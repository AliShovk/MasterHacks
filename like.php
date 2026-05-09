<?php
error_reporting(E_ALL); ini_set("display_errors", 1);
/**
 * Обработка лайков с защитой от CSRF и rate limiting
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS заголовки (при необходимости)
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_HOST'] ?? '*'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limiting на основе IP
session_start();
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = 'like_rate_' . md5($client_ip);
$rate_limit = 30; // Максимум 30 лайков в минуту
$rate_window = 60; // Окно в секундах

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = ['count' => 0, 'start' => time()];
}

$rate_data = &$_SESSION[$rate_key];

// Сброс счетчика если прошло больше минуты
if (time() - $rate_data['start'] > $rate_window) {
    $rate_data = ['count' => 0, 'start' => time()];
}

// Проверка лимита
if ($rate_data['count'] >= $rate_limit) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait.']);
    exit;
}

$rate_data['count']++;

// DB mode: likes live in MySQL, not in data/posts.json
if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';

    if (defined('USE_DB') && USE_DB === true) {
        $inputRaw = file_get_contents('php://input');
        $data = json_decode($inputRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            exit;
        }

        if (!isset($data['post_id']) || !is_numeric($data['post_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
            exit;
        }

        $postId = (int)$data['post_id'];
        if ($postId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Post ID must be positive']);
            exit;
        }

        $telegramId = !empty($_SESSION['telegram_id']) ? (int)$_SESSION['telegram_id'] : 0;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $fingerprint = hash('sha256', $client_ip . '|' . $ua . '|' . session_id());

        try {
            $pdo = getDatabaseConnection();
            $pdo->beginTransaction();

            if ($telegramId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT id FROM user_actions WHERE telegram_id = :tid AND action_type = 'like' AND video_id = :vid ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([':tid' => $telegramId, ':vid' => $postId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT id FROM user_actions WHERE action_type = 'like' AND video_id = :vid AND fingerprint = :fp ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([':vid' => $postId, ':fp' => $fingerprint]);
            }

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            $liked = false;

            if ($existing && isset($existing['id'])) {
                // unlike
                $del = $pdo->prepare('DELETE FROM user_actions WHERE id = :id');
                $del->execute([':id' => (int)$existing['id']]);

                $upd = $pdo->prepare('UPDATE videos SET likes = GREATEST(0, likes - 1) WHERE id = :id');
                $upd->execute([':id' => $postId]);
                $liked = false;
            } else {
                // like
                $ins = $pdo->prepare(
                    "INSERT INTO user_actions (telegram_id, action_type, video_id, ip_address, user_agent, fingerprint, created_at) VALUES (:tid, 'like', :vid, :ip, :ua, :fp, NOW())"
                );
                $ins->execute([
                    ':tid' => $telegramId,
                    ':vid' => $postId,
                    ':ip' => $client_ip,
                    ':ua' => $ua,
                    ':fp' => $fingerprint
                ]);

                $upd = $pdo->prepare('UPDATE videos SET likes = likes + 1 WHERE id = :id');
                $upd->execute([':id' => $postId]);
                $liked = true;
            }

            $sel = $pdo->prepare('SELECT likes FROM videos WHERE id = :id');
            $sel->execute([':id' => $postId]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'likes' => isset($row['likes']) ? (int)$row['likes'] : 0,
                'liked' => $liked
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            error_log("LIKE_ERROR: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Server error'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// Чтение и валидация входных данных
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Валидация post_id
if (!isset($data['post_id']) || !is_numeric($data['post_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
    exit;
}

$post_id = intval($data['post_id']);

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Post ID must be positive']);
    exit;
}

// Проверка существования файла постов
$posts_file = 'data/posts.json';
if (!file_exists($posts_file)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Posts file not found']);
    exit;
}

// Чтение постов с блокировкой
$fp = fopen($posts_file, 'r+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Cannot open posts file']);
    exit;
}

// Блокировка файла для атомарной операции
if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Cannot lock file']);
    exit;
}

$content = stream_get_contents($fp);
$posts = json_decode($content, true);

if (!is_array($posts)) {
    flock($fp, LOCK_UN);
    fclose($fp);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Invalid posts data']);
    exit;
}

// Находим пост и обновляем лайки
$found = false;
$result = ['success' => false];

foreach ($posts as &$post) {
    if (isset($post['id']) && $post['id'] == $post_id) {
        $found = true;
        
        // Инициализация полей если не существуют
        if (!isset($post['likes'])) {
            $post['likes'] = 0;
        }
        if (!isset($post['user_liked'])) {
            $post['user_liked'] = false;
        }
        
        // Переключение лайка
        if ($post['user_liked']) {
            $post['likes'] = max(0, $post['likes'] - 1);
            $post['user_liked'] = false;
        } else {
            $post['likes']++;
            $post['user_liked'] = true;
        }
        
        $result = [
            'success' => true,
            'likes' => $post['likes'],
            'liked' => $post['user_liked']
        ];
        
        break;
    }
}

if (!$found) {
    flock($fp, LOCK_UN);
    fclose($fp);
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit;
}

// Записываем обновленные данные
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Инвалидируем кэш
$cache_file = 'data/cache/posts_cache.json';
if (file_exists($cache_file)) {
    @unlink($cache_file);
}

// Разблокировка и закрытие
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>

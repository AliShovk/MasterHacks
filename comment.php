<?php
/**
 * Обработка комментариев с защитой от XSS и rate limiting
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limiting
session_start();
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = 'comment_rate_' . md5($client_ip);
$rate_limit = 10; // Максимум 10 комментариев в минуту
$rate_window = 60;

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = ['count' => 0, 'start' => time()];
}

$rate_data = &$_SESSION[$rate_key];

if (time() - $rate_data['start'] > $rate_window) {
    $rate_data = ['count' => 0, 'start' => time()];
}

if ($rate_data['count'] >= $rate_limit) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Слишком много комментариев. Подождите немного.']);
    exit;
}

$rate_data['count']++;

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

// Валидация и санитизация текста комментария
if (!isset($data['text']) || !is_string($data['text'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment text required']);
    exit;
}

$text = trim($data['text']);

// Проверка длины
if (mb_strlen($text) < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Комментарий не может быть пустым']);
    exit;
}

if (mb_strlen($text) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Комментарий слишком длинный (макс. 500 символов)']);
    exit;
}

// XSS защита - полная санитизация
$text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Удаление потенциально опасных символов и паттернов
$text = preg_replace('/javascript:/i', '', $text);
$text = preg_replace('/on\w+\s*=/i', '', $text);
$text = preg_replace('/<[^>]*>/i', '', $text);

// Проверка на спам (простые эвристики)
$spam_patterns = [
    '/(.)\1{10,}/', // Повторяющиеся символы
    '/https?:\/\/[^\s]+/i', // Ссылки
    '/@[a-z0-9_]+/i', // Упоминания
];

foreach ($spam_patterns as $pattern) {
    if (preg_match($pattern, $text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Комментарий содержит запрещенный контент']);
        exit;
    }
}

$parent_id = null;
if (isset($data['parent_id'])) {
    if ($data['parent_id'] === null || $data['parent_id'] === '') {
        $parent_id = null;
    } elseif (is_numeric($data['parent_id'])) {
        $parent_id = (int)$data['parent_id'];
        if ($parent_id <= 0) {
            $parent_id = null;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parent ID']);
        exit;
    }
}

if (!file_exists(__DIR__ . '/config/database.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database not configured']);
    exit;
}

require_once __DIR__ . '/config/database.php';

$telegram_id = !empty($_SESSION['telegram_id']) ? (int)$_SESSION['telegram_id'] : null;
$author = 'Аноним';
if (!empty($_SESSION['username'])) {
    $author = '@' . (string)$_SESSION['username'];
} elseif (!empty($_SESSION['first_name'])) {
    $author = (string)$_SESSION['first_name'];
}

try {
    $pdo = getDatabaseConnection();

    if ($parent_id !== null) {
        $stmt = $pdo->prepare('SELECT id FROM comments WHERE id = :pid AND video_id = :vid');
        $stmt->execute([':pid' => $parent_id, ':vid' => $post_id]);
        $parentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$parentRow) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Parent comment not found']);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO comments (video_id, telegram_id, author_name, text, parent_id, created_at) '
        . 'VALUES (:vid, :tid, :author, :text, :parent_id, NOW())'
    );
    $stmt->execute([
        ':vid' => $post_id,
        ':tid' => $telegram_id,
        ':author' => $author,
        ':text' => $text,
        ':parent_id' => $parent_id
    ]);

    $commentId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('UPDATE videos SET comments_count = comments_count + 1 WHERE id = :vid');
    $stmt->execute([':vid' => $post_id]);

    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $commentId,
            'parent_id' => $parent_id,
            'author' => htmlspecialchars($author, ENT_QUOTES, 'UTF-8'),
            'text' => $text,
            'date' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>

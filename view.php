<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();

if (!file_exists(__DIR__ . '/config/database.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database not configured'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/config/database.php';

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);
if (!is_array($input) || !isset($input['post_id']) || !is_numeric($input['post_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid post ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

$postId = (int)$input['post_id'];
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Post ID must be positive'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ttlSeconds = 600;
$key = 'view_' . $postId;
$now = time();

try {
    $pdo = getDatabaseConnection();

    $sel0 = $pdo->prepare('SELECT views FROM videos WHERE id = :id');
    $sel0->execute([':id' => $postId]);
    $row0 = $sel0->fetch(PDO::FETCH_ASSOC);

    if (!$row0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Post not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_SESSION[$key]) && is_int($_SESSION[$key]) && ($now - $_SESSION[$key]) < $ttlSeconds) {
        echo json_encode([
            'success' => true,
            'skipped' => true,
            'views' => (int)$row0['views']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION[$key] = $now;

    $updated = false;
    $lastErr = null;

    for ($i = 0; $i < 2; $i++) {
        try {
            $upd = $pdo->prepare('UPDATE videos SET views = views + 1 WHERE id = :id');
            $upd->execute([':id' => $postId]);

            if ($upd->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Post not found'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $updated = true;
            $lastErr = null;
            break;
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
            if ($i === 0 && preg_match('/Unknown column\s+\'?views\'?/i', $lastErr)) {
                $pdo->exec('ALTER TABLE videos ADD COLUMN views INT NOT NULL DEFAULT 0');
                continue;
            }
            break;
        }
    }

    $sel = $pdo->prepare('SELECT views FROM videos WHERE id = :id');
    $sel->execute([':id' => $postId]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => $updated,
        'views' => $row ? (int)$row['views'] : null,
        'error' => $updated ? null : $lastErr
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['telegram_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Не авторизован'], JSON_UNESCAPED_UNICODE);
    exit;
}

$telegram_id = (int)$_SESSION['telegram_id'];
try {
    $pdo = getDatabaseConnection();

    $videos = [];
    $sqls = [
        // full
        "SELECT id, filename, file_type as type, status, views, likes, comments_count, created_at, published_at FROM videos WHERE telegram_id = :tid ORDER BY created_at DESC",
        // without views/comments_count
        "SELECT id, filename, file_type as type, status, likes, created_at, published_at FROM videos WHERE telegram_id = :tid ORDER BY created_at DESC",
        // minimal
        "SELECT id, filename, file_type as type, created_at FROM videos WHERE telegram_id = :tid ORDER BY created_at DESC"
    ];

    $lastErr = null;
    foreach ($sqls as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':tid' => $telegram_id]);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $lastErr = null;
            break;
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
            $videos = [];
            continue;
        }
    }

    if ($lastErr !== null) {
        http_response_code(500);
        echo json_encode(['error' => 'DB error: ' . $lastErr], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$mediaDir = __DIR__ . '/media/';
$videos = array_values(array_filter($videos, function($video) use ($mediaDir) {
    $filename = $video['filename'] ?? '';
    return $filename && is_file($mediaDir . $filename);
}));

foreach ($videos as &$v) {
    $v['id'] = (int)($v['id'] ?? 0);
    $v['views'] = (int)($v['views'] ?? 0);
    $v['likes'] = (int)($v['likes'] ?? 0);
    $v['comments_count'] = (int)($v['comments_count'] ?? 0);
}
unset($v);

echo json_encode($videos, JSON_UNESCAPED_UNICODE);

<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['telegram_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$video_id = isset($input['video_id']) ? (int)$input['video_id'] : 0;

if ($video_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный ID видео'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDatabaseConnection();
$telegram_id = (int)$_SESSION['telegram_id'];

$stmt = $pdo->prepare('SELECT id, filename, telegram_id, status FROM videos WHERE id = :id');
$stmt->execute([':id' => $video_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Видео не найдено'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int)$video['telegram_id'] !== $telegram_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Нет прав'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($video['status'] ?? '') !== 'pending') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Отменить можно только видео на модерации'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM videos WHERE id = :id AND status = "pending"');
    $stmt->execute([':id' => $video_id]);

    $file_path = __DIR__ . '/media/' . ($video['filename'] ?? '');
    if (is_file($file_path)) {
        @unlink($file_path);
    }

    $cache_file = __DIR__ . '/data/cache/posts_cache.json';
    if (is_file($cache_file)) {
        @unlink($cache_file);
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка'], JSON_UNESCAPED_UNICODE);
}

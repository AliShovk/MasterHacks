<?php
// Публикация видео в Telegram-канал
require_once __DIR__ . '/config/database.php';

$token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : getenv('TELEGRAM_BOT_TOKEN');
$chatId = getenv('TELEGRAM_CHAT_ID') ?: '@masterhacksru';

if (!$token) { die('No bot token'); }

$videoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($videoId <= 0) { die('No video id'); }

$pdo = getDatabaseConnection();
$stmt = $pdo->prepare('SELECT id, filename, title, description, file_type FROM videos WHERE id = :id AND status = "approved"');
$stmt->execute([':id' => $videoId]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) { die('Video not found'); }

$filePath = __DIR__ . '/media/' . $video['filename'];
if (!file_exists($filePath)) { die('File not found: ' . $filePath); }

$caption = ($video['title'] ?? 'MasterHacks') . "\n\n" . ($video['description'] ?? '');
$caption .= "\n\n🔗 Смотреть на сайте: https://masterhacks.ru/?p=" . $video['id'];

// Отправка видео через multipart/form-data
$ch = curl_init("https://api.telegram.org/bot{$token}/sendVideo");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'chat_id' => $chatId,
        'video' => new CURLFile($filePath),
        'caption' => $caption,
        'parse_mode' => 'HTML',
        'supports_streaming' => true,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result && ($result['ok'] ?? false)) {
    $messageId = $result['result']['message_id'] ?? 0;
    $stmt = $pdo->prepare('UPDATE videos SET telegram_posted = 1 WHERE id = :id');
    $stmt->execute([':id' => $videoId]);
    echo json_encode(['ok' => true, 'id' => $videoId, 'message_id' => $messageId]);
} else {
    echo json_encode(['ok' => false, 'error' => $response ?: $error, 'http' => $httpCode]);
}

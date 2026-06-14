<?php
/**
 * notify.php - Telegram notification helpers for MasterHacks
 * 
 * Usage from other scripts:
 *   require_once __DIR__ . '/notify.php';
 *   notifyAdmin("Новое видео загружено!");
 *   postToChannel("🔥 Новое видео: Как взломать пароль?\n\nhttps://masterhacks.ru");
 */

require_once __DIR__ . '/config/env.php';
loadEnvFile(__DIR__);

define('NH_TOKEN', defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : getenv('TELEGRAM_BOT_TOKEN'));
define('NH_ADMIN_ID', getenv('ADMIN_CHAT_ID') ?: '');
define('NH_CHANNEL_ID', getenv('TELEGRAM_CHAT_ID') ?: '@masterhacksru');

/**
 * Send a message via Telegram Bot API.
 */
function telegramApi($method, $params) {
    $url = 'https://api.telegram.org/bot' . NH_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

/**
 * Send notification to admin via DM.
 */
function notifyAdmin($message) {
    if (empty(NH_ADMIN_ID)) return false;
    return telegramApi('sendMessage', [
        'chat_id' => NH_ADMIN_ID,
        'text' => '🤖 ' . $message,
        'parse_mode' => 'HTML',
    ]);
}

/**
 * Post message to the channel.
 */
function postToChannel($message) {
    return telegramApi('sendMessage', [
        'chat_id' => NH_CHANNEL_ID,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false,
    ]);
}

/**
 * Post a video notification to the channel with title and link.
 */
function notifyNewVideo($videoId, $title, $description = '') {
    $siteUrl = getenv('SITE_URL') ?: 'https://masterhacks.ru';
    
    $msg = "🎬 <b>Новое видео!</b>\n\n";
    if (!empty($title)) {
        $msg .= "<b>" . htmlspecialchars($title) . "</b>\n\n";
    }
    if (!empty($description)) {
        $desc = mb_strlen($description) > 150 
            ? mb_substr($description, 0, 150) . '…' 
            : $description;
        $msg .= htmlspecialchars($desc) . "\n\n";
    }
    $msg .= "🔗 <a href=\"{$siteUrl}\">Смотреть на MasterHacks</a>";
    
    return postToChannel($msg);
}

/**
 * If called directly with ?msg= or ?video_id=, send notification.
 */
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    
    $msg = $_GET['msg'] ?? null;
    $videoId = $_GET['video_id'] ?? null;
    $target = $_GET['target'] ?? 'channel'; // 'channel' or 'admin'
    
    if ($msg) {
        if ($target === 'admin') {
            $result = notifyAdmin($msg);
        } else {
            $result = postToChannel($msg);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($videoId) {
        // Query DB for video info
        try {
            require_once __DIR__ . '/config/database.php';
            $stmt = $pdo->prepare("SELECT id, title, description FROM videos WHERE id = :id");
            $stmt->execute([':id' => (int)$videoId]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($video) {
                $result = notifyNewVideo($video['id'], $video['title'], $video['description']);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Video not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    echo json_encode(['error' => 'No msg or video_id provided']);
}

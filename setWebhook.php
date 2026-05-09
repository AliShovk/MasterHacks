<?php
require_once __DIR__ . '/config/database.php';

$token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : (getenv('TELEGRAM_BOT_TOKEN') ?: '');
if (!$token) {
    http_response_code(500);
    echo 'TELEGRAM_BOT_TOKEN is not configured';
    exit;
}

$webhook_url = SITE_URL . '/bot.php';

$url = "https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($webhook_url);
$result = @file_get_contents($url);
if ($result === false) {
    http_response_code(502);
    echo 'Failed to call Telegram setWebhook API';
    exit;
}

echo $result;

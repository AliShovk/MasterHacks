<?php
require_once __DIR__ . '/config/database.php';

$bot_token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : (getenv('TELEGRAM_BOT_TOKEN') ?: '');
$webhook_url = rtrim(SITE_URL, '/') . '/api/telegram_webhook.php';

$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url={$webhook_url}";

$response = file_get_contents($api_url);
$result = json_decode($response, true);

$info_url = "https://api.telegram.org/bot{$bot_token}/getWebhookInfo";
$info_response = file_get_contents($info_url);
$info_json = json_decode($info_response, true);
$webhookInfo = (is_array($info_json) && ($info_json['ok'] ?? false)) ? ($info_json['result'] ?? []) : null;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Установка Webhook</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { background: #4CAF50; color: white; padding: 20px; border-radius: 10px; }
        .error { background: #f44336; color: white; padding: 20px; border-radius: 10px; }
        .info { background: #2196F3; color: white; padding: 15px; border-radius: 10px; margin: 10px 0; }
    </style>
</head>
<body>";

if ($result['ok']) {
    echo "<div class='success'>
        <h2>✅ Webhook успешно установлен!</h2>
        <p>URL: $webhook_url</p>
    </div>";
} else {
    echo "<div class='error'>
        <h2>❌ Ошибка установки Webhook</h2>
        <p>" . ($result['description'] ?? 'Неизвестная ошибка') . "</p>
    </div>";
}

echo "<div class='info'>
    <h3>Информация:</h3>
    <p>Токен бота: " . substr($bot_token, 0, 10) . "...</p>
    <p>Webhook URL: $webhook_url</p>
</div>";

echo "<div class='info'>
    <h3>Webhook status (getWebhookInfo):</h3>";

if (is_array($webhookInfo)) {
    $url = htmlspecialchars((string)($webhookInfo['url'] ?? ''), ENT_QUOTES, 'UTF-8');
    $hasCustom = !empty($webhookInfo['has_custom_certificate']);
    $pending = (int)($webhookInfo['pending_update_count'] ?? 0);
    $lastErrDate = isset($webhookInfo['last_error_date']) ? date('Y-m-d H:i:s', (int)$webhookInfo['last_error_date']) : '';
    $lastErrMsg = (string)($webhookInfo['last_error_message'] ?? '');
    $ip = htmlspecialchars((string)($webhookInfo['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8');

    echo "<p><b>url:</b> {$url}</p>";
    echo "<p><b>ip_address:</b> {$ip}</p>";
    echo "<p><b>pending_update_count:</b> {$pending}</p>";
    echo "<p><b>has_custom_certificate:</b> " . ($hasCustom ? 'true' : 'false') . "</p>";
    if ($lastErrMsg !== '') {
        echo "<p><b>last_error_date:</b> {$lastErrDate}</p>";
        echo "<p><b>last_error_message:</b> " . htmlspecialchars($lastErrMsg, ENT_QUOTES, 'UTF-8') . "</p>";
    } else {
        echo "<p><b>last_error_message:</b> (empty)</p>";
    }
} else {
    echo "<p><b>Ошибка getWebhookInfo:</b> " . htmlspecialchars((string)($info_json['description'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') . "</p>";
}

echo "</div>

<p><a href='index_db.php'>Перейти в DB ленту</a> | <a href='index.php'>Перейти в JSON ленту</a></p>
</body>
</html>";
?>
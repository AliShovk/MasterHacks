<?php
require_once __DIR__ . '/env.php';
loadEnvFile(dirname(__DIR__));

if (!defined('USE_DB')) {
    define('USE_DB', filter_var(getenv('USE_DB') ?: 'true', FILTER_VALIDATE_BOOLEAN));
}

if (!defined('PROJECT_NAME')) { define('PROJECT_NAME', getenv('PROJECT_NAME') ?: 'MasterHacks'); }
if (!defined('ADMIN_PANEL_KEY')) { define('ADMIN_PANEL_KEY', getenv('ADMIN_PANEL_KEY') ?: 'change_me_admin_key'); }
if (!defined('SITE_URL')) { define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost'); }
if (!defined('TELEGRAM_BOT_TOKEN')) { define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: ''); }

$telegramBotUrl = trim((string)(getenv('TELEGRAM_BOT_URL') ?: ''));
$telegramBotUsername = trim((string)(getenv('TELEGRAM_BOT_USERNAME') ?: ''));

if ($telegramBotUsername === 'your_bot_username') {
    $telegramBotUsername = '';
}

if ($telegramBotUrl === 'https://t.me/your_bot_username') {
    $telegramBotUrl = '';
}

if ($telegramBotUsername === '' && $telegramBotUrl !== '') {
    if (preg_match('~(?:https?://)?t\.me/([A-Za-z0-9_]+)~i', $telegramBotUrl, $m)) {
        $telegramBotUsername = $m[1];
    }
}

if ($telegramBotUrl === '' && $telegramBotUsername !== '') {
    $telegramBotUrl = 'https://t.me/' . $telegramBotUsername;
}

if (!defined('TELEGRAM_BOT_URL')) { define('TELEGRAM_BOT_URL', $telegramBotUrl); }
if (!defined('TELEGRAM_BOT_USERNAME')) { define('TELEGRAM_BOT_USERNAME', $telegramBotUsername); }
if (!defined('ADMIN_CHAT_ID')) { define('ADMIN_CHAT_ID', getenv('ADMIN_CHAT_ID') ?: ''); }

function getDatabaseConnection() {
    $envHost = getenv('DB_HOST');
    $isLocalEnvHost = ($envHost === 'localhost' || $envHost === '127.0.0.1');
    if ($envHost !== false && $envHost !== '' && !$isLocalEnvHost) {
        $hosts = [$envHost, 'localhost'];
    } else {
        $hosts = ['localhost'];
    }
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'app_database';
    $username = getenv('DB_USER') ?: 'app_user';
    $password = getenv('DB_PASS') ?: '';

    $lastException = null;
    foreach ($hosts as $host) {
        try {
            $dsn = "mysql:host={$host};";
            if ($port !== '') {
                $dsn .= "port={$port};";
            }
            $dsn .= "dbname={$dbname};charset=utf8mb4";

            $pdo = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
            continue;
        }
    }
    throw $lastException;
}

<?php
// telegram_bot.php - Исправленный бот без проблем с дубликатами

require_once __DIR__ . '/config/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_once __DIR__ . '/api/telegram_webhook.php';
    exit;
}

$bot_token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : (getenv('TELEGRAM_BOT_TOKEN') ?: '');
$adminChatId = getenv('ADMIN_CHAT_ID') ?: '';
$admin_ids = $adminChatId !== '' ? [(int)$adminChatId] : []; // Замените через .env

// Логирование
file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " === НОВЫЙ ЗАПРОС ===\n", FILE_APPEND);

// Получаем входящие данные
$content = file_get_contents("php://input");
$update = json_decode($content, true);

file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Данные получены\n", FILE_APPEND);

if (!$update && empty($_POST)) {
    // Если это не вебхук, показываем информацию
    showInfoPage();
    exit;
}

// Обрабатываем сообщение
if (isset($update['message'])) {
    handleMessage($update['message']);
}

function handleMessage($message) {
    global $bot_token, $admin_ids;
    
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $username = $message['from']['username'] ?? 'Аноним';
    
    file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Сообщение от $username ($user_id)\n", FILE_APPEND);
    
    // Проверяем права администратора
    if (!in_array($user_id, $admin_ids)) {
        sendMessage($chat_id, "❌ У вас нет прав для использования этого бота.");
        return;
    }
    
    // Обработка текстовых команд
    if (isset($message['text'])) {
        $text = $message['text'];
        
        if ($text == '/start') {
            sendMessage($chat_id, "🤖 Бот для загрузки контента в ленту MasterHacks\n\nДоступные команды:\n/help - помощь\n/status - статус системы\n\nПросто отправьте фото или видео, и они автоматически добавятся в ленту!");
        }
        elseif ($text == '/help') {
            sendMessage($chat_id, "📋 Помощь:\n\n1. Отправьте фото или видео в бота\n2. Файлы автоматически сохранятся в папку media/\n3. Лента обновится автоматически\n\nТребования:\n- Видео: до 50 MB\n- Фото: до 20 MB\n- Форматы: jpg, png, mp4, mov, avi");
        }
        elseif ($text == '/status') {
            $status = getSystemStatus();
            sendMessage($chat_id, $status);
        }
        elseif ($text == '/update') {
            updateFeed();
            sendMessage($chat_id, "✅ Лента обновлена!");
        }
        elseif ($text == '/scan') {
            sendMessage($chat_id, "🔍 Запуск сканирования...");
            shell_exec('php scan.php > /dev/null 2>&1 &');
            sendMessage($chat_id, "✅ Сканирование запущено. Через 5 секунд лента обновится.");
            sleep(5);
            updateFeed();
        }
        else {
            sendMessage($chat_id, "❓ Неизвестная команда. Используйте /help для справки.");
        }
    }
    
    // Обработка фото
    if (isset($message['photo'])) {
        $photos = $message['photo'];
        $photo = end($photos); // Берем фото самого высокого качества
        $file_id = $photo['file_id'];
        
        sendMessage($chat_id, "📸 Получено фото, начинаю загрузку...");
        
        if (downloadFile($file_id, 'image')) {
            sendMessage($chat_id, "✅ Фото успешно загружено в ленту!");
        } else {
            sendMessage($chat_id, "❌ Ошибка загрузки фото.");
        }
    }
    
    // Обработка видео
    if (isset($message['video'])) {
        $video = $message['video'];
        $file_id = $video['file_id'];
        
        sendMessage($chat_id, "🎥 Получено видео, начинаю загрузку...");
        
        if (downloadFile($file_id, 'video')) {
            sendMessage($chat_id, "✅ Видео успешно загружено в ленту!");
        } else {
            sendMessage($chat_id, "❌ Ошибка загрузки видео.");
        }
    }
}

function downloadFile($file_id, $type) {
    global $bot_token;
    
    // Получаем путь к файлу
    $api_url = "https://api.telegram.org/bot{$bot_token}/getFile?file_id={$file_id}";
    $response = file_get_contents($api_url);
    $result = json_decode($response, true);
    
    if (!$result['ok']) {
        file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Ошибка получения пути файла\n", FILE_APPEND);
        return false;
    }
    
    $file_path = $result['result']['file_path'];
    
    // Скачиваем файл
    $download_url = "https://api.telegram.org/file/bot{$bot_token}/{$file_path}";
    $file_content = file_get_contents($download_url);
    
    if (!$file_content) {
        file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Ошибка скачивания файла\n", FILE_APPEND);
        return false;
    }
    
    // Создаем хэш файла
    $file_hash = md5($file_content);
    
    // Проверяем, не существует ли уже файл с таким хэшем
    if (isFileDuplicate($file_hash)) {
        sendMessage($chat_id, "⚠️ Этот файл уже есть в ленте (дубликат)");
        return false;
    }
    
    // Создаем уникальное имя файла
    $ext = pathinfo($file_path, PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . $file_hash . '.' . $ext;
    
    // Сохраняем файл
    $save_path = 'media/' . $filename;
    if (!file_exists('media')) {
        mkdir('media', 0777, true);
    }
    
    if (file_put_contents($save_path, $file_content)) {
        file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Файл сохранен: $save_path\n", FILE_APPEND);
        
        // Добавляем запись в posts.json
        if (addToPostsJson($filename, $type, $file_hash)) {
            // Обновляем ленту
            updateFeed();
            return true;
        } else {
            // Если не удалось добавить в JSON, удаляем файл
            unlink($save_path);
            return false;
        }
    }
    
    file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Ошибка сохранения файла\n", FILE_APPEND);
    return false;
}

// УПРОЩЕННАЯ проверка дубликатов - только по хэшу
function isFileDuplicate($file_hash) {
    $posts_file = 'data/posts.json';
    
    if (!file_exists($posts_file)) {
        return false;
    }
    
    $posts = json_decode(file_get_contents($posts_file), true) ?: [];
    
    foreach ($posts as $post) {
        // Проверяем по имени файла (в имени содержится хэш)
        if (strpos($post['filename'], $file_hash) !== false) {
            return true;
        }
    }
    
    return false;
}

function addToPostsJson($filename, $type, $file_hash) {
    $posts_file = 'data/posts.json';
    
    if (!file_exists('data')) {
        mkdir('data', 0777, true);
    }
    
    // Загружаем существующие посты
    if (file_exists($posts_file)) {
        $posts = json_decode(file_get_contents($posts_file), true) ?: [];
    } else {
        $posts = [];
    }
    
    // Определяем следующий ID
    $next_id = 1;
    if (!empty($posts)) {
        $ids = array_column($posts, 'id');
        if (!empty($ids)) {
            $next_id = max($ids) + 1;
        }
    }
    
    // Добавляем новый пост
    $new_post = [
        'id' => $next_id,
        'filename' => $filename,
        'type' => $type,
        'likes' => 0,
        'comments_count' => 0,
        'date' => date('Y-m-d H:i:s'),
        'user_liked' => false,
        'file_hash' => $file_hash
    ];
    
    // Добавляем в начало массива
    array_unshift($posts, $new_post);
    
    // Сохраняем
    if (file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Добавлен в posts.json: $filename (хэш: $file_hash)\n", FILE_APPEND);
        return true;
    }
    
    file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Ошибка записи в posts.json\n", FILE_APPEND);
    return false;
}

function updateFeed() {
    // Очищаем кеш
    $cache_file = 'data/cache/posts_cache.json';
    if (file_exists($cache_file)) {
        unlink($cache_file);
    }
    
    // Также можно перезапустить сканер
    $scanner_file = 'scan.php';
    if (file_exists($scanner_file)) {
        // Запускаем сканер в фоне
        shell_exec('php scan.php > /dev/null 2>&1 &');
    }
    
    file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Кеш очищен, сканер запущен\n", FILE_APPEND);
}

function sendMessage($chat_id, $text) {
    global $bot_token;
    
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - Отправлено сообщение: $text\n", FILE_APPEND);
    
    return $response;
}

function getSystemStatus() {
    $posts_file = 'data/posts.json';
    $media_dir = 'media/';
    
    $status = "📊 Статус системы:\n\n";
    
    // Количество постов
    if (file_exists($posts_file)) {
        $posts = json_decode(file_get_contents($posts_file), true) ?: [];
        $images = 0;
        $videos = 0;
        
        foreach ($posts as $post) {
            if ($post['type'] == 'image') $images++;
            if ($post['type'] == 'video') $videos++;
        }
        
        $status .= "📈 Постов в базе: " . count($posts) . "\n";
        $status .= "📸 Изображений: $images\n";
        $status .= "🎥 Видео: $videos\n\n";
    } else {
        $status .= "❌ Файл posts.json не найден\n\n";
    }
    
    // Количество файлов в media
    if (file_exists($media_dir)) {
        $files = scandir($media_dir);
        $file_count = count($files) - 2;
        $status .= "📁 Файлов в media/: $file_count\n";
    }
    
    return $status;
}

function showInfoPage() {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>🤖 Telegram Bot Webhook</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
            .container { background: #f5f5f5; padding: 30px; border-radius: 15px; }
            .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .code { background: #333; color: #fff; padding: 15px; border-radius: 5px; font-family: monospace; }
            h1 { color: #333; }
            .btn { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .btn:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🤖 Telegram Bot Webhook</h1>
            
            <div class="success">
                <h3>✅ Вебхук установлен</h3>
                <p>Бот готов к работе!</p>
            </div>
            
            <div class="info">
                <h3>📋 Инструкция:</h3>
                <ol>
                    <li>Найдите бота в Telegram</li>
                    <li>Отправьте команду <code>/start</code></li>
                    <li>Отправляйте фото или видео в бота</li>
                    <li>Контент автоматически появится в ленте</li>
                </ol>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn">🎬 Перейти в ленту</a>
                <a href="index.php?force_refresh=1" class="btn">🔄 Обновить ленту</a>
                <a href="scan.php" class="btn">🔍 Запустить сканер</a>
            </div>
        </div>
    </body>
    </html>';
}

// Если скрипт запущен не через вебхук, показываем инфостраницу
if (empty($content) && empty($_POST)) {
    showInfoPage();
}
?>
<?php
// НОРМАЛЬНЫЙ БОТ ДЛЯ ЗАРАБОТКА В КАНАЛАХ

$token = '8617834423:AAEMBuAdXd0c9CxnSYnnrk1koYd-3pUP1IU';
$admin_id = 5405885462;

// ВАШИ КАНАЛЫ ДЛЯ ЗАРАБОТКА
$channels = [
    'rek_agt' => [
        'id' => '@rek_agt',
        'name' => 'Основной канал заработка',
        'username' => 'rek_agt'
    ],
    'reagt1' => [
        'id' => '@reagt1', 
        'name' => 'Второй канал заработка',
        'username' => 'reagt1'
    ]
];

// Получаем входящее сообщение
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if ($update) {
    // Логируем запрос
    file_put_contents('/var/www/masterhacks.ru/monetization_bot/logs/requests.log', 
        date('Y-m-d H:i:s') . ' - ' . json_encode($update) . "\n", 
        FILE_APPEND);
    
    // Обрабатываем сообщение
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Только для администратора
        if ($user_id == $admin_id) {
            processAdminCommand($chat_id, $text);
        }
    }
}

echo 'OK';

function processAdminCommand($chat_id, $text) {
    global $token, $channels;
    
    $response = '';
    
    if ($text == '/start') {
        $response = "🚀 *НОРМАЛЬНЫЙ БОТ ДЛЯ ЗАРАБОТКА*\n\n";
        $response .= "Ваши каналы:\n";
        $response .= "1. @rek_agt - Основной канал\n";
        $response .= "2. @reagt1 - Второй канал\n\n";
        $response .= "Команды:\n";
        $response .= "• /post [канал] [текст] - Создать пост\n";
        $response .= "• /channels - Список каналов\n";
        $response .= "• /stats - Статистика\n";
        $response .= "• /help - Помощь";
    }
    elseif ($text == '/channels') {
        $response = "📢 *ВАШИ КАНАЛЫ ДЛЯ ЗАРАБОТКА:*\n\n";
        $response .= "1. *@rek_agt*\n";
        $response .= "   Основной канал для постинга и развития\n\n";
        $response .= "2. *@reagt1*\n";
        $response .= "   Второй канал для постинга\n\n";
        $response .= "Используйте /post для создания постов!";
    }
    elseif (strpos($text, '/post ') === 0) {
        // Команда для постинга: /post [канал] [текст]
        $parts = explode(' ', $text, 3);
        if (count($parts) >= 3) {
            $channel = $parts[1];
            $post_text = $parts[2];
            
            if ($channel == 'rek_agt' || $channel == 'reagt1') {
                $channel_id = $channel == 'rek_agt' ? '@rek_agt' : '@reagt1';
                
                // Отправляем пост в канал
                $result = sendToChannel($channel_id, $post_text);
                
                if ($result) {
                    $response = "✅ *ПОСТ ОПУБЛИКОВАН!*\n\n";
                    $response .= "Канал: {$channel_id}\n";
                    $response .= "Текст: {$post_text}\n\n";
                    $response .= "Ссылка: https://t.me/{$channel}/?post";
                } else {
                    $response = "❌ Ошибка публикации. Проверьте права бота в канале.";
                }
            } else {
                $response = "❌ Неверный канал. Используйте: rek_agt или reagt1";
            }
        } else {
            $response = "❌ Формат: /post [канал] [текст]\nПример: /post rek_agt Привет, это тестовый пост!";
        }
    }
    elseif ($text == '/stats') {
        $response = "📊 *СТАТИСТИКА КАНАЛОВ:*\n\n";
        $response .= "*@rek_agt:*\n";
        $response .= "• Основной канал заработка\n";
        $response .= "• Готов к постингу\n\n";
        $response .= "*@reagt1:*\n";
        $response .= "• Второй канал заработка\n";
        $response .= "• Готов к постингу\n\n";
        $response .= "Используйте /post для заработка!";
    }
    elseif ($text == '/help') {
        $response = "🦀 *ПОМОЩЬ ПО КОМАНДАМ:*\n\n";
        $response .= "*/start* - Запуск бота\n";
        $response .= "*/channels* - Ваши каналы\n";
        $response .= "*/post [канал] [текст]* - Создать пост\n";
        $response .= "   Пример: /post rek_agt Новый контент!\n";
        $response .= "*/stats* - Статистика\n";
        $response .= "*/help* - Эта справка";
    }
    else {
        $response = "Напишите /help для списка команд";
    }
    
    // Отправляем ответ администратору
    sendMessage($chat_id, $response);
}

function sendMessage($chat_id, $text) {
    global $token;
    
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
        ],
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

function sendToChannel($channel_id, $text) {
    global $token;
    
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $channel_id,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
        ],
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    // Логируем публикацию
    file_put_contents('/var/www/masterhacks.ru/monetization_bot/logs/posts.log', 
        date('Y-m-d H:i:s') . ' - ' . $channel_id . ' - ' . $text . "\n", 
        FILE_APPEND);
    
    return $result !== false;
}

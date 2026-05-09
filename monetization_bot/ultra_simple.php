<?php
// УЛЬТРА-ПРОСТОЙ РАБОЧИЙ БОТ

// Получаем данные
$input = file_get_contents('php://input');
file_put_contents('/var/www/monetization_bot/logs/ultra.log', date('Y-m-d H:i:s') . ' - ' . $input . "\n", FILE_APPEND);

// Всегда отвечаем OK
echo 'OK';

// Если есть сообщение - обрабатываем
$data = json_decode($input, true);
if (!$data || !isset($data['message'])) {
    exit;
}

$message = $data['message'];
$chat_id = $message['chat']['id'];
$text = $message['text'] ?? '';

// Только для администратора
if ($message['from']['id'] != 5405885462) {
    exit;
}

// Отправляем ответ
$token = '8617834423:AAEMBuAdXd0c9CxnSYnnrk1koYd-3pUP1IU';
$url = "https://api.telegram.org/bot{$token}/sendMessage";

if ($text == '/start') {
    $msg = "✅ БОТ РАБОТАЕТ!\n\nПишите /help для команд";
} elseif ($text == '/help') {
    $msg = "🦀 Команды:\n/start - проверка\n/referral - KuCoin\n/stats - каналы\n/help - справка";
} elseif ($text == '/referral') {
    $msg = "💰 KuCoin: rM7BV54\nhttps://www.kucoin.com/r/af/rM7BV54";
} elseif ($text == '/stats') {
    $msg = "📊 Каналы:\n1. t.me/pumpscrin\n2. t.me/crybotis\n3. t.me/rek_agt\n4. t.me/reagt1";
} else {
    $msg = "Напишите /help";
}

$post = [
    'chat_id' => $chat_id,
    'text' => $msg,
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_exec($ch);
curl_close($ch);

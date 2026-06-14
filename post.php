<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once __DIR__ . "/config/env.php";
loadEnvFile(__DIR__);

$token = defined("TELEGRAM_BOT_TOKEN") ? TELEGRAM_BOT_TOKEN : getenv("TELEGRAM_BOT_TOKEN");
$chat_id = getenv("TELEGRAM_CHAT_ID") ?: "@masterhacksru";
$msg = $_GET["msg"] ?? "Test from MasterHacks!";

$ch = curl_init("https://api.telegram.org/bot" . $token . "/sendMessage");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(["chat_id" => $chat_id, "text" => $msg]),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_RETURNTRANSFER => true,
]);
$r = curl_exec($ch);
curl_close($ch);
header("Content-Type: application/json");
echo $r;

<?php
session_start();
require_once __DIR__ . '/config/database.php';

$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['token']) ? (string)$_POST['token'] : '';
} else {
    $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
}
$token = trim($token);

if ($token === '') {
    http_response_code(400);
    echo 'Токен не указан';
    exit;
}

if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
    http_response_code(400);
    echo 'Неверный формат токена';
    exit;
}

$pdo = getDatabaseConnection();

$stmt = $pdo->prepare(
    'SELECT telegram_id, username, first_name, expires_at, NOW() AS now_db '
    . 'FROM user_sessions '
    . 'WHERE token = :t'
);
$stmt->execute([':t' => $token]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    http_response_code(403);
    echo 'Токен недействителен';
    exit;
}

$expiresAt = $session['expires_at'] ?? null;
$nowDb = $session['now_db'] ?? null;
if ($expiresAt && $nowDb && strtotime((string)$expiresAt) <= strtotime((string)$nowDb)) {
    http_response_code(403);
    echo 'Срок действия токена истек';
    exit;
}

// IMPORTANT:
// Do NOT consume token on GET to avoid Telegram link preview using it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/html; charset=utf-8');
    $name = $session['username'] ? ('@' . htmlspecialchars((string)$session['username'], ENT_QUOTES, 'UTF-8')) : htmlspecialchars((string)($session['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($name === '') {
        $name = 'пользователь';
    }
    $safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><html lang=\"ru\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>Вход — MasterHacks</title><style>body{font-family:Arial,sans-serif;background:#0f0f0f;color:#fff;margin:0;padding:24px} .card{max-width:520px;margin:0 auto;background:#1a1a1a;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:18px} .btn{width:100%;border:0;border-radius:12px;padding:14px 16px;font-weight:800;background:#fc7b07;color:#000;font-size:16px;cursor:pointer} .muted{opacity:.75;font-size:12px;margin-top:10px;line-height:1.4}</style></head><body><div class=\"card\"><h2 style=\"margin:0 0 10px\">Подтверждение входа</h2><div style=\"margin-bottom:14px\">Войти как <b>{$name}</b>?</div><form method=\"post\" action=\"auth.php\"><input type=\"hidden\" name=\"token\" value=\"{$safeToken}\"><button class=\"btn\" type=\"submit\">Войти</button></form><div class=\"muted\">Если вы не запрашивали вход — просто закройте страницу. Ссылка одноразовая.</div></div></body></html>";
    exit;
}

$_SESSION['telegram_id'] = (int)$session['telegram_id'];
$_SESSION['username'] = $session['username'] ?? '';
$_SESSION['first_name'] = $session['first_name'] ?? '';

$stmt = $pdo->prepare('DELETE FROM user_sessions WHERE token = :t');
$stmt->execute([':t' => $token]);

header('Location: /');
exit;

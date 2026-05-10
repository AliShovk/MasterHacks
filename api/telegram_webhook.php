<?php
require_once __DIR__ . '/../config/database.php';

// Prefer env var TELEGRAM_BOT_TOKEN (recommended for security)
if (!defined('TELEGRAM_BOT_TOKEN')) {
    $__botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
    define('TELEGRAM_BOT_TOKEN', $__botToken);
}
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');

define('MEDIA_DIR', __DIR__ . '/../media/');

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!TELEGRAM_BOT_TOKEN) {
    error_log('Telegram Webhook Error: TELEGRAM_BOT_TOKEN is not configured');
    http_response_code(200);
    echo 'OK';
    exit;
}

if (!$update) {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Reply to Telegram ASAP to avoid timeouts / 503 from hosting.
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'OK';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    @flush();
}

$chatId = null;
if (isset($update['message']['chat']['id'])) {
    $chatId = $update['message']['chat']['id'];
} elseif (isset($update['callback_query']['message']['chat']['id'])) {
    $chatId = $update['callback_query']['message']['chat']['id'];
}

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $e) {
    error_log('Telegram Webhook DB Error: ' . $e->getMessage());
    if ($chatId) {
        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "❌ Серверная ошибка: база данных не подключена. Сообщение не обработано.",
            'parse_mode' => 'HTML'
        ]);
    }
    echo 'OK';
    exit;
}

try {
    if (isset($update['message'])) {
        handleMessage($pdo, $update['message']);
    } elseif (isset($update['callback_query'])) {
        // reserved
    }
} catch (Throwable $e) {
    error_log('Telegram Webhook Error: ' . $e->getMessage());
}

function handleMessage(PDO $pdo, array $message): void {
    $chatId = $message['chat']['id'] ?? null;
    $from = $message['from'] ?? null;

    if (!$chatId || !$from || !isset($from['id'])) {
        return;
    }

    $telegramId = (int)$from['id'];

    $author = getOrCreateAuthor($pdo, $from);

    if (isset($message['text'])) {
        $text = trim($message['text']);
        error_log('Telegram Webhook: text message received. telegram_id=' . $telegramId . '; text=' . $text);

        if (strpos($text, '/start') === 0) {
            $payload = trim(substr($text, 6));
            if ($payload === 'upload' || $payload === 'add' || $payload === 'video') {
                sendTelegramMessage(
                    $chatId,
                    "🎬 Отправь мне видео\n\n" .
                    "Просто пришли видео сюда (как обычное видео или файлом) — я загружу его на сайт после модерации.\n\n" .
                    "Как это работает:\n" .
                    "1) Отправь видео\n" .
                    "2) Я пришлю ID ролика\n" .
                    "3) Чтобы добавить описание: /desc <id> текст\n\n" .
                    "Можно отправлять несколько видео подряд."
                );
                return;
            }
        }

        if (strpos($text, '/start login') === 0 || $text === '/login') {
            error_log('Telegram Webhook Auth: login command accepted. telegram_id=' . $telegramId . '; text=' . $text);
            $token = bin2hex(random_bytes(16));
            $username = $from['username'] ?? null;
            $firstName = $from['first_name'] ?? '';

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO user_sessions (telegram_id, token, username, first_name, expires_at) '
                    . 'VALUES (:tid, :token, :u, :f, DATE_ADD(NOW(), INTERVAL 1 HOUR)) '
                    . 'ON DUPLICATE KEY UPDATE token = VALUES(token), username = VALUES(username), first_name = VALUES(first_name), expires_at = VALUES(expires_at)'
                );
                $stmt->execute([
                    ':tid' => $telegramId,
                    ':token' => $token,
                    ':u' => $username,
                    ':f' => $firstName
                ]);
            } catch (Throwable $e) {
                $err = $e->getMessage();
                error_log('Telegram Webhook Auth Insert Error: ' . $err);

                if (defined('ADMIN_CHAT_ID') && ADMIN_CHAT_ID) {
                    telegramApi('sendMessage', [
                        'chat_id' => ADMIN_CHAT_ID,
                        'text' => "Auth debug: insert failed.\ntelegram_id={$telegramId}\nerror={$err}",
                        'parse_mode' => 'HTML'
                    ]);
                }

                sendTelegramMessage(
                    $chatId,
                    "❌ Ошибка авторизации: не удалось сохранить токен.\nПопробуйте позже."
                );
                return;
            }

            $dbName = '';
            $dbHost = '';
            try {
                $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            } catch (Throwable $e) {
                $dbName = '';
            }
            try {
                $dbHost = (string)$pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            } catch (Throwable $e) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM user_sessions WHERE token = :t');
            $check->execute([':t' => $token]);
            $exists = (int)$check->fetchColumn();
            if ($exists <= 0) {
                $msg = 'Login token was generated but not found in user_sessions after insert. '
                    . 'telegram_id=' . $telegramId;
                error_log('Telegram Webhook Auth Debug: ' . $msg);

                sendTelegramMessage(
                    $chatId,
                    "❌ Ошибка авторизации: токен не сохранился в базе.\nПопробуйте позже или сообщите администратору."
                );
                return;
            }

            error_log('Telegram Webhook Auth: login token saved. telegram_id=' . $telegramId . '; token_prefix=' . substr($token, 0, 8));

            $loginUrl = rtrim((string)(defined('SITE_URL') ? SITE_URL : ''), '/') . '/auth.php?token=' . urlencode($token);
            if ($loginUrl === '/auth.php?token=' . urlencode($token)) {
                $loginUrl = 'auth.php?token=' . urlencode($token);
            }

            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "Действует 1 раз.",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '🟢 ВХОД',
                                'url' => $loginUrl
                            ]
                        ]
                    ]
                ], JSON_UNESCAPED_UNICODE)
            ]);
            return;
        }

        if ($text === '/start') {
            $authorId = isset($author['id']) ? (int)$author['id'] : 0;
            $authorLabel = $authorId > 0 ? "Ваш номер: #{$authorId}\n\n" : '';

            sendTelegramMessage(
                $chatId,
                "Добро пожаловать в MasterHacks!\n\n"
                . $authorLabel
                . "Как пользоваться ботом:\n"
                . "1) Отправьте видео или фото в этот чат — оно загрузится и попадёт на модерацию.\n"
                . "2) После одобрения админом материал появится в ленте сайта.\n\n"
                . "Описание к загруженному материалу:\n"
                . "- /desc <video_id> текст\n"
                . "  (пример: /desc 123 Привет, это мой ролик)\n\n"
                . "Вход на сайт и удаление своих видео:\n"
                . "- нажмите кнопку входа на сайте или отправьте: /start login\n"
                . "  бот пришлёт ссылку для входа (действует 1 раз).\n\n"
                . "Подсказка: после загрузки бот отправит ID (он нужен для /desc)."
            );
            return;
        }

        if (preg_match('/^\/desc\s+(\d+)\s+(.+)$/su', $text, $m)) {
            $videoId = (int)$m[1];
            $desc = trim($m[2]);

            $stmt = $pdo->prepare('UPDATE videos SET description = :d WHERE id = :id AND telegram_id = :tid');
            $stmt->execute([':d' => $desc, ':id' => $videoId, ':tid' => $telegramId]);

            sendTelegramMessage($chatId, $stmt->rowCount() ? '✅ Описание сохранено' : '❌ Не найдено видео или нет прав');
            return;
        }
    }

    if (isset($message['video'])) {
        $video = $message['video'];
        $videoId = handleTelegramMedia($pdo, $telegramId, $video['file_id'], 'video', $video['duration'] ?? null);
        if ($videoId) {
            sendTelegramMessage($chatId, "✅ Видео загружено (ID: {$videoId}).\nДобавьте описание: /desc {$videoId} текст");
        } else {
            sendTelegramMessage($chatId, '❌ Ошибка загрузки видео');
        }
        return;
    }

    if (isset($message['video_note'])) {
        $videoNote = $message['video_note'];
        $videoId = handleTelegramMedia($pdo, $telegramId, $videoNote['file_id'], 'video', $videoNote['duration'] ?? null);
        if ($videoId) {
            sendTelegramMessage($chatId, "✅ Видео загружено (ID: {$videoId}).\nДобавьте описание: /desc {$videoId} текст");
        } else {
            sendTelegramMessage($chatId, '❌ Ошибка загрузки видео');
        }
        return;
    }

    if (isset($message['animation'])) {
        $anim = $message['animation'];
        $videoId = handleTelegramMedia($pdo, $telegramId, $anim['file_id'], 'video', $anim['duration'] ?? null);
        if ($videoId) {
            sendTelegramMessage($chatId, "✅ Видео загружено (ID: {$videoId}).\nДобавьте описание: /desc {$videoId} текст");
        } else {
            sendTelegramMessage($chatId, '❌ Ошибка загрузки видео');
        }
        return;
    }

    if (isset($message['document'])) {
        $doc = $message['document'];
        $fileName = strtolower((string)($doc['file_name'] ?? ''));
        $extension = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $isVideo = ($doc['mime_type'] && substr($doc['mime_type'], 0, 6) === 'video/') || in_array($extension, $videoExtensions, true);
        $isImage = ($doc['mime_type'] && substr($doc['mime_type'], 0, 6) === 'image/') || in_array($extension, $imageExtensions, true);

        if ($isVideo || $isImage) {
            $fileType = $isVideo ? 'video' : 'image';
            $videoId = handleTelegramMedia($pdo, $telegramId, $doc['file_id'], $fileType, null);
            if ($videoId) {
                $label = $isVideo ? 'Видео' : 'Фото';
                sendTelegramMessage($chatId, "✅ {$label} загружено (ID: {$videoId}).\nДобавьте описание: /desc {$videoId} текст");
            } else {
                sendTelegramMessage($chatId, $isVideo ? '❌ Ошибка загрузки видео' : '❌ Ошибка загрузки фото');
            }
            return;
        }

        error_log('Telegram Webhook: unsupported document upload. mime=' . $doc['mime_type'] . '; file_name=' . $fileName . '; telegram_id=' . $telegramId);
        sendTelegramMessage($chatId, '❌ Этот файл не распознан как видео или фото. Отправьте видео как обычное видео или файлом .mp4/.mov, либо фото в формате jpg/png.');
        return;
    }

    if (isset($message['photo'])) {
        $photos = $message['photo'];
        $photo = end($photos);
        $videoId = handleTelegramMedia($pdo, $telegramId, $photo['file_id'], 'image', null);
        if ($videoId) {
            sendTelegramMessage($chatId, "✅ Фото загружено (ID: {$videoId}).\nДобавьте описание: /desc {$videoId} текст");
        } else {
            sendTelegramMessage($chatId, '❌ Ошибка загрузки фото');
        }
        return;
    }
}

function getOrCreateAuthor(PDO $pdo, array $from): array {
    $telegramId = (int)$from['id'];
    $username = $from['username'] ?? null;
    $firstName = $from['first_name'] ?? '';
    $lastName = $from['last_name'] ?? null;

    $stmt = $pdo->prepare('SELECT * FROM authors WHERE telegram_id = :telegram_id');
    $stmt->execute([':telegram_id' => $telegramId]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$author) {
        $stmt = $pdo->prepare('INSERT INTO authors (telegram_id, username, first_name, last_name, avatar_url, reputation_score, created_at) VALUES (:telegram_id, :username, :first_name, :last_name, :avatar_url, 10, NOW())');

        $stmt->execute([
            ':telegram_id' => $telegramId,
            ':username' => $username,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':avatar_url' => null
        ]);

        logUserAction($pdo, $telegramId, 'register', null, null, 10);

        $authorId = (int)$pdo->lastInsertId();

        return [
            'id' => $authorId,
            'telegram_id' => $telegramId,
            'username' => $from['username'] ?? null,
            'first_name' => $from['first_name'] ?? ''
        ];
    }

    $stmt = $pdo->prepare(
        'UPDATE authors SET username = :username, first_name = :first_name, last_name = :last_name, last_active = NOW(), updated_at = NOW() '
        . 'WHERE telegram_id = :telegram_id'
    );
    $stmt->execute([
        ':username' => $username,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':telegram_id' => $telegramId
    ]);

    $stmt = $pdo->prepare('SELECT * FROM authors WHERE telegram_id = :telegram_id');
    $stmt->execute([':telegram_id' => $telegramId]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC);

    return $author;
}

function handleTelegramMedia(PDO $pdo, int $telegramId, string $fileId, string $type, ?int $duration): ?int {
    $fileInfo = telegramApi('getFile', ['file_id' => $fileId]);
    if (!$fileInfo || !isset($fileInfo['file_path'])) {
        error_log('Telegram Webhook: getFile failed for telegram_id=' . $telegramId . '; file_id=' . $fileId . '; type=' . $type);
        return null;
    }

    $filePath = $fileInfo['file_path'];
    $downloadUrl = 'https://api.telegram.org/file/bot' . TELEGRAM_BOT_TOKEN . '/' . $filePath;

    $fileBytes = @file_get_contents($downloadUrl);
    if ($fileBytes === false) {
        error_log('Telegram Webhook: file download failed for telegram_id=' . $telegramId . '; path=' . $filePath . '; type=' . $type);
        return null;
    }

    $fileHash = md5($fileBytes);

    $stmt = $pdo->prepare('SELECT id FROM videos WHERE file_hash = :h');
    $stmt->execute([':h' => $fileHash]);
    if ($stmt->fetchColumn()) {
        error_log('Telegram Webhook: duplicate media skipped for telegram_id=' . $telegramId . '; hash=' . $fileHash);
        return null;
    }

    if (!is_dir(MEDIA_DIR)) {
        mkdir(MEDIA_DIR, 0777, true);
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!$ext) {
        $ext = $type === 'video' ? 'mp4' : 'jpg';
    }

    $filename = $type . '_' . time() . '_' . $telegramId . '_' . $fileHash . '.' . $ext;
    $localPath = MEDIA_DIR . $filename;

    if (@file_put_contents($localPath, $fileBytes) === false) {
        error_log('Telegram Webhook: failed to write media file for telegram_id=' . $telegramId . '; local_path=' . $localPath);
        return null;
    }

    $stmt = $pdo->prepare('INSERT INTO videos (telegram_id, file_hash, filename, file_type, description, duration, status, created_at) VALUES (:telegram_id, :file_hash, :filename, :file_type, NULL, :duration, "pending", NOW())');

    $stmt->execute([
        ':telegram_id' => $telegramId,
        ':file_hash' => $fileHash,
        ':filename' => $filename,
        ':file_type' => $type,
        ':duration' => $duration
    ]);

    $videoId = (int)$pdo->lastInsertId();

    logUserAction($pdo, $telegramId, 'upload', $videoId, null, 5);

    return $videoId;
}

function telegramApi(string $method, array $params): ?array {
    $url = TELEGRAM_API_URL . $method;

    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($params),
                'timeout' => 10
            ]
        ]);
        $raw = @file_get_contents($url, false, $context);
    }

    if (!$raw) {
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || !($json['ok'] ?? false)) {
        return null;
    }

    return $json['result'] ?? null;
}

function sendTelegramMessage($chatId, string $text): void {
    telegramApi('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ]);
}

function logUserAction(PDO $pdo, int $telegramId, string $actionType, ?int $videoId = null, ?int $targetTelegramId = null, int $points = 0): void {
    $fingerprint = md5((string)($_SERVER['HTTP_USER_AGENT'] ?? '') . (string)($_SERVER['REMOTE_ADDR'] ?? ''));

    $stmt = $pdo->prepare('INSERT INTO user_actions (telegram_id, action_type, video_id, target_telegram_id, ip_address, user_agent, fingerprint, points_earned, created_at) VALUES (:telegram_id, :action_type, :video_id, :target_telegram_id, :ip_address, :user_agent, :fingerprint, :points, NOW())');

    $stmt->execute([
        ':telegram_id' => $telegramId,
        ':action_type' => $actionType,
        ':video_id' => $videoId,
        ':target_telegram_id' => $targetTelegramId,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':fingerprint' => $fingerprint,
        ':points' => $points
    ]);

    if ($points !== 0) {
        $stmt = $pdo->prepare('UPDATE authors SET reputation_score = reputation_score + :points, updated_at = NOW() WHERE telegram_id = :telegram_id');
        $stmt->execute([':points' => $points, ':telegram_id' => $telegramId]);
    }
}

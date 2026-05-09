<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();

if (empty($_SESSION['telegram_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['author_telegram_id']) || !is_numeric($input['author_telegram_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$subscriberId = (int)$_SESSION['telegram_id'];
$authorId = (int)$input['author_telegram_id'];

if ($authorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid author']);
    exit;
}

if ($authorId === $subscriberId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot subscribe to self']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Ensure subscriptions table exists (some DBs may be missing migrations)
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS subscriptions (\n"
            . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "  subscriber_telegram_id BIGINT NOT NULL,\n"
            . "  author_telegram_id BIGINT NOT NULL,\n"
            . "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "  UNIQUE KEY unique_subscription (subscriber_telegram_id, author_telegram_id),\n"
            . "  INDEX idx_subscriber (subscriber_telegram_id),\n"
            . "  INDEX idx_author (author_telegram_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Не удалось создать таблицу subscriptions: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // subscriptions.author_telegram_id имеет FK на authors.telegram_id
    // Если автора еще нет в таблице authors (например, старые записи/импорт), подписка будет падать.
    $stmt = $pdo->prepare('SELECT telegram_id FROM authors WHERE telegram_id = :tid');
    $stmt->execute([':tid' => $authorId]);
    $authorExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$authorExists) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO authors (telegram_id, username, first_name, last_name, avatar_url, reputation_score, created_at) VALUES (:tid, NULL, NULL, NULL, NULL, 10, NOW())');
        $stmt->execute([':tid' => $authorId]);
    }

    $stmt = $pdo->prepare('SELECT id FROM subscriptions WHERE subscriber_telegram_id = :s AND author_telegram_id = :a');
    $stmt->execute([':s' => $subscriberId, ':a' => $authorId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare('DELETE FROM subscriptions WHERE subscriber_telegram_id = :s AND author_telegram_id = :a');
        $stmt->execute([':s' => $subscriberId, ':a' => $authorId]);
        echo json_encode(['success' => true, 'subscribed' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO subscriptions (subscriber_telegram_id, author_telegram_id, created_at) '
        . 'VALUES (:s, :a, NOW())'
    );
    $stmt->execute([':s' => $subscriberId, ':a' => $authorId]);

    echo json_encode(['success' => true, 'subscribed' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

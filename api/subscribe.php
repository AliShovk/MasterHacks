<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$pdo = getDatabaseConnection();
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['subscriber_telegram_id']) || !isset($input['author_telegram_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$subscriberId = (int)$input['subscriber_telegram_id'];
$authorId = (int)$input['author_telegram_id'];

try {
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO subscriptions (subscriber_telegram_id, author_telegram_id, created_at) '
        . 'VALUES (:s, :a, NOW())'
    );
    $stmt->execute([':s' => $subscriberId, ':a' => $authorId]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

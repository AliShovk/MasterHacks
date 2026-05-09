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

if (!is_array($input) || !isset($input['telegram_id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$telegramId = (int)$input['telegram_id'];
$action = (string)$input['action'];
$videoId = isset($input['video_id']) ? (int)$input['video_id'] : null;

$pointsMap = [
    'upload' => 5,
    'like_received' => 3,
    'view' => 0,
    'share' => 5,
    'comment' => 2,
    'milestone_1000' => 50,
    'milestone_10000' => 100,
    'report' => -10,
    'spam' => -20
];

$points = (int)($pointsMap[$action] ?? 0);

try {
    $fingerprint = md5((string)($_SERVER['HTTP_USER_AGENT'] ?? '') . (string)($_SERVER['REMOTE_ADDR'] ?? ''));

    $stmt = $pdo->prepare(
        'INSERT INTO user_actions (telegram_id, action_type, video_id, target_telegram_id, ip_address, user_agent, fingerprint, points_earned, created_at) '
        . 'VALUES (:telegram_id, :action_type, :video_id, NULL, :ip_address, :user_agent, :fingerprint, :points, NOW())'
    );

    $stmt->execute([
        ':telegram_id' => $telegramId,
        ':action_type' => $action,
        ':video_id' => $videoId,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':fingerprint' => $fingerprint,
        ':points' => $points
    ]);

    if ($points !== 0) {
        $stmt = $pdo->prepare(
            'UPDATE authors '
            . 'SET reputation_score = GREATEST(0, reputation_score + :points), updated_at = NOW() '
            . 'WHERE telegram_id = :telegram_id'
        );
        $stmt->execute([':points' => $points, ':telegram_id' => $telegramId]);
    }

    echo json_encode(['success' => true, 'points' => $points], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

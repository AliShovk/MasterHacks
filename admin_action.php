<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_start();
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    $data = json_decode(file_get_contents('php://input'), true);
    $response = ['success' => false];

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $action = (string)($data['action'] ?? '');

    if (($action === 'approve' || $action === 'reject') && isset($data['video_id'])) {
        $video_id = (int)$data['video_id'];
        if ($video_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid video_id']);
            exit;
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE videos SET status = 'approved', published_at = NOW() WHERE id = ?");
            $stmt->execute([$video_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE videos SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$video_id]);
        }

        $updated = (int)$stmt->rowCount();
        if ($updated <= 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Video not found or not changed']);
            exit;
        }

        echo json_encode(['success' => true, 'updated' => $updated]);
        exit;
    }

    if ($action === 'delete_old') {
        $stmt = $pdo->prepare("DELETE FROM videos WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND status != 'approved'");
        $stmt->execute();
        echo json_encode(['success' => true, 'deleted' => (int)$stmt->rowCount()]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

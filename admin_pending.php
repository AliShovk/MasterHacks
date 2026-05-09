<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_start();
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pdo = getDatabaseConnection();

$stmt = $pdo->query(
    "SELECT v.id, v.filename, v.file_type, v.created_at, a.first_name as author_name, a.username as author_username\n"
    . "FROM videos v\n"
    . "LEFT JOIN authors a ON a.telegram_id = v.telegram_id\n"
    . "WHERE v.status = 'pending'\n"
    . "ORDER BY v.created_at DESC"
);

$videos = $stmt->fetchAll();

echo json_encode($videos);

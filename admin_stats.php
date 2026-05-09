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

$stats = [
    'total_videos' => 0,
    'pending_videos' => 0,
    'approved_videos' => 0,
    'rejected_videos' => 0
];

$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM videos GROUP BY status");
$results = $stmt->fetchAll();

foreach ($results as $row) {
    $stats['total_videos'] += (int)$row['count'];
    if ($row['status'] === 'pending') {
        $stats['pending_videos'] = (int)$row['count'];
    } elseif ($row['status'] === 'approved') {
        $stats['approved_videos'] = (int)$row['count'];
    } elseif ($row['status'] === 'rejected') {
        $stats['rejected_videos'] = (int)$row['count'];
    }
}

echo json_encode($stats);

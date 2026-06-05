<?php
/**
 * youtube_crosspost.php — Trigger YouTube cross-posting in background
 * 
 * Usage:
 *   GET /youtube_crosspost.php?video_id=164&limit=5
 */

header('Content-Type: application/json; charset=utf-8');

$videoId = isset($_GET['video_id']) ? (int)$_GET['video_id'] : null;
$limit   = isset($_GET['limit']) ? max(1, min(10, (int)$_GET['limit'])) : 5;
$dryRun  = isset($_GET['dry_run']);

$script = __DIR__ . '/youtube_upload.py';
if (!file_exists($script)) {
    echo json_encode(['success' => false, 'error' => 'upload script not found']);
    exit;
}

$logFile = __DIR__ . '/data/logs/youtube_crosspost.log';
@mkdir(dirname($logFile), 0777, true);

$cmd = 'python3 ' . escapeshellarg($script);
if ($videoId) {
    $cmd .= ' --video-id ' . (int)$videoId;
} else {
    $cmd .= ' --limit ' . $limit;
}
if ($dryRun) {
    $cmd .= ' --dry-run';
}

// Run in background, output to log
$cmd = 'nohup ' . $cmd . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

exec($cmd);

echo json_encode([
    'success' => true,
    'message' => $dryRun ? 'Dry run started' : 'Cross-posting started in background',
    'limit' => $limit,
    'video_id' => $videoId,
    'dry_run' => $dryRun,
    'log' => str_replace(__DIR__, '', $logFile)
], JSON_UNESCAPED_UNICODE);

<?php
/**
 * Скрипт для очистки битых видео из ленты
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

function cleanBrokenVideos() {
    $mediaDir = __DIR__ . '/media/';
    $pdo = getDatabaseConnection();
    
    // Получаем все видео из БД
    $stmt = $pdo->query("SELECT id, filename FROM videos WHERE status = 'approved'");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $broken = [];
    $fixed = 0;
    
    foreach ($videos as $video) {
        $filename = $video['filename'];
        $filepath = $mediaDir . $filename;
        
        // Проверяем существование файла
        if (!file_exists($filepath)) {
            $broken[] = [
                'id' => $video['id'],
                'filename' => $filename,
                'reason' => 'file_not_found'
            ];
            continue;
        }
        
        // Проверяем размер файла
        if (filesize($filepath) < 1024) {
            $broken[] = [
                'id' => $video['id'],
                'filename' => $filename,
                'reason' => 'file_too_small'
            ];
            continue;
        }
        
        // Проверяем целостность MP4 файла
        if (preg_match('/\.mp4$/i', $filename)) {
            $full_path = escapeshellarg($filepath);
            $probe = shell_exec("ffprobe -v error -show_format $full_path 2>&1");
            if (strpos($probe, '[FORMAT]') === false) {
                $broken[] = [
                    'id' => $video['id'],
                    'filename' => $filename,
                    'reason' => 'corrupted_mp4'
                ];
                continue;
            }
        }
    }
    
    // Удаляем или помечаем битые видео
    foreach ($broken as $video) {
        $stmt = $pdo->prepare("UPDATE videos SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$video['id']]);
        $fixed++;
    }
    
    // Очищаем кэш
    $cacheFile = __DIR__ . '/data/cache/posts_cache.json';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
    
    return [
        'total_checked' => count($videos),
        'broken_found' => count($broken),
        'fixed' => $fixed,
        'broken_videos' => $broken
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = cleanBrokenVideos();
        echo json_encode([
            'success' => true,
            'message' => "Проверено {$result['total_checked']} видео, найдено {$result['broken_found']} битых, исправлено {$result['fixed']}",
            'details' => $result
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode(['error' => 'Method not allowed']);
}
?>

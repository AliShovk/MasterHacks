<?php
// SIMPLE_SCAN.php - Простой сканер без сложной логики
$media_dir = 'media/';
$posts_file = 'data/posts.json';

echo "<h2>🔍 Простое сканирование папки media</h2>";

// Создаем необходимые папки
if (!file_exists('data')) mkdir('data', 0777, true);
if (!file_exists('data/comments')) mkdir('data/comments', 0777, true);
if (!file_exists('data/cache')) mkdir('data/cache', 0777, true);

// Получаем все файлы
$files = scandir($media_dir);
$all_files = [];

foreach ($files as $file) {
    if ($file == '.' || $file == '..') continue;
    
    $path = $media_dir . $file;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $type = 'image';
    } elseif (in_array($ext, ['mp4', 'avi', 'mov', 'mkv', 'webm'])) {
        $type = 'video';
    } else {
        continue;
    }
    
    $all_files[] = [
        'file' => $file,
        'type' => $type,
        'path' => $path
    ];
    
    echo "<p>✅ $type: $file</p>";
}

echo "<hr><h3>📊 Найдено файлов: " . count($all_files) . "</h3>";

// Загружаем существующие посты для сохранения лайков
$existing_posts = [];
if (file_exists($posts_file)) {
    $existing_posts = json_decode(file_get_contents($posts_file), true) ?: [];
}

// Создаем новые посты
$posts = [];
$next_id = 1;

// Сначала добавляем существующие посты (чтобы сохранить лайки и комментарии)
$existing_files = [];
foreach ($existing_posts as $post) {
    $posts[] = $post;
    $existing_files[] = $post['filename'];
    $next_id = max($next_id, $post['id'] + 1);
}

// Затем добавляем новые файлы
foreach ($all_files as $file_data) {
    $filename = $file_data['file'];
    
    // Если файл уже есть в постах, пропускаем
    if (in_array($filename, $existing_files)) {
        continue;
    }
    
    // Добавляем новый пост
    $posts[] = [
        'id' => $next_id++,
        'filename' => $filename,
        'type' => $file_data['type'],
        'likes' => 0,
        'comments_count' => 0,
        'date' => date('Y-m-d H:i:s', filemtime($file_data['path'])),
        'user_liked' => false
    ];
    
    echo "<p style='color:green'>🆕 Добавлен новый: $filename</p>";
}

// Сортируем по дате (новые сначала)
usort($posts, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Переиндексируем ID
foreach ($posts as $index => $post) {
    $posts[$index]['id'] = $index + 1;
}

// Сохраняем
file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Очищаем кеш
$cache_file = 'data/cache/posts_cache.json';
if (file_exists($cache_file)) unlink($cache_file);

echo "<div style='background:#4CAF50;color:white;padding:20px;border-radius:10px;margin:20px 0;'>";
echo "<h2>✅ СКАНИРОВАНИЕ ЗАВЕРШЕНО!</h2>";
echo "<p>📊 Всего записей в базе: " . count($posts) . "</p>";
echo "</div>";

echo "<script>
    setTimeout(function() {
        window.location.href = 'index.php?force_refresh=1';
    }, 3000);
</script>";
?>
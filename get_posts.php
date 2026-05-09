<?php
/**
 * API для получения постов с пагинацией и кэшированием
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60'); // Кэширование на 1 минуту

// Только GET запросы
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Валидация параметра page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, min($page, 1000)); // Ограничение от 1 до 1000

$posts_per_page = 10;
$cache_file = 'data/cache/posts_cache.json';
$cache_time = 300; // 5 минут
$posts_file = 'data/posts.json';

$media_dir = __DIR__ . '/media/';
$min_media_bytes = 1024;

function isLikelyValidMp4($path) {
    if (!is_file($path)) {
        return false;
    }

    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }

    $head = @fread($fh, 64);
    @fclose($fh);
    if (!is_string($head) || strlen($head) < 12) {
        return false;
    }

    if (substr($head, 4, 4) !== 'ftyp') {
        return false;
    }

    $size = @filesize($path);
    if (!is_int($size) || $size <= 0) {
        return false;
    }

    $tail_size = (int)min(1024 * 1024, $size);
    $fh2 = @fopen($path, 'rb');
    if (!$fh2) {
        return false;
    }
    @fseek($fh2, -$tail_size, SEEK_END);
    $tail = @fread($fh2, $tail_size);
    @fclose($fh2);

    if (!is_string($tail) || strpos($tail, 'moov') === false) {
        return false;
    }

    return true;
}

function isProgressiveMp4($path) {
    if (!is_file($path)) {
        return false;
    }

    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }

    $head = @fread($fh, 1024 * 1024);
    @fclose($fh);

    if (!is_string($head) || $head === '') {
        return false;
    }

    return (strpos($head, 'moov') !== false);
}

// Альтернативный источник: MySQL (videos/authors)
$use_db = isset($_GET['db']) && $_GET['db'] == '1' && file_exists(__DIR__ . '/config/database.php');

if ($use_db) {
    require_once __DIR__ . '/config/database.php';
    $pdo = getDatabaseConnection();

    $offset = ($page - 1) * $posts_per_page;

    $stmt = $pdo->prepare(
        "SELECT 
            v.id,
            v.filename,
            v.file_type,
            v.likes,
            v.comments_count,
            COALESCE(v.published_at, v.created_at) AS date,
            a.id AS author_id,
            a.telegram_id AS author_telegram_id,
            a.username AS author_username,
            a.first_name AS author_first_name,
            a.last_name AS author_last_name,
            a.avatar_url AS author_avatar_url,
            a.is_verified AS author_verified,
            a.reputation_score AS author_reputation
        FROM videos v
        LEFT JOIN authors a ON a.telegram_id = v.telegram_id
        WHERE v.status = 'approved'
        ORDER BY COALESCE(v.published_at, v.created_at) DESC
        LIMIT :limit OFFSET :offset"
    );

    $stmt->bindValue(':limit', $posts_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = array_values(array_filter($rows, function($row) use ($media_dir, $min_media_bytes) {
        $filename = $row['filename'] ?? '';
        if (!$filename) {
            return false;
        }

        $path = $media_dir . $filename;
        if (!is_file($path) || filesize($path) < $min_media_bytes) {
            return false;
        }

        // Проверяем, что видео не битое (только для mp4)
        if (preg_match('/\.mp4$/i', $filename)) {
            if (!isLikelyValidMp4($path)) {
                return false;
            }

            if (!isProgressiveMp4($path)) {
                return false;
            }
        }

        return true;
    }));

    $safe_posts = array_map(function($row) {
        return [
            'id' => isset($row['id']) ? intval($row['id']) : 0,
            'filename' => isset($row['filename']) ? (string)$row['filename'] : '',
            'type' => isset($row['file_type']) ? (string)$row['file_type'] : 'video',
            'likes' => isset($row['likes']) ? intval($row['likes']) : 0,
            'comments_count' => isset($row['comments_count']) ? intval($row['comments_count']) : 0,
            'date' => isset($row['date']) ? (string)$row['date'] : '',
            'user_liked' => false,
            'author_id' => isset($row['author_id']) ? (int)$row['author_id'] : null,
            'author_telegram_id' => isset($row['author_telegram_id']) ? (int)$row['author_telegram_id'] : null,
            'author_username' => isset($row['author_username']) ? (string)$row['author_username'] : null,
            'author_first_name' => isset($row['author_first_name']) ? (string)$row['author_first_name'] : null,
            'author_last_name' => isset($row['author_last_name']) ? (string)$row['author_last_name'] : null,
            'author_avatar_url' => isset($row['author_avatar_url']) ? (string)$row['author_avatar_url'] : null,
            'author_verified' => isset($row['author_verified']) ? (bool)$row['author_verified'] : false,
            'author_reputation' => isset($row['author_reputation']) ? (int)$row['author_reputation'] : 0
        ];
    }, $rows);

    echo json_encode($safe_posts, JSON_UNESCAPED_UNICODE);
    exit;
}

// Функция для получения постов с кэшированием
function getCachedPosts($posts_file, $cache_file, $cache_time) {
    $media_dir = __DIR__ . '/media/';
    $min_media_bytes = 1024;

    // Проверяем директорию кэша
    $cache_dir = dirname($cache_file);
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // Проверяем кэш
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if (is_array($cached)) {
            $filtered = array_values(array_filter($cached, function($post) use ($media_dir, $min_media_bytes) {
                $filename = $post['filename'] ?? '';
                if (!$filename) {
                    return false;
                }

                $path = $media_dir . $filename;
                if (!is_file($path) || filesize($path) < $min_media_bytes) {
                    return false;
                }

                // Проверяем, что видео не битое (только для mp4)
                if (preg_match('/\.mp4$/i', $filename)) {
                    if (!isLikelyValidMp4($path)) {
                        // Просто пропускаем битое видео (в JSON-режиме не удаляем из БД)
                        return false;
                    }

                    if (!isProgressiveMp4($path)) {
                        return false;
                    }
                }

                return true;
            }));

            if (count($filtered) !== count($cached)) {
                file_put_contents($cache_file, json_encode($filtered, JSON_UNESCAPED_UNICODE));
            }

            return $filtered;
        }
    }
    
    // Читаем оригинальный файл
    if (!file_exists($posts_file)) {
        return [];
    }
    
    $posts = json_decode(file_get_contents($posts_file), true);
    if (!is_array($posts)) {
        return [];
    }
    
    // Сортируем по дате
    usort($posts, function($a, $b) {
        $dateA = isset($a['date']) ? strtotime($a['date']) : 0;
        $dateB = isset($b['date']) ? strtotime($b['date']) : 0;
        return $dateB - $dateA;
    });

    $posts = array_values(array_filter($posts, function($post) use ($media_dir, $min_media_bytes) {
        $filename = $post['filename'] ?? '';
        if (!$filename) {
            return false;
        }

        $path = $media_dir . $filename;
        if (!is_file($path) || filesize($path) < $min_media_bytes) {
            return false;
        }

        // Проверяем, что видео не битое (только для mp4)
        if (preg_match('/\.mp4$/i', $filename)) {
            if (!isLikelyValidMp4($path)) {
                return false;
            }

            if (!isProgressiveMp4($path)) {
                return false;
            }
        }

        return true;
    }));
    
    // Сохраняем в кэш
    file_put_contents($cache_file, json_encode($posts, JSON_UNESCAPED_UNICODE));
    
    return $posts;
}

// Получаем все посты
$all_posts = getCachedPosts($posts_file, $cache_file, $cache_time);
$total_posts = count($all_posts);
$total_pages = ceil($total_posts / $posts_per_page);

// Проверка выхода за пределы
if ($page > $total_pages && $total_pages > 0) {
    echo json_encode([]);
    exit;
}

// Получаем срез постов для текущей страницы
$start = ($page - 1) * $posts_per_page;
$posts = array_slice($all_posts, $start, $posts_per_page);

// Санитизируем данные перед выводом
$safe_posts = array_map(function($post) {
    return [
        'id' => isset($post['id']) ? intval($post['id']) : 0,
        'filename' => isset($post['filename']) ? (string)$post['filename'] : '',
        'type' => isset($post['type']) ? (string)$post['type'] : 'video',
        'likes' => isset($post['likes']) ? intval($post['likes']) : 0,
        'comments_count' => isset($post['comments_count']) ? intval($post['comments_count']) : 0,
        'date' => isset($post['date']) ? (string)$post['date'] : '',
        'user_liked' => isset($post['user_liked']) ? (bool)$post['user_liked'] : false
    ];
}, $posts);

echo json_encode($safe_posts, JSON_UNESCAPED_UNICODE);
?>

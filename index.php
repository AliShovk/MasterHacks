<?php
// Вертикальная лента TikTok-стиль v3.3 с паузой видео и бесконечным скроллом

session_start();

if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
}

if (!defined('USE_DB') && file_exists(__DIR__ . '/config/database.php')) {
    define('USE_DB', true);
}

if (defined('ADMIN_PANEL_KEY') && ADMIN_PANEL_KEY && isset($_GET['admin_key'])) {
    if (hash_equals((string)ADMIN_PANEL_KEY, (string)$_GET['admin_key'])) {
        $_SESSION['is_admin'] = true;
    }
}

if (isset($_GET['debug_db']) && !empty($_SESSION['is_admin'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    try {
        if (!file_exists(__DIR__ . '/config/database.php')) {
            echo json_encode(['success' => false, 'error' => 'No database config'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        require_once __DIR__ . '/config/database.php';
        $pdo = getDatabaseConnection();

        $dbName = null;
        $conn = null;
        try { $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn(); } catch (Throwable $e) { $dbName = null; }
        try { $conn = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS); } catch (Throwable $e) { $conn = null; }

        $rows = $pdo->query("SELECT status, COUNT(*) cnt FROM videos GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $counts = [];
        foreach ($rows as $r) {
            $k = (string)($r['status'] ?? '');
            $counts[$k] = (int)($r['cnt'] ?? 0);
        }

        echo json_encode([
            'success' => true,
            'database' => $dbName,
            'connection' => $conn,
            'counts' => $counts
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Fallback for '+' button (if index_db.php not used)
if (!defined('TELEGRAM_BOT_URL')) {
    define('TELEGRAM_BOT_URL', getenv('TELEGRAM_BOT_URL') ?: 'https://t.me/your_bot_username');
}

if (!defined('TELEGRAM_BOT_USERNAME')) {
    define('TELEGRAM_BOT_USERNAME', getenv('TELEGRAM_BOT_USERNAME') ?: 'your_bot_username');
}

// Обработчик обновления кэша
if (isset($_GET['refresh_cache'])) {
    $cache_file = 'data/cache/posts_cache.json';
    if (file_exists($cache_file)) {
        unlink($cache_file);
        echo 'success';
    } else {
        echo 'success';
    }
    exit;
}

// Обработчик проверки обновлений
if (isset($_GET['check_update'])) {
    $cache_file = 'data/cache/posts_cache.json';
    $posts_file = 'data/posts.json';
    
    if (file_exists($cache_file) && file_exists($posts_file)) {
        $cache_time = filemtime($cache_file);
        $posts_time = filemtime($posts_file);
        
        if ($posts_time > $cache_time) {
            // Возвращаем количество новых постов
            $old_posts = json_decode(file_get_contents($cache_file), true) ?? [];
            $new_posts = json_decode(file_get_contents($posts_file), true) ?? [];
            
            $new_count = count($new_posts) - count($old_posts);
            if ($new_count > 0) {
                echo 'new_posts:' . $new_count;
            } else {
                echo 'new_posts';
            }
            exit;
        }
    }
    echo 'no_updates';
    exit;
}

$cache_file = 'data/cache/posts_cache.json';
$cache_time = 60;
$posts_per_page = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$ajax_request = isset($_GET['ajax']);
$feed_type = isset($_GET['feed']) ? (string)$_GET['feed'] : 'all';
$feed_type = ($feed_type === 'subscriptions' && empty($_SESSION['telegram_id'])) ? 'all' : $feed_type;
$posts_file = 'data/posts.json';

if (isset($_GET['debug_feed']) && !empty($_SESSION['is_admin'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    try {
        $all = getPostsWithCache($posts_file, $cache_file, $cache_time, $feed_type);
        $lastErr = $GLOBALS['__feed_last_error'] ?? null;
        $total = is_array($all) ? count($all) : 0;
        $tp = $posts_per_page > 0 ? (int)ceil($total / $posts_per_page) : 0;
        $sample = [];
        if (is_array($all)) {
            $sampleRows = array_slice($all, 0, 5);
            foreach ($sampleRows as $p) {
                $sample[] = [
                    'id' => $p['id'] ?? null,
                    'filename' => $p['filename'] ?? null,
                    'type' => $p['type'] ?? null,
                    'date' => $p['date'] ?? null
                ];
            }
        }
        echo json_encode([
            'success' => true,
            'use_db' => (defined('USE_DB') && USE_DB === true),
            'feed_type' => $feed_type,
            'page' => $page,
            'posts_per_page' => $posts_per_page,
            'total_posts' => $total,
            'total_pages' => $tp,
            'last_error' => $lastErr,
            'sample' => $sample
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function getPostsWithCache($posts_file, $cache_file, $cache_time, $feed_type = 'all') {
    $mediaDir = __DIR__ . '/media/';
    $minMediaBytes = 1024;

    if (defined('USE_DB') && USE_DB === true && file_exists(__DIR__ . '/config/database.php')) {
        try {
            require_once __DIR__ . '/config/database.php';
            $pdo = getDatabaseConnection();

            $useSubscriptionsFeed = ($feed_type === 'subscriptions');
            $subscriberTelegramId = !empty($_SESSION['telegram_id']) ? (int)$_SESSION['telegram_id'] : 0;

            // If subscriptions table is missing, safely disable subscriptions feed
            $subscriptionsTableOk = true;
            try {
                $pdo->query("SELECT 1 FROM subscriptions LIMIT 1");
            } catch (Throwable $e) {
                $subscriptionsTableOk = false;
            }
            if ($useSubscriptionsFeed && !$subscriptionsTableOk) {
                $useSubscriptionsFeed = false;
                $feed_type = 'all';
            }

            if ($useSubscriptionsFeed && $subscriberTelegramId <= 0) {
                return [];
            }

            $sql = "SELECT 
                    v.id,
                    v.filename,
                    v.file_type AS type,
                    v.views,
                    v.likes,
                    v.comments_count,
                    COALESCE(v.published_at, v.created_at) AS date,
                    a.id AS author_id,
                    a.telegram_id AS author_telegram_id,
                    a.username AS author_username,
                    a.first_name AS author_first_name,
                    a.is_verified AS author_verified
                FROM videos v
                LEFT JOIN authors a ON a.telegram_id = v.telegram_id ";

            $params = [];
            if ($useSubscriptionsFeed) {
                $sql .= " INNER JOIN subscriptions s ON s.author_telegram_id = v.telegram_id AND s.subscriber_telegram_id = :sid ";
                $params[':sid'] = $subscriberTelegramId;
            }

            $sql .= " WHERE v.status = 'approved'
                ORDER BY COALESCE(v.published_at, v.created_at) DESC";

            $rows = [];
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $GLOBALS['__feed_last_error'] = $e->getMessage();

                $fallbackParams = [];
                $joinSubscriptions = '';
                if ($useSubscriptionsFeed) {
                    $joinSubscriptions = " INNER JOIN subscriptions s ON s.author_telegram_id = v.telegram_id AND s.subscriber_telegram_id = :sid ";
                    $fallbackParams[':sid'] = $subscriberTelegramId;
                }

                $fallbackSqls = [
                    // full videos columns (without authors)
                    "SELECT v.id, v.filename, v.file_type AS type, v.views, v.likes, v.comments_count, COALESCE(v.published_at, v.created_at) AS date, NULL AS author_id, v.telegram_id AS author_telegram_id, NULL AS author_username, NULL AS author_first_name, 0 AS author_verified FROM videos v{$joinSubscriptions} WHERE v.status = 'approved' ORDER BY COALESCE(v.published_at, v.created_at) DESC",
                    // minimal (no views/comments_count)
                    "SELECT v.id, v.filename, v.file_type AS type, v.likes, COALESCE(v.published_at, v.created_at) AS date, NULL AS author_id, v.telegram_id AS author_telegram_id, NULL AS author_username, NULL AS author_first_name, 0 AS author_verified FROM videos v{$joinSubscriptions} WHERE v.status = 'approved' ORDER BY COALESCE(v.published_at, v.created_at) DESC",
                    // super minimal
                    "SELECT v.id, v.filename, v.file_type AS type, COALESCE(v.published_at, v.created_at) AS date, NULL AS author_id, v.telegram_id AS author_telegram_id, NULL AS author_username, NULL AS author_first_name, 0 AS author_verified FROM videos v{$joinSubscriptions} WHERE v.status = 'approved' ORDER BY COALESCE(v.published_at, v.created_at) DESC"
                ];

                foreach ($fallbackSqls as $fsql) {
                    try {
                        $stmt = $pdo->prepare($fsql);
                        $stmt->execute($fallbackParams);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if ($rows) {
                            $GLOBALS['__feed_last_error'] = null;
                            break;
                        }
                    } catch (Throwable $e2) {
                        $GLOBALS['__feed_last_error'] = $e2->getMessage();
                        $rows = [];
                        continue;
                    }
                }
            }

            $posts = array_map(function($row) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'filename' => (string)($row['filename'] ?? ''),
                    'type' => (string)($row['type'] ?? 'video'),
                    'views' => (int)($row['views'] ?? 0),
                    'likes' => (int)($row['likes'] ?? 0),
                    'comments_count' => (int)($row['comments_count'] ?? 0),
                    'date' => (string)($row['date'] ?? date('Y-m-d H:i:s')),
                    'author_id' => isset($row['author_id']) ? (int)$row['author_id'] : null,
                    'author_telegram_id' => isset($row['author_telegram_id']) ? (int)$row['author_telegram_id'] : null,
                    'author_username' => $row['author_username'] ?? null,
                    'author_first_name' => $row['author_first_name'] ?? null,
                    'author_verified' => isset($row['author_verified']) ? (bool)$row['author_verified'] : false
                ];
            }, $rows);

            // Если join authors недоступен (fallback запросы), добираем имена авторов отдельным запросом.
            try {
                $need = [];
                foreach ($posts as $p) {
                    $tid = isset($p['author_telegram_id']) ? (int)$p['author_telegram_id'] : 0;
                    if ($tid > 0 && empty($p['author_username']) && empty($p['author_first_name'])) {
                        $need[$tid] = true;
                    }
                }

                if ($need) {
                    $ids = array_keys($need);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmtA = $pdo->prepare("SELECT telegram_id, username, first_name, is_verified FROM authors WHERE telegram_id IN ({$placeholders})");
                    $stmtA->execute($ids);
                    $authors = $stmtA->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $map = [];
                    foreach ($authors as $a) {
                        $map[(int)$a['telegram_id']] = $a;
                    }

                    foreach ($posts as &$p) {
                        $tid = isset($p['author_telegram_id']) ? (int)$p['author_telegram_id'] : 0;
                        if ($tid > 0 && isset($map[$tid])) {
                            if (empty($p['author_username'])) $p['author_username'] = $map[$tid]['username'] ?? null;
                            if (empty($p['author_first_name'])) $p['author_first_name'] = $map[$tid]['first_name'] ?? null;
                            if (empty($p['author_verified'])) $p['author_verified'] = !empty($map[$tid]['is_verified']);
                        }
                    }
                    unset($p);
                }
            } catch (Throwable $e) {
                // ignore
            }

            $posts = array_values(array_filter($posts, function($post) use ($mediaDir, $minMediaBytes) {
                $filename = $post['filename'] ?? '';
                if (!$filename) return false;
                $path = $mediaDir . $filename;
                return is_file($path) && filesize($path) >= $minMediaBytes;
            }));

            return $posts;
        } catch (Throwable $e) {
            $GLOBALS['__feed_last_error'] = $e->getMessage();
            return [];
        }
    }

    if (!file_exists($posts_file)) return [];

    if (file_exists($cache_file) && time() - filemtime($cache_file) < $cache_time) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if (is_array($cached)) {
            $filtered = array_values(array_filter($cached, function($post) use ($mediaDir, $minMediaBytes) {
                $filename = $post['filename'] ?? '';
                if (!$filename) return false;
                $path = $mediaDir . $filename;
                return is_file($path) && filesize($path) >= $minMediaBytes;
            }));

            if (count($filtered) !== count($cached)) {
                file_put_contents($cache_file, json_encode($filtered, JSON_UNESCAPED_UNICODE));
            }

            return $filtered;
        }
    }

    $posts = json_decode(file_get_contents($posts_file), true) ?? [];
    usort($posts, function($a, $b) {
        $dateA = isset($a['date']) ? strtotime($a['date']) : 0;
        $dateB = isset($b['date']) ? strtotime($b['date']) : 0;
        return $dateB - $dateA;
    });

    $posts = array_values(array_filter($posts, function($post) use ($mediaDir, $minMediaBytes) {
        $filename = $post['filename'] ?? '';
        if (!$filename) return false;
        $path = $mediaDir . $filename;
        return is_file($path) && filesize($path) >= $minMediaBytes;
    }));

    file_put_contents($cache_file, json_encode($posts, JSON_UNESCAPED_UNICODE));
    return $posts;
}

function sanitize($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function isAdmin() {
    return !empty($_SESSION['is_admin']);
}

function renderPost($post, $index, $subscribedAuthors = []) {
    $sanitizedId = sanitize($post['id']);
    $sanitizedType = sanitize($post['type']);
    $sanitizedFilename = sanitize($post['filename']);
    $postDate = date('d.m.Y', strtotime($post['date']));
    $postDescription = sanitize(pathinfo($post['filename'], PATHINFO_FILENAME));
    if (function_exists('mb_strlen') && mb_strlen($postDescription) > 20) {
        $postDescription = mb_substr($postDescription, 0, 20) . '…';
    } elseif (strlen($postDescription) > 20) {
        $postDescription = substr($postDescription, 0, 20) . '…';
    }
    $likes = intval($post['likes'] ?? 0);
    $commentsCount = intval($post['comments_count'] ?? 0);
    $authorTelegramId = isset($post['author_telegram_id']) ? (int)$post['author_telegram_id'] : 0;
    $viewerTelegramId = !empty($_SESSION['telegram_id']) ? (int)$_SESSION['telegram_id'] : 0;
    $canSubscribe = ($viewerTelegramId > 0 && $authorTelegramId > 0 && $authorTelegramId !== $viewerTelegramId);
    $isSubscribed = $canSubscribe && in_array($authorTelegramId, $subscribedAuthors, true);
    $postData = json_encode(['id' => $post['id'], 'filename' => $post['filename'], 'type' => $post['type'], 'date' => $post['date']], JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div class="post" data-id="<?= $sanitizedId ?>" data-index="<?= $index ?>" data-type="<?= $sanitizedType ?>">
        <div class="media-placeholder" id="placeholder-<?= $sanitizedId ?>">
            <div class="loader-icon"></div>
        </div>
        
        <i class="fas fa-heart double-tap-heart" id="heart-<?= $sanitizedId ?>"></i>
        
        <div class="media-container">
            <?php if($post['type'] == 'image'): ?>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100'%3E%3C/svg%3E" 
                     data-src="media/<?= $sanitizedFilename ?>" 
                     alt="" 
                     class="lazy-media"
                     onload="mediaLoaded(this, '<?= $sanitizedId ?>')"
                     onerror="mediaError(this, '<?= $sanitizedId ?>')">
            <?php else: ?>
                <video class="lazy-media"
                       data-src="media/<?= $sanitizedFilename ?>"
                       preload="auto"
                       playsinline
                       muted
                       loop
                       data-post-id="<?= $sanitizedId ?>"
                       oncanplay="videoCanPlay(this, '<?= $sanitizedId ?>')"
                       onerror="videoError(this, '<?= $sanitizedId ?>')">
                </video>
            <?php endif; ?>
        </div>
        
        <?php if($post['type'] == 'video'): ?>
        <div class="sound-controls">
            <button class="sound-toggle" id="sound-<?= $sanitizedId ?>" onclick="toggleSound(this, '<?= $sanitizedId ?>')" title="Звук">
                <i class="fas fa-volume-mute"></i>
            </button>
            <button class="pause-toggle" id="pauseBtn-<?= $sanitizedId ?>" onclick="togglePause('<?= $sanitizedId ?>')" title="Пауза">
                <i class="fas fa-pause"></i>
            </button>
        </div>
        <?php endif; ?>
        
        <div class="post-info">
            <div class="post-description">
                <?= $postDescription ?>
            </div>
            <div class="post-meta">
                <div class="post-author">
                    <div class="author-avatar">M</div>
                    <span>
                        <?php
                            $authorLabel = 'MasterHacks';
                            if (!empty($post['author_username'])) {
                                $authorLabel = '@' . sanitize($post['author_username']);
                            } elseif (!empty($post['author_first_name'])) {
                                $authorLabel = sanitize($post['author_first_name']);
                            }
                            if (!empty($post['author_id'])) {
                                $authorLabel .= ' #' . (int)$post['author_id'];
                            }

                            echo $authorLabel;

                            echo ' <i class="fas fa-user" style="color:#fc7b07; font-size: 12px;" title="Участник"></i>';
                        ?>
                    </span>
                </div>
                <?php if ($canSubscribe): ?>
                <button class="follow-btn<?= $isSubscribed ? ' subscribed' : '' ?>" type="button" onclick="event.stopPropagation(); toggleSubscribe(<?= (int)$authorTelegramId ?>, this)"><?= $isSubscribed ? 'Вы подписаны' : 'Подписаться' ?></button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="side-actions">
            <button class="action-btn like-btn" onclick="toggleLike('<?= $sanitizedId ?>', this)">
                <div class="action-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <span class="action-count"><?= $likes ?></span>
            </button>
            <button class="action-btn comment-btn" onclick="openComments('<?= $sanitizedId ?>')">
                <div class="action-icon">
                    <i class="fas fa-comment"></i>
                </div>
                <span class="action-count"><?= $commentsCount ?></span>
            </button>
            <button class="action-btn views-btn" type="button" onclick="return false;" aria-label="Просмотры">
                <div class="action-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <span class="action-count" id="viewsCount-<?= $sanitizedId ?>"><?= isset($post['views']) ? (int)$post['views'] : 0 ?></span>
            </button>
            <button class="action-btn bookmark-btn" onclick="toggleBookmark('<?= $sanitizedId ?>', this)" data-post='<?= $postData ?>'>
                <div class="action-icon">
                    <i class="fas fa-bookmark"></i>
                </div>
                <span class="action-count"></span>
            </button>
            <button class="action-btn share-btn" onclick="openShare('<?= $sanitizedId ?>', '<?= $sanitizedFilename ?>')">
                <div class="action-icon">
                    <i class="fas fa-share-alt"></i>
                </div>
                <span class="action-count"></span>
            </button>
            <a href="https://gaikavint.ru/" target="_blank" class="action-btn site-btn">
                <div class="action-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <span class="action-count">Shop</span>
            </a>
        </div>
    </div>
    <?php
}
$subscribedAuthors = [];
if (defined('USE_DB') && USE_DB === true && !empty($_SESSION['telegram_id']) && file_exists(__DIR__ . '/config/database.php')) {
    try {
        require_once __DIR__ . '/config/database.php';
        $pdo = getDatabaseConnection();
        try {
            $stmt = $pdo->prepare('SELECT author_telegram_id FROM subscriptions WHERE subscriber_telegram_id = :s');
            $stmt->execute([':s' => (int)$_SESSION['telegram_id']]);
            $subscribedAuthors = array_map(function($r) { return (int)$r['author_telegram_id']; }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable $e) {
            $subscribedAuthors = [];
        }
    } catch (Throwable $e) {
        $subscribedAuthors = [];
    }
}

$all_posts = getPostsWithCache($posts_file, $cache_file, $cache_time, $feed_type);
$total_posts = count($all_posts);
$total_pages = ceil($total_posts / $posts_per_page);
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$start = ($page - 1) * $posts_per_page;
$posts = array_slice($all_posts, $start, $posts_per_page);

// Если это AJAX запрос, возвращаем только посты и кнопку
if ($ajax_request) {
    foreach($posts as $index => $post) {
        renderPost($post, $start + $index, $subscribedAuthors);
    }
    
    if ($page < $total_pages): ?>
    <div class="load-more-container" id="loadMoreContainer">
        <button class="load-more-btn" onclick="loadMorePosts()" id="loadMoreBtn">
            <i class="fas fa-sync-alt"></i> Загрузить еще
        </button>
    </div>
    <?php endif;
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>MasterHacks | Лента</title>
<meta name="description" content="Полезные видео для дома, хозяйства и строительства. Лайфхаки, DIY, ремонт своими руками. Смотрите короткие инструкции и советы от мастеров."><meta name="keywords" content="DIY, хозяйство, строительство, ремонт, своими руками, лайфхаки, дом, дача, мастер, видео, инструкция, совет"><meta property="og:title" content="MasterHacks - Полезные видео для дома и строительства"><meta property="og:description" content="Короткие видео-инструкции по ремонту, хозяйству и строительству. Лайфхаки для дома своими руками."><meta property="og:image" content="https://masterhacks.ru/gk.png"><meta property="og:url" content="https://masterhacks.ru"><meta property="og:type" content="website"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="MasterHacks - Полезные видео для дома"><meta name="twitter:description" content="Короткие видео-инструкции по ремонту и хозяйству. DIY своими руками."><meta name="twitter:image" content="https://masterhacks.ru/gk.png">
<link rel="icon" type="image/png" href="/gk.png">
<link rel="apple-touch-icon" href="/gk.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body { height: 100%; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #000; color: #fff; }
body { position: fixed; width: 100%; height: 100%; overflow: hidden; }
.feed-container { position: fixed; top: 0; left: 0; width: 100%; height: 100%; overflow-y: auto; scroll-snap-type: y mandatory; -webkit-overflow-scrolling: auto; scrollbar-width: none; }
.feed-container::-webkit-scrollbar { display: none; }
.post { position: relative; width: 100%; height: 100vh; height: -webkit-fill-available; scroll-snap-align: start; scroll-snap-stop: always; overflow: hidden; background: #000; }
@supports (padding-top: env(safe-area-inset-top)) { .post { padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom); } }
.media-container { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
.intro-post{display:flex;align-items:center;justify-content:center;padding:calc(88px + env(safe-area-inset-top, 0)) 18px calc(24px + env(safe-area-inset-bottom, 0));background:radial-gradient(circle at top, rgba(252,123,7,.30), rgba(0,0,0,.92) 42%, #000 100%)}
.intro-card{width:min(100%,560px);background:linear-gradient(180deg, rgba(18,18,18,.92), rgba(9,9,9,.96));border:1px solid rgba(255,255,255,.10);border-radius:24px;padding:22px 18px;box-shadow:0 24px 60px rgba(0,0,0,.45);backdrop-filter:blur(10px)}
.intro-badge{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:rgba(252,123,7,.18);border:1px solid rgba(252,123,7,.35);color:#ffd2ad;font-size:12px;font-weight:700;letter-spacing:.02em;text-transform:uppercase}
.intro-title{margin-top:14px;font-size:32px;line-height:1.05;font-weight:800;color:#fff}
.intro-subtitle{margin-top:12px;font-size:15px;line-height:1.55;color:rgba(255,255,255,.82)}
.intro-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.intro-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 16px;border-radius:999px;text-decoration:none;font-weight:700;font-size:14px;border:1px solid rgba(255,255,255,.14);transition:transform .18s ease, background .18s ease, border-color .18s ease;color:#fff}
.intro-btn:hover{transform:translateY(-1px)}
.intro-btn-primary{background:linear-gradient(135deg, #fc7b07, #ff9f43);border-color:rgba(252,123,7,.45);color:#fff}
.intro-btn-secondary{background:rgba(255,255,255,.06)}
.intro-section{margin-top:18px;padding:16px;border-radius:18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}
.intro-section-title{font-size:13px;font-weight:800;color:#fff;text-transform:uppercase;letter-spacing:.04em}
.intro-section-text{margin-top:8px;font-size:14px;line-height:1.55;color:rgba(255,255,255,.78)}
.intro-steps{display:grid;gap:10px;margin-top:12px}
.intro-step{display:flex;gap:12px;align-items:flex-start;padding:12px;border-radius:16px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06)}
.intro-step-num{flex:0 0 28px;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(252,123,7,.22);border:1px solid rgba(252,123,7,.35);color:#ffb066;font-size:13px;font-weight:800}
.intro-step-title{font-size:14px;font-weight:700;color:#fff}
.intro-step-text{margin-top:4px;font-size:13px;line-height:1.45;color:rgba(255,255,255,.72)}
.intro-card{max-height:calc(100vh - 120px);overflow:auto;scrollbar-width:none}
.intro-card::-webkit-scrollbar{display:none}
.media-container img, .media-container video { width: 100%; height: 100%; object-fit: cover; }
.media-placeholder { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #111; display: flex; align-items: center; justify-content: center; z-index: 2; }
.media-placeholder .loader-icon { width: 30px; height: 30px; border: 2px solid rgba(252, 123, 7, 0.3); border-top-color: #fc7b07; border-radius: 50%; animation: spin 1s linear infinite; }
.pause-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.3); display: flex; align-items: center; justify-content: center; z-index: 5; opacity: 0; transition: opacity 0.3s ease; pointer-events: none; }
.pause-overlay.show { opacity: 1; pointer-events: auto; }
.pause-icon { width: 70px; height: 70px; background: rgba(252, 123, 7, 0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; transform: scale(0.8); transition: transform 0.3s ease; }
.pause-overlay.show .pause-icon { transform: scale(1); }
.video-paused .pause-overlay { opacity: 1; }
.side-actions { position: absolute; right: 10px; bottom: 70px; display: flex; flex-direction: column; align-items: center; gap: 8px; z-index: 10; }
.action-btn { display: flex; flex-direction: column; align-items: center; background: none; border: none; cursor: pointer; width: 45px; padding: 0; }
.action-icon { width: 38px; height: 38px; border-radius: 50%; background: rgba(252, 123, 7, 0.85); border: 1px solid rgba(255, 255, 255, 0.3); color: white; font-size: 16px; margin-bottom: 3px; opacity: .7; display:flex; align-items:center; justify-content:center; line-height:1; }
.action-icon i { display:block; line-height:1; }
.action-count { font-size: 10px; font-weight: 600; color: white; background: rgba(0, 0, 0, 0.5); padding: 3px 8px; border-radius: 999px; min-width: 20px; text-align: center; opacity: .7; border: 1px solid rgba(255, 255, 255, 0.18); }
.action-btn:hover .action-icon,.action-btn:hover .action-count{opacity:1}

.like-btn .action-icon{background:rgba(220,53,69,.85)}
.site-btn .action-icon{background:rgba(40,167,69,.85)}
.views-btn .action-icon{background:rgba(13,110,253,.85)}
.follow-btn{border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.08);color:#fff;border-radius:999px;padding:5px 10px;font-size:11px;font-weight:800;cursor:pointer;white-space:nowrap}
.follow-btn.subscribed{background:rgba(40,167,69,.18);border-color:rgba(40,167,69,.35);color:#28a745}
.feed-switch{display:flex;gap:6px;align-items:center}
.feed-btn{border:1px solid rgba(255,255,255,.18);background:rgba(0,0,0,.25);color:#fff;border-radius:999px;padding:0;width:34px;height:34px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;cursor:pointer;text-decoration:none;flex:0 0 auto}
.feed-btn.active{background:rgba(252,123,7,.18);border-color:rgba(252,123,7,.35);color:#fc7b07}
.tg-login-btn{background:rgba(34,158,217,.22)!important;border:1px solid rgba(34,158,217,.55)!important}
.tg-login-btn i{color:#22a2d9}
.top-nav { position: fixed; top: 0; left: 0; width: 100%; padding: 10px 12px; padding-top: calc(10px + env(safe-area-inset-top, 0)); display: flex; justify-content: space-between; align-items: center; z-index: 100; background: linear-gradient(to bottom, rgba(0,0,0,0.8), transparent); }
.logo-image { height: 50px; width: auto; }
.nav-actions { display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: calc(100vw - 140px); }
.nav-actions::-webkit-scrollbar { display: none; }
.nav-btn { width: 32px; height: 32px; border-radius: 50%; background: rgba(252, 123, 7, 0.8); border: 1px solid rgba(255, 255, 255, 0.2); color: white; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.nav-btn-add { width: 40px; height: 40px; font-size: 24px; font-weight: 900; text-decoration: none; flex-shrink: 0; background: rgba(40,167,69,.92); border: 1px solid rgba(40,167,69,.40); color: #fff; }
.tg-login-btn { width: 40px; height: 40px; border-radius: 50%; padding: 0; display: flex; align-items: center; justify-content: center; gap: 0; }
.tg-login-btn span { display: none; }
.tg-login-label{display:none}
.tg-login-wrap{display:flex;flex-direction:column;align-items:center;gap:4px}
.tg-login-wrap .tg-login-label{display:block;font-size:10px;line-height:1;color:rgba(255,255,255,.85);background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.18);padding:2px 6px;border-radius:999px}

.feed-side-panel{position:fixed;left:12px;top:86px;z-index:120;display:flex;flex-direction:column;gap:8px}
.feed-side-btn{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.18);color:#fff;cursor:pointer;text-decoration:none;backdrop-filter:blur(6px)}
.feed-side-btn.active{background:rgba(252,123,7,.20);border-color:rgba(252,123,7,.45);color:#fc7b07}
.post-info { position: absolute; bottom: 0; left: 0; width: 100%; padding: 12px; padding-bottom: calc(12px + env(safe-area-inset-bottom, 0)); background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); z-index: 5; }
.post-description { max-width: 80%; font-size: 14px; line-height: 1.3; margin-bottom: 6px; color: white; }
.post-meta { display: flex; align-items: center; gap: 8px; font-size: 12px; color: rgba(255, 255, 255, 0.8); }
.author-avatar { width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #fc7b07, #ff9d3a); display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; font-size: 11px; }
.sound-controls { position: absolute; bottom: 12px; right: 22px; z-index: 100; display: flex; gap: 16px; }
.sound-toggle { width: 46px; height: 46px; border-radius: 50%; background: rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.2); color: white; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.sound-toggle.unmuted { background: rgba(252, 123, 7, 0.9); }
.pause-toggle { width: 46px; height: 46px; border-radius: 50%; background: rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.2); color: white; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.pause-toggle.playing { background: rgba(252, 123, 7, 0.9); }
.post-date-right{position:absolute;right:56px;bottom:16px;z-index:101;font-size:11px;color:rgba(255,255,255,.85);background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.18);padding:3px 8px;border-radius:999px;backdrop-filter:blur(6px)}
.double-tap-heart { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0); font-size: 60px; color: #fc7b07; z-index: 100; pointer-events: none; opacity: 0; }
.double-tap-heart.animate { animation: doubleTapHeart 0.8s ease-out forwards; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.95); z-index: 1000; display: none; align-items: flex-start; justify-content: center; padding: env(safe-area-inset-top) 0 env(safe-area-inset-bottom) 0; }
.modal-content { background: rgba(30, 30, 30, 0.98); border-radius: 16px; width: 95%; max-width: 400px; max-height: 70vh; margin-top: 20px; margin-bottom: 20px; overflow: hidden; border: 1px solid rgba(252, 123, 7, 0.3); box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5); }
.modal-header { padding: 14px 16px; border-bottom: 1px solid rgba(252, 123, 7, 0.2); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: rgba(30, 30, 30, 0.98); z-index: 2; }
.modal-header h3 { color: #fc7b07; font-size: 16px; }
.close-modal { background: rgba(252, 123, 7, 0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.comments-container { max-height: calc(70vh - 140px); overflow-y: auto; padding: 12px 16px; }

/* My Videos modal: make it taller so more items are visible */
#myVideosModal .modal-content{max-width:520px;max-height:92vh;margin-top:10px;margin-bottom:10px}
#myVideosModal .comments-container{max-height:calc(92vh - 90px)}

/* Admin modal: make it taller and allow scrolling content */
#adminModal .modal-content{max-height:92vh}
#adminModal #adminPanelContent{max-height:calc(92vh - 70px);overflow-y:auto}
.pending-admin-list{max-height:calc(92vh - 170px);overflow-y:auto;padding-right:6px}
.ui-card{border:1px solid rgba(255,255,255,.12);border-radius:14px;background:rgba(255,255,255,.04);padding:12px}
.ui-row{display:flex;align-items:center;justify-content:space-between;gap:10px}
.ui-muted{color:rgba(255,255,255,.72)}
.ui-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;font-size:12px;color:rgba(255,255,255,.82)}
.ui-chip{display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.35);border-radius:999px;padding:4px 10px;font-size:12px}
.status-chip{border-color:rgba(252,123,7,.25)}
.status-chip.approved{border-color:rgba(40,167,69,.35);color:#28a745;background:rgba(40,167,69,.10)}
.status-chip.pending{border-color:rgba(255,193,7,.35);color:#ffc107;background:rgba(255,193,7,.10)}
.status-chip.rejected{border-color:rgba(220,53,69,.35);color:#dc3545;background:rgba(220,53,69,.10)}
.ui-actions{display:flex;gap:10px;margin-top:10px}
.ui-btn{border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);color:#fff;border-radius:12px;padding:10px 12px;font-size:12px;font-weight:900;cursor:pointer}
.ui-btn:active{transform:translateY(1px)}
.ui-btn.success{border-color:rgba(40,167,69,.45);background:rgba(40,167,69,.14);color:#bff3c6}
.ui-btn.danger{border-color:rgba(220,53,69,.45);background:rgba(220,53,69,.14);color:#ffb3bd}
.ui-btn.warning{border-color:rgba(255,193,7,.45);background:rgba(255,193,7,.12);color:#ffe08a}
.mv-grid{display:flex;flex-direction:column;gap:10px}
.mv-item{display:flex;gap:12px;align-items:flex-start}
.mv-thumb{width:84px;height:112px;border-radius:12px;overflow:hidden;background:#000;border:1px solid rgba(255,255,255,.10);flex:0 0 auto;position:relative}
.mv-thumb video,.mv-thumb img{width:100%;height:100%;object-fit:contain;display:block;background:#000}
.mv-right{flex:1;min-width:0}
.mv-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
.mv-title{font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mv-stats{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.mv-actions{display:flex;gap:8px;margin-top:10px;justify-content:flex-end}
.mv-delete{border:1px solid rgba(220,53,69,.45);background:rgba(220,53,69,.12);color:#ffb3bd;border-radius:12px;padding:6px 10px;font-size:11px;font-weight:900;cursor:pointer;flex:0 0 auto}
.mv-open{border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);color:#fff;border-radius:12px;padding:8px 12px;font-size:12px;font-weight:900;cursor:pointer;flex:0 0 auto}
.mv-totals{padding:0 16px 10px;font-size:12px;color:rgba(255,255,255,0.75);display:flex;flex-wrap:wrap;gap:8px}
.mv-total-chip{border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);border-radius:999px;padding:6px 10px;font-size:12px;font-weight:900;display:inline-flex;align-items:center;gap:6px}
.mv-total-chip.views{border-color:rgba(34,162,217,.45);background:rgba(34,162,217,.12);color:#aee8ff}
.mv-total-chip.likes{border-color:rgba(252,123,7,.45);background:rgba(252,123,7,.12);color:#ffd2ad}
.mv-total-chip.comments{border-color:rgba(155,89,182,.45);background:rgba(155,89,182,.12);color:#e6c6ff}

.action-btn.share-btn .action-icon{background:linear-gradient(135deg,#fc7b07,#ff2d55,#6f5eff,#22a2d9);border-color:rgba(255,255,255,.18)}
.action-btn.share-btn i{color:#fff}
.comment-item { display: flex; gap: 8px; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
.comment-avatar { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #fc7b07, #ff9d3a); flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 11px; }
.comment-content { flex: 1; min-width: 0; }
.comment-author { font-weight: bold; margin-bottom: 3px; color: white; font-size: 13px; }
.comment-text { color: rgba(255, 255, 255, 0.9); line-height: 1.3; margin-bottom: 3px; font-size: 12px; word-break: break-word; }
.comment-time { font-size: 10px; color: rgba(255, 255, 255, 0.6); }
.comment-form { padding: 12px 16px; border-top: 1px solid rgba(252, 123, 7, 0.2); display: flex; gap: 8px; position: sticky; bottom: 0; background: rgba(30, 30, 30, 0.98); z-index: 2; }
.comment-input { flex: 1; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(252, 123, 7, 0.3); border-radius: 18px; padding: 8px 14px; color: white; font-size: 12px; min-width: 0; }
.send-comment { width: 36px; height: 36px; border-radius: 50%; background: rgba(252, 123, 7, 0.9); border: none; color: white; font-size: 14px; cursor: pointer; flex-shrink: 0; }
.share-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; padding: 16px; }
.share-option { display: flex; flex-direction: column; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(252, 123, 7, 0.2); border-radius: 10px; padding: 12px 6px; color: white; cursor: pointer; }
.share-icon { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #fc7b07, #ff9d3a); display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; }
.share-name { font-size: 11px; font-weight: 500; text-align: center; }
.bookmarks-list { max-height: 60vh; overflow-y: auto; padding: 12px; }
.bookmark-item { display: flex; gap: 10px; padding: 10px; margin-bottom: 6px; background: rgba(255, 255, 255, 0.05); border-radius: 10px; cursor: pointer; }
.bookmark-thumb { width: 45px; height: 60px; background: #333; border-radius: 5px; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.bookmark-thumb img, .bookmark-thumb video { width: 100%; height: 100%; object-fit: contain; display: block; background: #000; }
.bookmark-info { flex: 1; min-width: 0; }
.bookmark-title { font-weight: 600; margin-bottom: 3px; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bookmark-date { font-size: 10px; color: rgba(255, 255, 255, 0.6); }
.bookmark-remove { background: rgba(255, 0, 0, 0.2); border: none; color: white; width: 26px; height: 26px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

/* Кнопка "Загрузить еще" */
.load-more-container { width: 100%; padding: 24px 20px 40px; text-align: center; scroll-snap-align: none; }
.load-more-status { display:inline-flex; align-items:center; justify-content:center; gap:8px; background: rgba(252, 123, 7, 0.08); border: 1px solid rgba(252, 123, 7, 0.3); color: #fc7b07; padding: 12px 18px; border-radius: 24px; font-size: 13px; font-weight: 500; letter-spacing: 0.3px; min-width: 180px; }
.load-more-status.loading { opacity: 0.9; }
.load-more-status i { font-size: 14px; }

@keyframes doubleTapHeart {
  0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
  25% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
  50% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
  100% { transform: translate(-50%, -50%) scale(1.3); opacity: 0; }
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-screen { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #000; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 9999; }
.loader { width: 35px; height: 35px; border: 2px solid rgba(252, 123, 7, 0.3); border-top-color: #fc7b07; border-radius: 50%; animation: spin 1s linear infinite; }
.toast { position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%) translateY(100px); background: rgba(252, 123, 7, 0.95); color: white; padding: 8px 16px; border-radius: 18px; font-size: 12px; z-index: 1000; opacity: 0; transition: all 0.3s ease; max-width: 90%; text-align: center; }
.toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
@media (max-width: 360px) {
  .side-actions { right: 8px; gap: 6px; bottom: 60px; }
  .action-icon { width: 34px; height: 34px; font-size: 14px; }
  .action-btn { width: 40px; }
  .action-count { font-size: 9px; }
  .modal-content { width: 98%; max-height: 65vh; }
  .comments-container { padding: 10px 12px; }
  .comment-form { padding: 10px 12px; }
  .load-more-status { padding: 10px 16px; font-size: 12px; min-width: 160px; }
  .top-nav { padding: 8px 10px; padding-top: calc(8px + env(safe-area-inset-top, 0)); }
  .logo-image { height: 40px; }
  .nav-actions { gap: 6px; max-width: calc(100vw - 110px); }
  .nav-btn { width: 30px; height: 30px; font-size: 13px; }
  .nav-btn-add { width: 36px; height: 36px; font-size: 22px; }
  .tg-login-btn { width: 30px; height: 30px; }
  .tg-login-wrap .tg-login-label{font-size:9px;padding:2px 6px}
  .feed-btn { width: 30px; height: 30px; font-size: 12px; }
  .feed-side-panel{left:10px;top:74px}
  .feed-side-btn{width:34px;height:34px;font-size:13px}
  .intro-post{padding:calc(80px + env(safe-area-inset-top, 0)) 14px calc(20px + env(safe-area-inset-bottom, 0))}
  .intro-card{padding:18px 14px;border-radius:20px}
  .intro-badge{font-size:11px;padding:6px 10px}
  .intro-title{font-size:26px}
  .intro-subtitle{font-size:14px}
  .intro-actions{flex-direction:column}
  .intro-btn{width:100%}
  .intro-section{margin-top:14px;padding:14px}
  .intro-steps{gap:8px;margin-top:10px}
  .intro-step{padding:10px;gap:10px}
  .intro-step-title{font-size:13px}
  .intro-step-text{font-size:12px;line-height:1.4}
}
@media (max-height: 600px) {
  .modal-content { max-height: 60vh; }
  .comments-container { max-height: calc(60vh - 130px); }
  .load-more-container { padding: 30px 20px; }
  .intro-post{align-items:flex-start;padding:calc(72px + env(safe-area-inset-top, 0)) 12px calc(12px + env(safe-area-inset-bottom, 0))}
  .intro-card{max-height:calc(100vh - 90px);padding:14px 12px;border-radius:18px}
  .intro-title{margin-top:10px;font-size:22px;line-height:1.08}
  .intro-subtitle{margin-top:8px;font-size:13px;line-height:1.4}
  .intro-actions{margin-top:12px;gap:8px}
  .intro-btn{min-height:40px;font-size:13px}
  .intro-section{margin-top:12px;padding:12px;border-radius:14px}
  .intro-section-title{font-size:12px}
  .intro-section-text{margin-top:6px;font-size:12px;line-height:1.4}
  .intro-steps{gap:7px;margin-top:8px}
  .intro-step{padding:9px;gap:9px;border-radius:12px}
  .intro-step-num{width:24px;height:24px;flex-basis:24px;font-size:12px}
  .intro-step-title{font-size:12px}
  .intro-step-text{margin-top:2px;font-size:11px;line-height:1.35}
}

/* Mobile video preview improvements */
@media (max-width: 768px) {
  .mv-thumb video, .mv-thumb img { object-fit: contain; }
  .bookmark-thumb video, .bookmark-thumb img { object-fit: contain; }
  .pending-media-btn video, .pending-media-btn img { object-fit: contain; }
  
  /* Ensure videos load thumbnails on mobile */
  video { min-height: 1px; min-width: 1px; }
}

/* --- Admin pending preview --- */
.pending-media-btn{width:100%;padding:0;margin:10px 0 0;border:0;background:#000;border-radius:10px;overflow:hidden;cursor:pointer;display:flex;align-items:center;justify-content:center;min-height:180px}
.pending-media-btn video,.pending-media-btn img{width:100%;height:auto;max-height:180px;object-fit:contain;display:block;background:#000}
.pending-preview-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;padding:20px;z-index:99999}
.pending-preview-box{max-width:min(920px,95vw);width:100%}
.pending-preview-inner{background:#000;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.12)}
.pending-preview-inner video,.pending-preview-inner img{width:100%;max-height:75vh;height:auto;display:block;object-fit:contain}
.pending-preview-close{position:fixed;top:14px;right:14px;width:42px;height:42px;border:0;border-radius:12px;background:rgba(255,255,255,.12);color:#fff;font-size:26px;line-height:42px;cursor:pointer;z-index:100000}
.pending-preview-nav{position:fixed;top:50%;transform:translateY(-50%);width:42px;height:42px;border:0;border-radius:12px;background:rgba(255,255,255,.12);color:#fff;font-size:22px;line-height:42px;cursor:pointer;z-index:100000}
.pending-preview-prev{left:14px}
.pending-preview-next{right:66px}
.pending-thumbs{margin-top:12px;display:flex;gap:10px;overflow-x:auto;padding:10px;background:rgba(255,255,255,.06);border-radius:12px;border:1px solid rgba(255,255,255,.08)}
.pending-thumb{flex:0 0 auto;width:72px;height:120px;border:1px solid rgba(255,255,255,.14);border-radius:10px;background:#000;overflow:hidden;padding:0;cursor:pointer}
.pending-thumb.active{border-color:#fc7b07;box-shadow:0 0 0 2px rgba(252,123,7,.25)}
.pending-thumb video,.pending-thumb img{width:100%;height:100%;object-fit:cover;display:block}

.my-preview-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;padding:20px;z-index:99999}
.my-preview-box{max-width:min(920px,95vw);width:100%}
.my-preview-inner{background:#000;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.12)}
.my-preview-inner video,.my-preview-inner img{width:100%;max-height:75vh;height:auto;display:block;object-fit:contain}
.my-preview-close{position:fixed;top:14px;right:14px;width:42px;height:42px;border:0;border-radius:12px;background:rgba(255,255,255,.12);color:#fff;font-size:26px;line-height:42px;cursor:pointer;z-index:100000}
</style>

<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=106417259', 'ym');

    ym(106417259, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/106417259" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->

</head>
<body>
<div class="loading-screen" id="loading">
  <div class="loader"></div>
  <p style="margin-top: 10px; color: #fc7b07; font-size: 12px;">Загрузка...</p>
</div>

<div class="top-nav">
  <a href="#" class="logo">
    <img src="https://masterhacks.ru/gklo.png" alt="MasterHacks" class="logo-image">
  </a>
  <div class="nav-actions">
    <?php if (defined('TELEGRAM_BOT_URL') && TELEGRAM_BOT_URL): ?>
    <?php
      $addUrl = (string)TELEGRAM_BOT_URL;
      if ($addUrl && strpos($addUrl, 'start=') === false) {
          $addUrl .= (strpos($addUrl, '?') === false ? '?' : '&') . 'start=upload';
      }
    ?>
    <a class="nav-btn nav-btn-add" href="<?= sanitize($addUrl) ?>" target="_blank" rel="noopener" title="Добавить видео через Telegram"><i class="fas fa-plus"></i>
    </a>
    <?php endif; ?>

    <?php if (empty($_SESSION['telegram_id'])): ?>
    <?php
      $loginBotUrl = '';
      if (defined('TELEGRAM_BOT_USERNAME') && TELEGRAM_BOT_USERNAME) {
          $loginBotUrl = 'tg://resolve?domain=' . rawurlencode((string)TELEGRAM_BOT_USERNAME) . '&start=login';
      } elseif (defined('TELEGRAM_BOT_URL') && TELEGRAM_BOT_URL) {
          $loginBotUrl = (string)TELEGRAM_BOT_URL;
          if (strpos($loginBotUrl, 'start=') === false) {
              $loginBotUrl .= (strpos($loginBotUrl, '?') === false ? '?' : '&') . 'start=login';
          }
      }
    ?>
    <div class="tg-login-wrap">
      <a href="<?= sanitize($loginBotUrl) ?>" class="nav-btn tg-login-btn" title="Войти через Telegram">
        <i class="fab fa-telegram"></i>
      </a>
      <span class="tg-login-label">Вход</span>
    </div>
    <?php else: ?>
    <button class="nav-btn" onclick="openMyVideos()" title="Мои видео">
      <i class="fas fa-video"></i>
    </button>
    <button class="nav-btn" onclick="logout()" title="Выйти">
      <i class="fas fa-sign-out-alt"></i>
    </button>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
    <button class="nav-btn" onclick="clearCache()" title="Очистить кэш">
      <i class="fas fa-sync-alt"></i>
    </button>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
    <button class="nav-btn" onclick="openAdminPanel()" title="Админ-панель" style="background: #dc3545;">
      <i class="fas fa-shield-alt"></i>
    </button>
    <?php endif; ?>

    <button class="nav-btn" onclick="openBookmarks()" title="Закладки">
      <i class="fas fa-bookmark"></i>
      <span class="badge" id="bookmarksBadge" style="display: none;">0</span>
    </button>
    <?php if (isAdmin()): ?>
    <button class="nav-btn" onclick="openHistory()" title="История">
      <i class="fas fa-history"></i>
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="feed-side-panel" aria-label="Переключатель ленты">
  <a class="feed-side-btn<?= ($feed_type !== 'subscriptions') ? ' active' : '' ?>" href="/" aria-label="Рекомендации" title="Рекомендации"><i class="fas fa-fire"></i></a>
  <a class="feed-side-btn<?= ($feed_type === 'subscriptions') ? ' active' : '' ?>" href="/?feed=subscriptions" aria-label="Подписки" title="Подписки"><i class="fas fa-users"></i></a>
</div>

<div class="pending-preview-overlay" id="pendingPreviewOverlay" onclick="closePendingPreview(event)">
  <button class="pending-preview-close" type="button" onclick="closePendingPreview(event)">×</button>
  <button class="pending-preview-nav pending-preview-prev" type="button" onclick="prevPendingPreview(event)">‹</button>
  <button class="pending-preview-nav pending-preview-next" type="button" onclick="nextPendingPreview(event)">›</button>
  <div class="pending-preview-box" onclick="stopPendingPreviewClick(event)">
    <div class="pending-preview-inner" id="pendingPreviewInner"></div>
    <div class="pending-thumbs" id="pendingPreviewThumbs"></div>
  </div>
</div>

<div class="modal-overlay" id="myVideosModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-user-circle"></i> Мои видео</h3>
      <button class="close-modal" onclick="closeMyVideos()">×</button>
    </div>
    <div id="myVideosTotals" class="mv-totals"></div>
    <div class="comments-container" id="myVideosList"></div>
  </div>
</div>

<div class="my-preview-overlay" id="myPreviewOverlay" onclick="closeMyPreview(event)">
  <button class="my-preview-close" type="button" onclick="closeMyPreview(event)">×</button>
  <div class="my-preview-box" onclick="stopMyPreviewClick(event)">
    <div class="my-preview-inner" id="myPreviewInner"></div>
  </div>
</div>

<div class="feed-container" id="feed">
  <section class="post intro-post" id="welcome">
    <div class="intro-card">
      <div class="intro-badge"><i class="fas fa-bolt"></i> Видео-платформа с входом через Telegram</div>
      <h1 class="intro-title">Смотрите короткие видео, загружайте свои и быстро входите без регистрации.</h1>
      <p class="intro-subtitle">MasterHacks — это лента коротких видео с простым входом через Telegram. Вы можете смотреть ролики сразу, а для загрузки своих видео и управления ими достаточно одного касания.</p>

      <div class="intro-actions">
        <?php if (defined('TELEGRAM_BOT_URL') && TELEGRAM_BOT_URL): ?>
        <a class="intro-btn intro-btn-primary" href="<?= sanitize($addUrl) ?>" target="_blank" rel="noopener"><i class="fas fa-plus"></i> Загрузить своё видео</a>
        <?php endif; ?>
        <?php if (empty($_SESSION['telegram_id']) && !empty($loginBotUrl)): ?>
        <a class="intro-btn intro-btn-secondary" href="<?= sanitize($loginBotUrl) ?>"><i class="fab fa-telegram"></i> Войти через Telegram</a>
        <?php endif; ?>
        <a class="intro-btn intro-btn-secondary" href="#feed-start"><i class="fas fa-play"></i> Смотреть ленту</a>
      </div>

      <div class="intro-section">
        <div class="intro-section-title">Что вы получите</div>
        <div class="intro-section-text">Сразу после входа вы сможете управлять своими видео, быстро загружать новые ролики через бота и следить за своей активностью без лишних форм и паролей.</div>
      </div>

      <div class="intro-section">
        <div class="intro-section-title">Как это работает</div>
        <div class="intro-steps">
          <div class="intro-step">
            <div class="intro-step-num">1</div>
            <div>
              <div class="intro-step-title">Откройте бота</div>
              <div class="intro-step-text">Нажмите `Вход` или `Загрузить`, чтобы перейти в Telegram-бота MasterHacks.</div>
            </div>
          </div>
          <div class="intro-step">
            <div class="intro-step-num">2</div>
            <div>
              <div class="intro-step-title">Получите доступ в один клик</div>
              <div class="intro-step-text">Бот отправит одноразовую кнопку входа. Никаких паролей и долгих регистраций.</div>
            </div>
          </div>
          <div class="intro-step">
            <div class="intro-step-num">3</div>
            <div>
              <div class="intro-step-title">Смотрите и публикуйте</div>
              <div class="intro-step-text">Листайте ленту, загружайте свои видео и управляйте ими через личный кабинет на сайте.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div id="feed-start"></div>
  <?php foreach($posts as $index => $post): ?>
  <?php renderPost($post, $start + $index, $subscribedAuthors); ?>
  <?php endforeach; ?>
  
  <!-- Автоподгрузка -->
  <?php if($page < $total_pages): ?>
  <div class="load-more-container" id="loadMoreContainer">
    <div class="load-more-status" id="loadMoreStatus">
      <i class="fas fa-angle-double-down"></i> Листайте дальше
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<div class="modal-overlay" id="commentsModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-comments"></i> Комментарии</h3>
      <button class="close-modal" onclick="closeComments()">×</button>
    </div>
    <div class="comments-container" id="commentsList"></div>
    <div class="comment-form">
      <div id="replyBar" style="display:none; flex: 1; align-items:center; justify-content:space-between; gap:8px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10); border-radius: 12px; padding: 6px 10px; color: rgba(255,255,255,0.85); font-size: 11px;">
        <span id="replyLabel" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></span>
        <button type="button" onclick="cancelReply()" style="background: transparent; border: 0; color: #fc7b07; font-weight: 900; cursor: pointer;">×</button>
      </div>
      <input type="text" class="comment-input" placeholder="Ваш комментарий..." id="commentInput" maxlength="200">
      <button class="send-comment" onclick="sendComment()">
        <i class="fas fa-paper-plane"></i>
      </button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="shareModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-share-alt"></i> Поделиться</h3>
      <button class="close-modal" onclick="closeShare()">×</button>
    </div>
    <div class="share-grid">
      <button class="share-option" onclick="shareTo('telegram')">
        <div class="share-icon">
          <i class="fab fa-telegram"></i>
        </div>
        <span class="share-name">Telegram</span>
      </button>
      <button class="share-option" onclick="shareTo('vk')">
        <div class="share-icon">
          <i class="fab fa-vk"></i>
        </div>
        <span class="share-name">VK</span>
      </button>
      <button class="share-option" onclick="shareTo('whatsapp')">
        <div class="share-icon">
          <i class="fab fa-whatsapp"></i>
        </div>
        <span class="share-name">WhatsApp</span>
      </button>
      <button class="share-option" onclick="shareTo('max')">
        <div class="share-icon">
          <i class="fas fa-comment-dots"></i>
        </div>
        <span class="share-name">MAX</span>
      </button>
      <button class="share-option" onclick="shareTo('download')">
        <div class="share-icon">
          <i class="fas fa-download"></i>
        </div>
        <span class="share-name">Скачать</span>
      </button>
      <button class="share-option" onclick="shareTo('copy')">
        <div class="share-icon">
          <i class="fas fa-link"></i>
        </div>
        <span class="share-name">Ссылка</span>
      </button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="bookmarksModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-bookmark"></i> Закладки</h3>
      <button class="close-modal" onclick="closeBookmarks()">×</button>
    </div>
    <div class="bookmarks-list" id="bookmarksList"></div>
  </div>
</div>

<div class="modal-overlay" id="historyModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-history"></i> История</h3>
      <button class="close-modal" onclick="closeHistory()">×</button>
    </div>
    <div class="modal-body" style="padding: 20px; text-align:center; color:#666;">
      История просмотров сохраняется автоматически
    </div>
  </div>
</div>

<div class="modal-overlay" id="adminModal">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h3><i class="fas fa-shield-alt"></i> Админ-панель</h3>
      <button class="close-modal" onclick="closeAdminPanel()">×</button>
    </div>
    <div class="admin-panel" id="adminPanelContent" style="padding: 20px;">
      <div class="admin-stats">
        <h4>Статистика:</h4>
        <div id="adminStats">Загрузка...</div>
      </div>
      
      <div class="admin-actions" style="margin-top: 20px;">
        <h4>Действия:</h4>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <button class="btn" onclick="loadPendingVideos()" style="background: #ffc107;">
            <i class="fas fa-clock"></i> Показать видео на модерации
          </button>
          <button class="btn" onclick="refreshAllCache()" style="background: #0d6efd;">
            <i class="fas fa-redo"></i> Обновить все кэши
          </button>
          <button class="btn" onclick="deleteOldVideos()" style="background: #dc3545;">
            <i class="fas fa-trash"></i> Удалить старые видео (30+ дней)
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* =========================================
   FAST VIDEO FEED v3.3
   instant load + autoplay sound + pause + бесконечный скролл
   Авто звук для всех видео при воспроизведении
========================================= */

let state = {
    activePost: 0,
    soundEnabled: false,
    activeVideo: null,
    currentPage: <?= $page ?>,
    totalPages: <?= $total_pages ?>,
    isLoading: false,
    lastTap: 0,
    lastActionTime: {},
    currentPostId: null,
    replyTo: null,
    userHasScrolled: false
};

function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    const text = (message === null || message === undefined) ? '' : String(message);
    toast.textContent = text;
    toast.classList.add('show');
    if (toast.__hideTimer) clearTimeout(toast.__hideTimer);
    toast.__hideTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

document.addEventListener('DOMContentLoaded', () => {
    // Быстро скрываем экран загрузки
    setTimeout(() => {
        document.getElementById('loading').style.display = 'none';
    }, 300);

    try {
        const params = new URLSearchParams(window.location.search);
        const pid = params.get('p');
        if (pid) {
            setTimeout(() => {
                if (typeof scrollToPost === 'function') scrollToPost(pid);
            }, 600);
        }
    } catch (e) {
        // ignore
    }
    
    initVideos();
    initImages();
    initScroll();
    initVideoControls();
    initDoubleTap();
    initBookmarks();
    initKeyboardControls();
    autoRefresh();
    initInfiniteScroll();
});

/* ---------- AUTO REFRESH ---------- */
function autoRefresh() {
    // Проверяем обновления каждые 10 секунд
    setInterval(() => {
        fetch(window.location.pathname + '?check_update=1')
            .then(response => response.text())
            .then(data => {
                if (data.includes('new_posts')) {
                    // Сохраняем текущую позицию прокрутки
                    const feed = document.getElementById('feed');
                    const currentScroll = feed.scrollTop;
                    
                    // Извлекаем количество новых постов
                    let newCount = 0;
                    if (data.includes('new_posts:')) {
                        newCount = parseInt(data.split(':')[1]);
                    }
                    
                    // Показываем уведомление с опцией
                    const toast = document.getElementById('toast');
                    const message = newCount > 0 ? `Обнаружено ${newCount} новых видео!` : 'Обнаружены новые видео!';
                    
                    toast.innerHTML = `
                        <div>${message}</div>
                        <button onclick="loadNewPosts(${currentScroll})" 
                                style="background:rgba(255,255,255,0.2); border:none; color:#fc7b07; padding:4px 8px; border-radius:8px; margin-top:5px; font-size:11px; cursor:pointer;">
                            Загрузить
                        </button>
                    `;
                    toast.classList.add('show');
                    
                    // Автоматически скрываем через 6 секунд
                    setTimeout(() => {
                        toast.classList.remove('show');
                    }, 6000);
                }
            })
            .catch(() => {});
    }, 10000);
}


/* ---------- ЗАГРУЗКА НОВЫХ ПОСТОВ БЕЗ ПЕРЕЗАГРУЗКИ ---------- */
async function loadNewPosts(currentScroll) {
    const toast = document.getElementById('toast');
    toast.textContent = 'Загрузка новых видео...';
    toast.classList.add('show');
    
    try {
        // Обновляем кэш на сервере
        const refreshResponse = await fetch('?refresh_cache=1');
        const refreshResult = await refreshResponse.text();
        
        if (refreshResult === 'success') {
            // Загружаем первую страницу через AJAX
            const params = new URLSearchParams(window.location.search);
            const feed = params.get('feed');
            const url = feed ? `?page=1&ajax=1&feed=${encodeURIComponent(feed)}` : '?page=1&ajax=1';
            const postsResponse = await fetch(url);
            const newPostsHTML = await postsResponse.text();
            
            // Находим контейнер ленты
            const feedEl = document.getElementById('feed');
            
            // Сохраняем ID текущих постов на странице
            const currentPostIds = new Set();
            document.querySelectorAll('.post').forEach(post => {
                currentPostIds.add(post.dataset.id);
            });
            
            // Создаем временный контейнер для новых постов
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = newPostsHTML;
            
            // Получаем новые посты из временного контейнера
            const newPosts = tempDiv.querySelectorAll('.post');
            
            // Фильтруем только те, которых еще нет на странице
            let addedCount = 0;
            const postsToAdd = [];
            
            newPosts.forEach(newPost => {
                const newPostId = newPost.dataset.id;
                if (!currentPostIds.has(newPostId)) {
                    postsToAdd.push(newPost);
                    addedCount++;
                }
            });
            
            if (addedCount > 0) {
                // Добавляем посты в начало ленты
                postsToAdd.reverse().forEach(post => {
                    feedEl.insertBefore(post, feedEl.firstChild);
                    
                    // Инициализируем новый пост
                    const video = post.querySelector('video.lazy-media');
                    if (video) {
                        video.src = video.dataset.src;
                        video.muted = state.soundUnlocked ? false : true;
                        video.volume = 0;
                        video.loop = true;
                        video.playsInline = true;
                        video.load();
                    }
                    
                    const img = post.querySelector('img.lazy-media');
                    if (img && img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                });
                
                toast.textContent = `Добавлено ${addedCount} новых видео!`;
                
                // Прокручиваем немного вниз, чтобы показать новые посты
                setTimeout(() => {
                    feedEl.scrollTo({
                        top: currentScroll + (addedCount * window.innerHeight),
                        behavior: 'smooth'
                    });
                }, 500);
            } else {
                toast.textContent = 'Новых видео нет';
            }
            
            // Обновляем кнопку "Загрузить еще" если нужно
            const loadMoreContainer = document.querySelector('.load-more-container');
            const newLoadMore = tempDiv.querySelector('.load-more-container');
            
            if (!loadMoreContainer && newLoadMore) {
                // Удаляем старую кнопку если есть
                const oldLoadMore = document.getElementById('loadMoreContainer');
                if (oldLoadMore) oldLoadMore.remove();
                
                // Добавляем новую кнопку
                feedEl.appendChild(newLoadMore);
            } else if (loadMoreContainer && !newLoadMore) {
                // Удаляем кнопку, если больше нет постов
                loadMoreContainer.remove();
            }
            
            // Обновляем состояние
            state.currentPage = 1;
        } else {
            toast.textContent = 'Ошибка обновления';
        }
    } catch (error) {
        console.error('Ошибка:', error);
        toast.textContent = 'Ошибка загрузки';
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

/* ---------- БЕСКОНЕЧНЫЙ СКРОЛЛ ---------- */
function initInfiniteScroll() {
    const feed = document.getElementById('feed');
    if (!feed) return;

    const loadMoreContainer = document.getElementById('loadMoreContainer');
    if (loadMoreContainer && 'IntersectionObserver' in window) {
        if (window.__mhLoadMoreObserver) {
            try { window.__mhLoadMoreObserver.disconnect(); } catch (e) {}
        }

        window.__mhLoadMoreObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !state.isLoading && state.currentPage < state.totalPages) {
                    loadMorePosts();
                }
            });
        }, {
            root: feed,
            rootMargin: '0px 0px 220px 0px',
            threshold: 0.1
        });

        try { window.__mhLoadMoreObserver.observe(loadMoreContainer); } catch (e) {}
    }
    
    let isThrottled = false;
    
    feed.addEventListener('scroll', () => {
        if (isThrottled) return;
        
        isThrottled = true;
        setTimeout(() => {
            try {
                isThrottled = false;
                
                // Проверяем, достигли ли мы конца страницы
                const scrollTop = feed.scrollTop;
                const scrollHeight = feed.scrollHeight;
                const clientHeight = feed.clientHeight;
                const scrollPosition = scrollTop + clientHeight;
                
                // Если осталось меньше 900px до конца и есть еще страницы
                if (scrollHeight - scrollPosition < 900 && 
                    state.currentPage < state.totalPages && 
                    !state.isLoading) {
                    loadMorePosts();
                }
            } catch (e) {
                isThrottled = false;
            }
        }, 200);
    }, { passive: true });
}

/* ---------- ЗАГРУЗКА ДОПОЛНИТЕЛЬНЫХ ПОСТОВ ---------- */
async function loadMorePosts() {
    if (state.isLoading || state.currentPage >= state.totalPages) return;
    
    state.isLoading = true;
    const status = document.getElementById('loadMoreStatus');
    const container = document.getElementById('loadMoreContainer');
    
    if (status) {
        status.classList.add('loading');
        status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Загрузка...';
    }
    
    try {
        const nextPage = state.currentPage + 1;
        const params = new URLSearchParams(window.location.search);
        const feed = params.get('feed');
        const url = feed ? `?page=${nextPage}&ajax=1&feed=${encodeURIComponent(feed)}` : `?page=${nextPage}&ajax=1`;
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
        
        try {
            const response = await fetch(url, { signal: controller.signal });
            clearTimeout(timeoutId);
            
            const html = await response.text();
            
            if (html.trim()) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                const newPosts = Array.from(tempDiv.querySelectorAll('.post'));
                const newLoadMore = tempDiv.querySelector('.load-more-container');

                if (container) {
                    container.remove();
                }

                const feedEl = document.getElementById('feed');
                const frag = document.createDocumentFragment();
                newPosts.forEach(post => frag.appendChild(post));
                feedEl.appendChild(frag);

                if (newLoadMore) {
                    feedEl.appendChild(newLoadMore);
                }

                initVideos(feedEl);
                initImages(feedEl);
                initVideoControls(feedEl);
                initDoubleTap(feedEl);

                state.currentPage = nextPage;

                showToast(`Загружено ${newPosts.length} видео`);
            } else {
                // Если нет контента, скрываем кнопку
                if (container) {
                    container.style.display = 'none';
                }
                showToast('Больше видео нет');
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                showToast('Время ожидания истекло');
            } else {
                console.error('Ошибка загрузки:', error);
                showToast('Ошибка загрузки');
            }
        }
    } catch (error) {
        console.error('Ошибка:', error);
        showToast('Ошибка загрузки');
    } finally {
        state.isLoading = false;
        if (status && state.currentPage < state.totalPages) {
            status.classList.remove('loading');
            status.innerHTML = '<i class="fas fa-angle-double-down"></i> Листайте дальше';
        }
    }
}

/* ---------- VIDEO INIT ---------- */
function initVideos(root = document) {
    const scope = root && root.querySelectorAll ? root : document;

    // One shared observer for lazy loading
    if (!window.__mhVideoObserver && 'IntersectionObserver' in window) {
        window.__mhVideoObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target;
                if (!video) return;
                if (entry.isIntersecting && entry.intersectionRatio >= 0.25) {
                    ensureVideoLoaded(video);
                }
            });
        }, { threshold: [0.0, 0.25, 0.5] });
    }

    scope.querySelectorAll('video.lazy-media').forEach(video => {
        if (video.dataset.inited === '1') return;

        video.muted = state.soundUnlocked ? false : true;
        video.volume = 0;
        video.loop = true;
        video.playsInline = true;
        // Do not auto-download every video. We'll load on demand.
        video.preload = 'none';
        video.dataset.inited = '1';
        
        // Добавляем обработчик ошибок видео
        video.addEventListener('error', () => {
            const post = video.closest('.post');
            if (post) {
                post.style.opacity = '0.5';
                post.setAttribute('data-video-error', '1');
            }
        }, { once: true });
        
        // Observe for lazy loading
        try {
            if (window.__mhVideoObserver) window.__mhVideoObserver.observe(video);
        } catch (e) {}
    });

    // Автоплей лучше запускать после того как браузер отрисовал вставку
    requestAnimationFrame(() => {
        playFirstVisible();
    });
}

function ensureVideoLoaded(video) {
    if (!video) return;
    if (video.getAttribute('data-video-error') === '1') return;

    const desiredSrc = video.dataset ? video.dataset.src : null;
    if (!desiredSrc) return;

    if (!video.src) {
        video.src = desiredSrc;
    }

    // Some browsers won't start buffering until explicit load()
    try {
        if (video.readyState === 0) video.load();
    } catch (e) {}
}

function unloadDistantVideos(keepSet) {
    const keep = keepSet || new Set();
    document.querySelectorAll('video.lazy-media').forEach(v => {
        if (!v) return;
        if (keep.has(v)) return;

        try {
            v.pause();
        } catch (e) {}

        // Unload to free network/memory
        try {
            if (v.src) {
                v.removeAttribute('src');
                v.load();
            }
        } catch (e) {}
    });
}

/* ---------- IMAGE INIT ---------- */
function initImages(root = document) {
    const scope = root && root.querySelectorAll ? root : document;
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
            }
        });
    }, { threshold: 0.1 });

    scope.querySelectorAll('img.lazy-media').forEach(img => {
        if (img.dataset.observed === '1') return;
        img.dataset.observed = '1';
        imageObserver.observe(img);
    });
}

/* ---------- VIDEO CONTROLS ---------- */
function initVideoControls(root = document) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.post[data-type="video"] .media-container').forEach(container => {
        const post = container.closest('.post');
        if (post && post.dataset.controlsInited === '1') return;
        if (post) post.dataset.controlsInited = '1';
        const postId = post.dataset.id;
        
        let tapCount = 0;
        let tapTimer = null;
        
        container.addEventListener('click', (e) => {
            if (e.target.closest('.action-btn, .sound-toggle, .pause-toggle')) return;
            
            tapCount++;
            
            if (tapCount === 1) {
                tapTimer = setTimeout(() => {
                    // Одиночный тап - пауза/воспроизведение
                    togglePause(postId);
                    tapCount = 0;
                }, 300);
            } else if (tapCount === 2) {
                // Двойной тап - лайк
                clearTimeout(tapTimer);
                triggerDoubleTapLike(post);
                tapCount = 0;
            }
        });
    });
}

/* ---------- PAUSE/PLAY FUNCTION ---------- */
function togglePause(postId) {
    const video = document.querySelector(`[data-id="${postId}"] video`);
    const pauseOverlay = document.getElementById(`pauseOverlay-${postId}`);
    const pauseBtn = document.getElementById(`pauseBtn-${postId}`);
    
    if (!video) return;
    
    if (video.paused) {
        // Воспроизведение
        video.play().catch(() => {});
        if (pauseOverlay) pauseOverlay.classList.remove('show');
        if (pauseBtn) {
            const icon = pauseBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-pause';
            pauseBtn.classList.remove('playing');
        }
        video.closest('.post').classList.remove('video-paused');
    } else {
        // Пауза
        video.pause();
        if (pauseOverlay) pauseOverlay.classList.add('show');
        if (pauseBtn) {
            const icon = pauseBtn.querySelector('i');
            if (icon) icon.className = 'fas fa-play';
            pauseBtn.classList.add('playing');
        }
        video.closest('.post').classList.add('video-paused');
    }
}

/* ---------- SCROLL HANDLER ---------- */
function initScroll() {
    const feed = document.getElementById('feed');
    if (!feed) return;
    
    let scrollTimer;
    let isProcessing = false;

    feed.addEventListener('scroll', () => {
        state.userHasScrolled = true;
        clearTimeout(scrollTimer);
        
        // Предотвращаем одновременную обработку нескольких скроллов
        if (isProcessing) return;
        
        scrollTimer = setTimeout(() => {
            if (!isProcessing) {
                isProcessing = true;
                try {
                    requestAnimationFrame(() => {
                        try {
                            playFirstVisible();
                        } finally {
                            isProcessing = false;
                        }
                    });
                } catch (e) {
                    isProcessing = false;
                }
            }
        }, 100);
    }, { passive: true });
}

function playFirstVisible() {
    const posts = document.querySelectorAll('.post[data-type="video"]');
    let best = null;
    let bestRatio = 0;

    posts.forEach(post => {
        // Пропускаем видео на паузе и видео с ошибками
        if (post.classList.contains('video-paused') || post.getAttribute('data-video-error') === '1') return;
        
        const rect = post.getBoundingClientRect();
        const visible = Math.max(0,
            Math.min(window.innerHeight, rect.bottom) -
            Math.max(0, rect.top)
        );
        const ratio = visible / window.innerHeight;

        if (ratio > bestRatio && ratio > 0.5) {
            bestRatio = ratio;
            best = post;
        }
    });

    if (!best) return;

    const video = best.querySelector('video');
    if (!video || best.getAttribute('data-video-error') === '1') return;

    // Ensure current + neighbors are loaded; unload the rest.
    const keep = new Set();
    const all = Array.from(posts);
    const idx = all.indexOf(best);
    const neighbors = [idx, idx - 1, idx + 1, idx + 2].filter(i => i >= 0 && i < all.length);
    neighbors.forEach(i => {
        const v = all[i].querySelector('video');
        if (v) {
            keep.add(v);
            ensureVideoLoaded(v);
        }
    });
    unloadDistantVideos(keep);

    if (state.activeVideo !== video) {
        try {
            // Маршрутизация звука: не трогаем muted при скролле, только громкости
            document.querySelectorAll('video').forEach(v => {
                try {
                    v.volume = 0;
                } catch (e) {}
            });
            
            document.querySelectorAll('.sound-toggle').forEach(btn => {
                btn.classList.remove('unmuted');
                btn.innerHTML = '<i class="fas fa-volume-mute"></i>';
            });
            
            pauseAll();
            state.activeVideo = video;
            playVideo(video);
            registerView(best.dataset.id);
        } catch (e) {
            // Catch any sync errors during video playback
        }
    }
}

async function registerView(postId) {
    const pid = String(postId || '');
    if (!pid) return;

    if (!state.viewedPosts) state.viewedPosts = {};
    const now = Date.now();

    if (state.viewedPosts[pid] && (now - state.viewedPosts[pid]) < 600000) {
        return;
    }
    state.viewedPosts[pid] = now;

    try {
        const response = await fetch('view.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ post_id: parseInt(pid, 10) })
        });
        const result = await response.json();
        if (result && result.success && typeof result.views === 'number') {
            const el = document.getElementById('viewsCount-' + pid);
            if (el) el.textContent = String(result.views);
        }
    } catch (e) {
        // ignore
    }
}

async function toggleSubscribe(authorTelegramId, button) {
    const tid = parseInt(authorTelegramId, 10);
    if (!Number.isFinite(tid) || tid <= 0) return;

    try {
        const response = await fetch('api/toggle_subscription.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ author_telegram_id: tid })
        });
        let result = null;
        try {
            result = await response.json();
        } catch (e) {
            result = null;
        }

        if (!response.ok) {
            const msg = (result && result.error) ? String(result.error) : ('Ошибка подписки: ' + response.status);
            if (response.status === 401) {
                showToast('Нужно войти через Telegram');
            } else {
                showToast(msg);
            }
            return;
        }

        if (result && result.success) {
            if (result.subscribed) {
                button.classList.add('subscribed');
                button.textContent = 'Вы подписаны';
                showToast('Подписка оформлена');
            } else {
                button.classList.remove('subscribed');
                button.textContent = 'Подписаться';
                showToast('Подписка отменена');
            }
            return;
        }

        if (result && result.error) {
            showToast(String(result.error));
        } else {
            showToast('Ошибка подписки');
        }
    } catch (e) {
        showToast('Ошибка сети');
    }
}

function pauseAll() {
    document.querySelectorAll('video').forEach(v => {
        try {
            if (!v.paused) v.pause();
        } catch (e) {
            // ignore pause errors
        }
    });
}

function playVideo(video) {
    if (!video || !video.closest) return;
    
    const post = video.closest('.post');
    if (!post) return;
    
    const postId = post.dataset.id;
    const soundBtn = document.querySelector(`#sound-${postId}`);

    // Always make sure video is actually loaded before trying to play
    ensureVideoLoaded(video);

    // Добавляем обработчики ошибок видео
    const handleVideoError = () => {
        try {
            const p = video.closest('.post');
            if (p) p.setAttribute('data-video-error', '1');
        } catch (e) {}

        // Important: do NOT auto-scroll on first load.
        // Only skip by scrolling after user has started scrolling.
        if (state.userHasScrolled) {
            skipToNextVideo();
        } else {
            requestAnimationFrame(() => {
                try { playFirstVisible(); } catch (e) {}
            });
        }
    };

    const handleVideoStalled = () => {
        // Если видео зависло на загрузке более 5 секунд, пропускаем
        const stalledTimeout = setTimeout(() => {
            if (video.networkState === 2 && video.readyState < 2) { // NETWORK_LOADING but not enough data
                handleVideoError();
            }
        }, 5000);
        
        video.addEventListener('canplay', () => {
            clearTimeout(stalledTimeout);
        }, { once: true });
    };

    video.addEventListener('error', handleVideoError, { once: true });
    video.addEventListener('stalled', handleVideoStalled, { once: true });

    // До первого явного действия пользователя — всегда muted
    if (!state.soundUnlocked) {
        video.muted = true;
        video.volume = 0;
        try {
            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.catch(() => {
                    handleVideoError();
                });
            }
        } catch (e) {
            handleVideoError();
        }
        if (soundBtn) {
            soundBtn.classList.remove('unmuted');
            soundBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
        }
        return;
    }

    // Автозвук после скролла: сначала гарантированно запускаем воспроизведение,
    // и только ПОСЛЕ успешного старта выставляем громкость.
    const desiredVolume = state.soundEnabled ? 0.7 : 0;
    video.volume = 0;
    
    // Timeout для обнаружения зависаний (3 секунды)
    const playTimeout = setTimeout(() => {
        if (video.paused || video.readyState < 2) {
            handleVideoError();
        }
    }, 3000);

    try {
        const playPromise = video.play();
        if (playPromise === undefined) {
            // Старые браузеры без Promise
            clearTimeout(playTimeout);
            video.volume = desiredVolume;
            if (soundBtn) {
                soundBtn.classList[desiredVolume > 0 ? 'add' : 'remove']('unmuted');
                soundBtn.innerHTML = desiredVolume > 0 ? '<i class="fas fa-volume-up"></i>' : '<i class="fas fa-volume-mute"></i>';
            }
        } else {
            playPromise
                .then(() => {
                    clearTimeout(playTimeout);
                    video.volume = desiredVolume;
                    if (soundBtn) {
                        soundBtn.classList[desiredVolume > 0 ? 'add' : 'remove']('unmuted');
                        soundBtn.innerHTML = desiredVolume > 0 ? '<i class="fas fa-volume-up"></i>' : '<i class="fas fa-volume-mute"></i>';
                    }
                })
                .catch(() => {
                    clearTimeout(playTimeout);
                    handleVideoError();
                });
        }
    } catch (e) {
        clearTimeout(playTimeout);
        handleVideoError();
    }
}

function skipToNextVideo() {
    const feed = document.getElementById('feed');
    if (!feed) return;
    
    // Прокручиваем на высоту viewport для перехода к следующему видео
    const currentScroll = feed.scrollTop;
    const nextScroll = currentScroll + window.innerHeight;
    
    feed.scrollTo({
        top: nextScroll,
        behavior: 'smooth'
    });
}

/* ---------- DOUBLE TAP ---------- */
function initDoubleTap(root = document) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.post').forEach(post => {
        if (post.dataset.doubleTapInited === '1') return;
        post.dataset.doubleTapInited = '1';
        const mediaContainer = post.querySelector('.media-container');
        let lastTap = 0;
        
        mediaContainer.addEventListener('click', (e) => {
            if (e.target.closest('.action-btn, .sound-toggle, .pause-toggle, .pause-icon')) return;
            
            const now = Date.now();
            const timeDiff = now - lastTap;
            
            if (timeDiff < 300 && timeDiff > 0) {
                triggerDoubleTapLike(post);
                lastTap = 0;
            } else {
                lastTap = now;
            }
        });
    });
}

function triggerDoubleTapLike(post) {
    const postId = post.dataset.id;
    const heart = document.getElementById('heart-' + postId);
    const likeBtn = post.querySelector('.like-btn');
    
    heart.classList.remove('animate');
    void heart.offsetWidth;
    heart.classList.add('animate');
    
    if (!likeBtn.classList.contains('liked')) {
        toggleLike(postId, likeBtn);
    }
    
    setTimeout(() => {
        heart.classList.remove('animate');
    }, 800);
}

/* ---------- MANUAL SOUND ---------- */
function toggleSound(btn, postId) {
    const video = document.querySelector(`[data-id="${postId}"] video`);
    if (!video) return;

    // фиксируем реальное взаимодействие пользователя
    state.userInteracted = true;

    // Если звук еще не разблокирован — делаем это ОДИН раз по клику
    if (!state.soundUnlocked) {
        state.soundUnlocked = true;

        // Важно: размучиваем текущие видео ОДИН раз в рамках user gesture.
        // Дальше на скролле muted не трогаем, чтобы не останавливать воспроизведение на некоторых мобилках.
        document.querySelectorAll('video').forEach(v => {
            v.muted = false;
        });
    }

    // Переключаем режим звука (вкл/выкл) и маршрутизируем через громкость
    state.soundEnabled = !state.soundEnabled;

    // Заглушаем все видео громкостью, muted не трогаем при последующих скроллах
    document.querySelectorAll('video').forEach(v => {
        v.volume = 0;
    });

    // Для текущего видео включаем/выключаем громкость
    video.volume = state.soundEnabled ? 0.7 : 0;
    // Важно: попытка play() именно тут — это пользовательский жест, меньше шансов, что мобилка "уронит" видео
    video.play().catch(() => {});

    // Обновляем иконки
    document.querySelectorAll('.sound-toggle').forEach(b => {
        b.classList.remove('unmuted');
        b.innerHTML = '<i class="fas fa-volume-mute"></i>';
    });

    if (state.soundEnabled) {
        btn.classList.add('unmuted');
        btn.innerHTML = '<i class="fas fa-volume-up"></i>';
    } else {
        btn.classList.remove('unmuted');
        btn.innerHTML = '<i class="fas fa-volume-mute"></i>';
    }
}

/* ---------- KEYBOARD CONTROLS ---------- */
function initKeyboardControls() {
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space' && state.activeVideo) {
            e.preventDefault();
            const postId = state.activeVideo.closest('.post')?.dataset.id;
            if (postId) togglePause(postId);
        }
        
        // Стрелки вверх/вниз для навигации
        if (e.code === 'ArrowDown' || e.code === 'ArrowUp') {
            e.preventDefault();
            const feed = document.getElementById('feed');
            const scrollAmount = window.innerHeight * (e.code === 'ArrowDown' ? 1 : -1);
            feed.scrollBy({ top: scrollAmount, behavior: 'smooth' });
        }
        
        // M - переключение звука активного видео
        if (e.code === 'KeyM' && state.activeVideo) {
            e.preventDefault();
            const postId = state.activeVideo.closest('.post')?.dataset.id;
            if (postId) {
                const soundBtn = document.querySelector(`#sound-${postId}`);
                if (soundBtn) toggleSound(soundBtn, postId);
            }
        }
    });
}

/* ---------- MEDIA EVENTS ---------- */
function mediaLoaded(element, postId) {
    const placeholder = document.getElementById('placeholder-' + postId);
    if (placeholder) placeholder.style.display = 'none';
}

function mediaError(element, postId) {
    const placeholder = document.getElementById('placeholder-' + postId);
    if (placeholder) {
        placeholder.innerHTML = '<span style="color:#fc7b07;">Ошибка</span>';
    }

    const postEl = element && element.closest ? element.closest('.post') : null;
    if (postEl && postEl.parentNode) {
        if (state.activeVideo && postEl.contains(state.activeVideo)) {
            try { state.activeVideo.pause(); } catch (e) {}
            state.activeVideo = null;
        }
        postEl.parentNode.removeChild(postEl);
        requestAnimationFrame(() => {
            playFirstVisible();
        });
    }
}

function videoCanPlay(element, postId) {
    const placeholder = document.getElementById('placeholder-' + postId);
    if (placeholder) placeholder.style.display = 'none';
    element.style.opacity = 1;
}

function videoError(element, postId) {
    const placeholder = document.getElementById('placeholder-' + postId);
    if (placeholder) {
        placeholder.innerHTML = '<span style="color:#fc7b07;">Ошибка</span>';
    }

    const postEl = element && element.closest ? element.closest('.post') : null;
    if (postEl && postEl.parentNode) {
        if (state.activeVideo && postEl.contains(state.activeVideo)) {
            try { state.activeVideo.pause(); } catch (e) {}
            state.activeVideo = null;
        }
        postEl.parentNode.removeChild(postEl);
        requestAnimationFrame(() => {
            playFirstVisible();
        });
    }
}

/* ---------- LIKES ---------- */
async function toggleLike(postId, button) {
    const now = Date.now();
    if (state.lastActionTime['like_' + postId] && now - state.lastActionTime['like_' + postId] < 1000) {
        return;
    }
    state.lastActionTime['like_' + postId] = now;
    
    const countSpan = button.querySelector('.action-count');
    const currentCount = parseInt(countSpan.textContent) || 0;
    
    try {
        const response = await fetch('like.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({post_id: parseInt(postId)})
        });
        const result = await response.json();
        
        if (result.success) {
            countSpan.textContent = result.likes;
            button.classList.toggle('liked', result.liked);
        } else {
            if (button.classList.contains('liked')) {
                button.classList.remove('liked');
                countSpan.textContent = Math.max(0, currentCount - 1);
            } else {
                button.classList.add('liked');
                countSpan.textContent = currentCount + 1;
            }
        }
    } catch (error) {
        if (button.classList.contains('liked')) {
            button.classList.remove('liked');
            countSpan.textContent = Math.max(0, currentCount - 1);
        } else {
            button.classList.add('liked');
            countSpan.textContent = currentCount + 1;
        }
    }
}

/* ---------- BOOKMARKS ---------- */
function initBookmarks() {
    updateBookmarksBadge();
    document.querySelectorAll('.bookmark-btn').forEach(btn => {
        try {
            const postData = JSON.parse(btn.dataset.post);
            const bookmarks = getBookmarks();
            if (bookmarks.some(b => b.id == postData.id)) {
                btn.classList.add('bookmarked');
            }
        } catch (e) {}
    });
}

function getBookmarks() {
    try {
        return JSON.parse(localStorage.getItem('bookmarks') || '[]');
    } catch {
        return [];
    }
}

function saveBookmarks(bookmarks) {
    localStorage.setItem('bookmarks', JSON.stringify(bookmarks));
    updateBookmarksBadge();
}

function toggleBookmark(postId, button) {
    try {
        const postData = JSON.parse(button.dataset.post);
        let bookmarks = getBookmarks();
        const index = bookmarks.findIndex(b => b.id == postData.id);
        
        if (index > -1) {
            bookmarks.splice(index, 1);
            button.classList.remove('bookmarked');
            showToast('Удалено из закладок');
        } else {
            postData.savedAt = new Date().toISOString();
            bookmarks.unshift(postData);
            button.classList.add('bookmarked');
            showToast('Добавлено в закладки');
        }
        saveBookmarks(bookmarks);
    } catch (e) {
        showToast('Ошибка');
    }
}

function updateBookmarksBadge() {
    const badge = document.getElementById('bookmarksBadge');
    const count = getBookmarks().length;
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}

function openBookmarks() {
    const modal = document.getElementById('bookmarksModal');
    if (!modal) return;
    modal.style.display = 'flex';
    const list = document.getElementById('bookmarksList');
    if (!list) return;

    const bookmarks = getBookmarks();
    if (!Array.isArray(bookmarks) || bookmarks.length === 0) {
        list.innerHTML = '<div style="text-align:center; padding:20px; color:#666;">Нет закладок</div>';
        return;
    }

    list.innerHTML = bookmarks.map((b) => {
        const id = b.id ?? '';
        const type = b.type ?? 'video';
        const filename = b.filename ?? '';
        const date = b.date ?? '';
        const src = filename ? ('media/' + filename) : '';
        const safeDate = String(date || '').slice(0, 10);
        const thumb = type === 'image'
            ? `<img src="${src}" alt="" width="45" height="60">`
            : `<video src="${src}" muted playsinline width="45" height="60" loading="lazy"></video>`;
        return `
            <div class="bookmark-item" onclick="scrollToPost('${String(id).replace(/'/g, "\\'")}')">
                <div class="bookmark-thumb">${thumb}</div>
                <div class="bookmark-info">
                    <div class="bookmark-title">#${id}</div>
                    <div class="bookmark-date">${safeDate}</div>
                </div>
                <button class="bookmark-remove" type="button" onclick="event.stopPropagation(); removeBookmark('${String(id).replace(/'/g, "\\'")}');">×</button>
            </div>
        `;
    }).join('');

    // Форсим загрузку мини-видео, чтобы на мобильных появился кадр предпросмотра
    try {
        const videos = list.querySelectorAll('video');
        videos.forEach(v => {
            try { v.load(); } catch (e) {}
        });
    } catch (e) {}
}

function closeBookmarks() {
    const modal = document.getElementById('bookmarksModal');
    if (modal) modal.style.display = 'none';
}

function removeBookmark(postId) {
    const pid = String(postId || '');
    if (!pid) return;
    const bookmarks = getBookmarks().filter(b => String(b.id) !== pid);
    saveBookmarks(bookmarks);
    openBookmarks();
}

function scrollToPost(postId) {
    const pid = String(postId || '');
    if (!pid) return;
    closeBookmarks();
    const post = document.querySelector(`.post[data-id="${pid}"]`);
    if (post) {
        post.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function openHistory() {
    const modal = document.getElementById('historyModal');
    if (modal) modal.style.display = 'flex';
}

function closeHistory() {
    const modal = document.getElementById('historyModal');
    if (modal) modal.style.display = 'none';
}

async function openMyVideos() {
    const modal = document.getElementById('myVideosModal');
    if (!modal) return;
    modal.style.display = 'flex';

    const list = document.getElementById('myVideosList');
    const totals = document.getElementById('myVideosTotals');
    if (list) list.innerHTML = '<div style="text-align:center; padding:20px; color:#666;">Загрузка...</div>';
    if (totals) totals.innerHTML = '';

    try {
        const response = await fetch('my_videos.php');

        let data = null;
        try { data = await response.json(); } catch (e) { data = null; }

        if (!response.ok) {
            const msg = (data && data.error) ? String(data.error) : ('Ошибка загрузки: ' + response.status);
            if (list) list.innerHTML = `<div style="text-align:center; padding:20px; color:#fc7b07;">${msg}</div>`;
            showToast(msg);
            return;
        }

        if (!Array.isArray(data)) {
            const msg = (data && data.error) ? String(data.error) : 'Ошибка';
            if (list) list.innerHTML = `<div style="text-align:center; padding:20px; color:#fc7b07;">${msg}</div>`;
            return;
        }

        const totalLikes = data.reduce((s, v) => s + (parseInt(v.likes, 10) || 0), 0);
        const totalViews = data.reduce((s, v) => s + (parseInt(v.views, 10) || 0), 0);
        const totalComments = data.reduce((s, v) => s + (parseInt(v.comments_count, 10) || 0), 0);
        if (totals) totals.innerHTML = `
            <span class="mv-total-chip views">👁 Просмотры: ${totalViews}</span>
            <span class="mv-total-chip likes">❤ Лайки: ${totalLikes}</span>
            <span class="mv-total-chip comments">💬 Комментарии: ${totalComments}</span>
        `;

        if (data.length === 0) {
            if (list) list.innerHTML = '<div style="text-align:center; padding:20px; color:#666;">Пока нет видео</div>';
            return;
        }

        if (list) {
            const statusClass = (s) => {
                const v = String(s || '').toLowerCase();
                if (v === 'approved') return 'approved';
                if (v === 'pending') return 'pending';
                if (v === 'rejected') return 'rejected';
                return '';
            };

            list.innerHTML = `<div class="mv-grid">${data.map(v => {
                const id = v.id ?? '';
                const filename = v.filename ?? '';
                const type = v.type ?? 'video';
                const status = v.status ?? '';
                const src = filename ? ('media/' + encodeURIComponent(filename)) : '';
                const likes = parseInt(v.likes, 10) || 0;
                const views = parseInt(v.views, 10) || 0;
                const comments = parseInt(v.comments_count, 10) || 0;
                const st = statusClass(status);
                const thumb = type === 'image'
                    ? `<img src="${src}" alt="" width="84" height="112">`
                    : `<video src="${src}" muted playsinline width="84" height="112" loading="lazy"></video>`;
                return `
                    <div class="ui-card mv-item">
                        <div class="mv-thumb" onclick="openMyPreviewMedia('${type}','${src}')">${thumb}</div>
                        <div class="mv-right">
                            <div class="mv-top">
                                <div class="mv-title">#${id} <span class="ui-chip status-chip ${st}">${status || '—'}</span></div>
                            </div>
                            <div class="mv-stats">
                                <span class="ui-chip">👁 ${views}</span>
                                <span class="ui-chip">❤ ${likes}</span>
                                <span class="ui-chip">💬 ${comments}</span>
                            </div>
                            <div class="mv-actions">
                                <button class="mv-open" type="button" onclick="event.stopPropagation(); openMyPreviewMedia('${type}','${src}')">Смотреть</button>
                                <button class="mv-delete" type="button" onclick="event.stopPropagation(); deleteMyVideo(${parseInt(id,10)||0});">Удалить</button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('')}</div>`;

            // На мобильных браузерах видео внутри только что показанной модалки
            // иногда не отрисовывают превью-кадр, пока явно не вызвать load().
            try {
                const videos = list.querySelectorAll('video');
                videos.forEach(v => {
                    try { v.load(); } catch (e) {}
                });
            } catch (e) {}
        }
    } catch (e) {
        if (list) list.innerHTML = '<div style="text-align:center; padding:20px; color:#fc7b07;">Ошибка загрузки</div>';
    }
}

function openMyPreviewMedia(type, src) {
    const overlay = document.getElementById('myPreviewOverlay');
    const inner = document.getElementById('myPreviewInner');
    if (!overlay || !inner) return;
    const t = String(type || 'video');
    const s = String(src || '');
    if (!s) return;

    if (t === 'image') {
        inner.innerHTML = `<img src="${s}" alt="" style="max-width:92vw; max-height:82vh; width:auto; height:auto; border-radius:16px; border:1px solid rgba(255,255,255,.14); object-fit:contain;">`;
    } else {
        inner.innerHTML = `<video src="${s}" controls playsinline muted style="max-width:92vw; max-height:82vh; width:100%; height:auto; border-radius:16px; border:1px solid rgba(255,255,255,.14); background:#000; object-fit:contain;"></video>`;
        // На мобилках форсим загрузку и первый кадр
        const v = inner.querySelector('video');
        if (v) {
            try {
                v.load();
            } catch (e) {}
        }
    }
    overlay.style.display = 'flex';
}

function closeMyPreview(e) {
    if (e && e.target && e.target.closest && e.target.closest('.my-preview-box')) return;
    const overlay = document.getElementById('myPreviewOverlay');
    const inner = document.getElementById('myPreviewInner');
    if (overlay) overlay.style.display = 'none';
    if (inner) inner.innerHTML = '';
}

function stopMyPreviewClick(e) {
    if (e && e.stopPropagation) e.stopPropagation();
}

async function deleteMyVideo(videoId) {
    const id = parseInt(videoId, 10);
    if (!Number.isFinite(id) || id <= 0) return;

    if (!confirm('Удалить видео навсегда?')) return;

    try {
        const response = await fetch('delete_video.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ video_id: id })
        });

        let result = null;
        try { result = await response.json(); } catch (e) { result = null; }

        if (!response.ok || !result || !result.success) {
            const msg = (result && result.error) ? String(result.error) : ('Ошибка удаления: ' + response.status);
            showToast(msg);
            return;
        }

        showToast('Удалено');
        await openMyVideos();
    } catch (e) {
        showToast('Ошибка сети');
    }
}

function closeMyVideos() {
    const modal = document.getElementById('myVideosModal');
    if (modal) modal.style.display = 'none';
}

let __sharePostId = null;
let __sharePostFilename = null;

function openShare(postId, filename) {
    __sharePostId = String(postId || '');
    __sharePostFilename = String(filename || '');
    const modal = document.getElementById('shareModal');
    if (modal) modal.style.display = 'flex';
}

function closeShare() {
    const modal = document.getElementById('shareModal');
    if (modal) modal.style.display = 'none';
    __sharePostId = null;
    __sharePostFilename = null;
}

function buildShareUrl() {
    const base = window.location.origin + window.location.pathname;
    if (!__sharePostId) return base;
    const u = new URL(base);
    u.searchParams.set('p', __sharePostId);
    return u.toString();
}

async function shareTo(platform) {
    const url = buildShareUrl();
    const text = __sharePostId ? `MasterHacks #${__sharePostId}` : 'MasterHacks';

    try {
        if (navigator.share && (platform === 'native')) {
            await navigator.share({ title: 'MasterHacks', text, url });
            closeShare();
            return;
        }
    } catch (e) {
        // ignore
    }

    const encodedUrl = encodeURIComponent(url);
    const encodedText = encodeURIComponent(text);

    if (platform === 'telegram') {
        window.open(`https://t.me/share/url?url=${encodedUrl}&text=${encodedText}`, '_blank');
        closeShare();
        return;
    }

    if (platform === 'vk') {
        window.open(`https://vk.com/share.php?url=${encodedUrl}`, '_blank');
        closeShare();
        return;
    }

    if (platform === 'whatsapp') {
        window.open(`https://wa.me/?text=${encodeURIComponent(text + '\n' + url)}`, '_blank');
        closeShare();
        return;
    }

    if (platform === 'download') {
        if (!__sharePostFilename) {
            showToast('Файл не найден');
            closeShare();
            return;
        }
        const a = document.createElement('a');
        a.href = 'media/' + encodeURIComponent(__sharePostFilename);
        a.download = __sharePostFilename;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        closeShare();
        return;
    }

    if (platform === 'max') {
        // Если у MAX нет публичного share-url, делаем безопасный fallback: копируем ссылку
        try {
            await navigator.clipboard.writeText(url);
            showToast('Ссылка скопирована');
        } catch (e) {
            showToast('Скопируйте ссылку вручную');
        }
        closeShare();
        return;
    }

    if (platform === 'copy') {
        try {
            await navigator.clipboard.writeText(url);
            showToast('Ссылка скопирована');
        } catch (e) {
            // fallback
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showToast('Ссылка скопирована');
            } catch (e2) {
                showToast('Скопируйте ссылку вручную');
            }
            document.body.removeChild(ta);
        }
        closeShare();
        return;
    }

    showToast('Неизвестный вариант');
}

function openAdminPanel() {
    const modal = document.getElementById('adminModal');
    if (!modal) return;
    modal.style.display = 'flex';
    if (typeof loadPendingVideos === 'function') loadPendingVideos();
    if (typeof loadAdminStats === 'function') loadAdminStats();
}

function closeAdminPanel() {
    const modal = document.getElementById('adminModal');
    if (modal) modal.style.display = 'none';
}

function clearCache() {
    fetch('?refresh_cache=1')
        .then(() => {
            showToast('Кэш очищен');
            setTimeout(() => window.location.reload(), 500);
        })
        .catch(() => showToast('Ошибка'));
}

function logout() {
    fetch('logout.php')
        .then(() => window.location.reload())
        .catch(() => window.location.reload());
}

/* ---------- COMMENTS ---------- */
function openComments(postId) {
    state.currentPostId = postId;
    state.replyTo = null;
    const replyBar = document.getElementById('replyBar');
    if (replyBar) replyBar.style.display = 'none';
    const modal = document.getElementById('commentsModal');
    modal.style.display = 'flex';
    loadComments(postId);
    setTimeout(() => {
        document.getElementById('commentInput').focus();
    }, 100);
}

function closeComments() {
    document.getElementById('commentsModal').style.display = 'none';
}

async function loadComments(postId) {
    const list = document.getElementById('commentsList');
    list.innerHTML = '<div style="text-align:center; padding:20px; color:#666;">Загрузка...</div>';
    
    try {
        const response = await fetch(`get_comments.php?post_id=${encodeURIComponent(postId)}`);
        const comments = await response.json();
        
        if (!Array.isArray(comments) || comments.length === 0) {
            list.innerHTML = '<div style="text-align:center; padding:20px; color:#666;">Нет комментариев</div>';
            return;
        }

        const byId = new Map();
        const roots = [];

        comments.forEach(c => {
            const item = {
                id: c.id,
                parent_id: c.parent_id ?? null,
                author: c.author || 'Аноним',
                text: c.text || '',
                date: c.date || '',
                replies: []
            };
            byId.set(String(item.id), item);
        });

        byId.forEach(item => {
            if (item.parent_id) {
                const parent = byId.get(String(item.parent_id));
                if (parent) parent.replies.push(item);
                else roots.push(item);
            } else {
                roots.push(item);
            }
        });

        const renderItem = (item, isReply) => {
            const a = String(item.author || 'А');
            const authorChar = a.charAt(0);
            return `
                <div class="comment-item" style="${isReply ? 'margin-left:34px; opacity:0.95;' : ''}">
                    <div class="comment-avatar" style="${isReply ? 'width:22px;height:22px;font-size:10px;' : ''}">${authorChar}</div>
                    <div class="comment-content">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                            <div class="comment-author">${item.author || 'Аноним'}</div>
                            <button type="button" onclick="replyToComment(${Number(item.id)}, '${encodeURIComponent(item.author || 'Аноним')}')" style="background:transparent;border:0;color:#fc7b07;font-size:11px;font-weight:800;cursor:pointer;">Ответить</button>
                        </div>
                        <div class="comment-text">${item.text}</div>
                        <div class="comment-time">${item.date}</div>
                    </div>
                </div>
            `;
        };

        list.innerHTML = roots.map(root => {
            const repliesHtml = (root.replies || []).map(r => renderItem(r, true)).join('');
            return renderItem(root, false) + repliesHtml;
        }).join('');
    } catch (error) {
        list.innerHTML = '<div style="text-align:center; padding:20px; color:#fc7b07;">Ошибка</div>';
    }
}

function replyToComment(commentId, encodedAuthor) {
    const id = parseInt(commentId, 10);
    if (!Number.isFinite(id) || id <= 0) return;
    const author = decodeURIComponent(encodedAuthor || '');
    state.replyTo = { id, author: author || 'Аноним' };
    const bar = document.getElementById('replyBar');
    const label = document.getElementById('replyLabel');
    if (label) label.textContent = 'Ответ: ' + (state.replyTo.author || 'Аноним');
    if (bar) bar.style.display = 'flex';
    document.getElementById('commentInput')?.focus();
}

function cancelReply() {
    state.replyTo = null;
    const bar = document.getElementById('replyBar');
    if (bar) bar.style.display = 'none';
}

async function sendComment() {
    const input = document.getElementById('commentInput');
    const text = input.value.trim();
    if (!text || !state.currentPostId) return;
    
    const now = Date.now();
    if (state.lastActionTime.comment && now - state.lastActionTime.comment < 1000) {
        return;
    }
    state.lastActionTime.comment = now;
    
    try {
        const response = await fetch('comment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                post_id: parseInt(state.currentPostId),
                text: text,
                parent_id: state.replyTo ? state.replyTo.id : null
            })
        });
        const result = await response.json();
        
        if (result.success) {
            input.value = '';
            cancelReply();
            loadComments(state.currentPostId);
            const commentBtn = document.querySelector(`[data-id="${state.currentPostId}"] .comment-btn .action-count`);
            if (commentBtn) {
                commentBtn.textContent = (parseInt(commentBtn.textContent) || 0) + 1;
            }
            showToast('Комментарий добавлен');
        } else {
            showToast('Ошибка');
        }
    } catch (error) {
        showToast('Ошибка');
    }
}

async function loadAdminStats() {
    try {
        const response = await fetch('admin_stats.php');
        if (!response.ok) {
            if (response.status === 403) {
                showToast('Нет доступа к админ-панели. Открой сайт с admin_key');
                return;
            }
            showToast('Ошибка загрузки статистики');
            return;
        }

        const stats = await response.json();

        const statsHtml = `
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 10px;">
                <div style="background: rgba(252,123,7,0.1); padding: 10px; border-radius: 8px;">
                    <div style="font-size: 24px; color: #fc7b07;">${stats.total_videos}</div>
                    <div style="font-size: 12px;">Всего</div>
                </div>
                <div style="background: rgba(255,193,7,0.1); padding: 10px; border-radius: 8px;">
                    <div style="font-size: 24px; color: #ffc107;">${stats.pending_videos}</div>
                    <div style="font-size: 12px;">На модерации</div>
                </div>
                <div style="background: rgba(40,167,69,0.1); padding: 10px; border-radius: 8px;">
                    <div style="font-size: 24px; color: #28a745;">${stats.approved_videos}</div>
                    <div style="font-size: 12px;">Одобрено</div>
                </div>
                <div style="background: rgba(220,53,69,0.1); padding: 10px; border-radius: 8px;">
                    <div style="font-size: 24px; color: #dc3545;">${stats.rejected_videos}</div>
                    <div style="font-size: 12px;">Отклонено</div>
                </div>
            </div>
        `;
        document.getElementById('adminStats').innerHTML = statsHtml;
    } catch (error) {
        document.getElementById('adminStats').innerHTML = 'Ошибка загрузки статистики';
    }
}

async function loadPendingVideos() {
    try {
        const response = await fetch('admin_pending.php');
        if (!response.ok) {
            if (response.status === 403) {
                const el = document.getElementById('adminPanelContent');
                if (el) {
                    el.innerHTML = '<h4>Видео на модерации:</h4><p>Нет доступа. Открой сайт с admin_key</p>';
                }
                showToast('Нет доступа к модерации. Открой сайт с admin_key');
                return;
            }
            showToast('Ошибка загрузки модерации');
            return;
        }
        const videos = await response.json();
        window.__pendingVideos = Array.isArray(videos) ? videos : [];
        window.__pendingPreviewIndex = -1;
        window.__pendingThumbsBuilt = false;
        let html = '<div class="ui-row"><h4 style="margin:0;">Видео на модерации</h4><button class="ui-btn" type="button" onclick="openAdminPanel()" style="padding:8px 10px;">Обновить</button></div>';
        if (!Array.isArray(videos) || videos.length === 0) {
            html += '<div class="ui-card" style="margin-top:10px;text-align:center;">Нет видео на модерации</div>';
        } else {
            html += '<div class="pending-admin-list" style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">';
            videos.forEach(video => {
                const author = video.author_name ? video.author_name : '—';
                const filename = video.filename || '';
                const isVideo = (video.file_type === 'video') || (/\.(mp4|webm|mov|m4v)$/i.test(filename));
                const mediaSrc = 'media/' + filename;
                const status = 'pending';
                html += `
                    <div class="ui-card">
                        <div class="ui-row">
                            <div style="font-weight:900;">#${video.id}</div>
                            <span class="ui-chip status-chip pending">${status}</span>
                        </div>
                        <div class="ui-meta">
                            <span class="ui-chip">👤 ${author}${video.author_username ? ' (@' + video.author_username + ')' : ''}</span>
                            <span class="ui-chip">📅 ${video.created_at || '—'}</span>
                        </div>
                        <button class="pending-media-btn" type="button" onclick="openPendingPreviewById(event, ${video.id})">
                          ${isVideo
                            ? `<video src="${mediaSrc}" muted playsinline preload="metadata" width="100%" height="180" loading="lazy"></video>`
                            : `<img src="${mediaSrc}" alt="" width="100%" height="180" loading="lazy">`
                          }
                        </button>
                        <div class="ui-actions">
                            <button class="ui-btn success" type="button" onclick="event.stopPropagation(); approveVideo(${video.id})">Одобрить</button>
                            <button class="ui-btn danger" type="button" onclick="event.stopPropagation(); rejectVideo(${video.id})">Отклонить</button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }
        document.getElementById('adminPanelContent').innerHTML = html;
        
        // Force load video thumbnails on mobile
        try {
            const videos = document.getElementById('adminPanelContent').querySelectorAll('video');
            videos.forEach(v => {
                try { v.load(); } catch (e) {}
            });
        } catch (e) {}
    } catch (error) {
        showToast('Ошибка загрузки видео');
    }
}

function stopPendingPreviewClick(e) {
    if (!e) return;
    e.preventDefault();
    e.stopPropagation();
}

function openPendingPreviewById(e, id) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const list = Array.isArray(window.__pendingVideos) ? window.__pendingVideos : [];
    const idx = list.findIndex(v => String(v.id) === String(id));
    if (idx >= 0) openPendingPreview(idx);
}

function buildPendingThumbs() {
    if (window.__pendingThumbsBuilt) return;
    const thumbs = document.getElementById('pendingPreviewThumbs');
    const list = Array.isArray(window.__pendingVideos) ? window.__pendingVideos : [];
    if (!thumbs) return;
    thumbs.innerHTML = '';

    list.forEach((video, idx) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'pending-thumb';
        b.onclick = (e) => {
            stopPendingPreviewClick(e);
            openPendingPreview(idx);
        };

        const filename = video.filename || '';
        const isVideo = (video.file_type === 'video') || (/\.(mp4|webm|mov|m4v)$/i.test(filename));
        const mediaSrc = 'media/' + filename;

        if (isVideo) {
            const v = document.createElement('video');
            v.src = mediaSrc;
            v.muted = true;
            v.playsInline = true;
            v.preload = 'metadata';
            v.width = 72;
            v.height = 120;
            b.appendChild(v);
            try { v.load(); } catch (e) {}
        } else {
            const img = document.createElement('img');
            img.src = mediaSrc;
            img.alt = '';
            img.width = 72;
            img.height = 120;
            b.appendChild(img);
        }

        thumbs.appendChild(b);
    });

    window.__pendingThumbsBuilt = true;
}

function setActivePendingThumb(idx) {
    const thumbs = document.getElementById('pendingPreviewThumbs');
    if (!thumbs) return;
    const nodes = thumbs.querySelectorAll('.pending-thumb');
    nodes.forEach((n, i) => {
        if (i === idx) n.classList.add('active');
        else n.classList.remove('active');
    });
    const active = nodes[idx];
    if (active && typeof active.scrollIntoView === 'function') {
        active.scrollIntoView({ block: 'nearest', inline: 'center' });
    }
}

function renderPendingPreview() {
    const overlay = document.getElementById('pendingPreviewOverlay');
    const inner = document.getElementById('pendingPreviewInner');
    const list = Array.isArray(window.__pendingVideos) ? window.__pendingVideos : [];
    if (!overlay || !inner) return;
    if (window.__pendingPreviewIndex < 0 || window.__pendingPreviewIndex >= list.length) return;

    const video = list[window.__pendingPreviewIndex];
    const filename = video.filename || '';
    const isVideo = (video.file_type === 'video') || (/\.(mp4|webm|mov|m4v)$/i.test(filename));
    const mediaSrc = 'media/' + filename;

    inner.innerHTML = '';
    if (isVideo) {
        const v = document.createElement('video');
        v.src = mediaSrc;
        v.controls = true;
        v.playsInline = true;
        v.autoplay = true;
        v.preload = 'auto';
        v.style.width = '100%';
        v.style.height = 'auto';
        v.style.maxHeight = '75vh';
        v.style.display = 'block';
        v.style.objectFit = 'contain';
        inner.appendChild(v);
        try { v.load(); } catch (e) {}
    } else {
        const img = document.createElement('img');
        img.src = mediaSrc;
        img.alt = '';
        img.style.width = '100%';
        img.style.height = 'auto';
        img.style.maxHeight = '75vh';
        img.style.display = 'block';
        img.style.objectFit = 'contain';
        inner.appendChild(img);
    }

    buildPendingThumbs();
    setActivePendingThumb(window.__pendingPreviewIndex);
    overlay.style.display = 'flex';
}

function openPendingPreview(index) {
    const i = Number(index);
    const list = Array.isArray(window.__pendingVideos) ? window.__pendingVideos : [];
    if (!Number.isFinite(i) || i < 0 || i >= list.length) return;
    window.__pendingPreviewIndex = i;
    renderPendingPreview();
}

function prevPendingPreview(e) {
    stopPendingPreviewClick(e);
    const list = Array.isArray(window.__pendingVideos) ? window.__pendingVideos : [];
    if (list.length === 0) return;
    if (window.__pendingPreviewIndex <= 0) window.__pendingPreviewIndex = list.length - 1;
    else window.__pendingPreviewIndex -= 1;
    renderPendingPreview();
}

function nextPendingPreview(e) {
    stopPendingPreviewClick(e);
    const list = Array.isArray(window.__pendingVideos) ? window.__pendingVideos : [];
    if (list.length === 0) return;
    if (window.__pendingPreviewIndex >= list.length - 1) window.__pendingPreviewIndex = 0;
    else window.__pendingPreviewIndex += 1;
    renderPendingPreview();
}

function closePendingPreview(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const overlay = document.getElementById('pendingPreviewOverlay');
    const inner = document.getElementById('pendingPreviewInner');
    if (inner) inner.innerHTML = '';
    if (overlay) overlay.style.display = 'none';
    window.__pendingPreviewIndex = -1;
}

document.addEventListener('keydown', (e) => {
    const overlay = document.getElementById('pendingPreviewOverlay');
    if (!overlay || overlay.style.display !== 'flex') return;
    if (e.key === 'Escape') closePendingPreview(e);
    if (e.key === 'ArrowLeft') prevPendingPreview(e);
    if (e.key === 'ArrowRight') nextPendingPreview(e);
});

async function approveVideo(videoId) {
    if (!confirm('Одобрить это видео?')) return;
    try {
        const response = await fetch('admin_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'approve', video_id: videoId})
        });

        let result = null;
        let raw = '';
        try {
            raw = await response.text();
            result = raw ? JSON.parse(raw) : null;
        } catch (e) {
            result = null;
        }

        if (!response.ok) {
            const msg = (result && result.error) ? result.error : (raw || ('HTTP ' + response.status));
            showToast(msg);
            return;
        }

        if (result && result.success) {
            showToast('Видео одобрено');
            loadPendingVideos();
            loadAdminStats();
            try {
                const feedEl = document.getElementById('feed');
                const currentScroll = feedEl ? feedEl.scrollTop : 0;
                if (typeof loadNewPosts === 'function') {
                    loadNewPosts(currentScroll);
                } else {
                    setTimeout(() => window.location.reload(), 400);
                }
            } catch (e) {
                setTimeout(() => window.location.reload(), 400);
            }
        } else {
            showToast((result && result.error) ? result.error : 'Ошибка');
        }
    } catch (error) {
        showToast('Ошибка сети');
    }
}

async function rejectVideo(videoId) {
    if (!confirm('Отклонить это видео?')) return;
    try {
        const response = await fetch('admin_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'reject', video_id: videoId})
        });

        let result = null;
        let raw = '';
        try {
            raw = await response.text();
            result = raw ? JSON.parse(raw) : null;
        } catch (e) {
            result = null;
        }

        if (!response.ok) {
            const msg = (result && result.error) ? result.error : (raw || ('HTTP ' + response.status));
            showToast(msg);
            return;
        }

        if (result && result.success) {
            showToast('Видео отклонено');
            loadPendingVideos();
            loadAdminStats();
        } else {
            showToast((result && result.error) ? result.error : 'Ошибка');
        }
    } catch (error) {
        showToast('Ошибка сети');
    }
}

async function refreshAllCache() {
    const toast = document.getElementById('toast');
    toast.textContent = 'Обновление всех кэшей...';
    toast.classList.add('show');
    try {
        await fetch('?refresh_cache=1');
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    } catch (error) {
        toast.textContent = 'Ошибка обновления';
    }
}

async function deleteOldVideos() {
    if (!confirm('Удалить видео старше 30 дней?')) return;
    try {
        const response = await fetch('admin_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'delete_old'})
        });
        const result = await response.json();
        if (result.success) {
            showToast(`Удалено ${result.deleted || 0} видео`);
            loadAdminStats();
        } else {
            showToast('Ошибка');
        }
    } catch (error) {
        showToast('Ошибка сети');
    }
}

// Закрытие модальных окон при клике вне
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});

// Enter для отправки комментария
document.getElementById('commentInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendComment();
    }
});
</script>
</body>
</html>
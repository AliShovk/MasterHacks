<?php
// ── SEO: GET view.php?id=N → HTML page with Open Graph & Schema.org ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $videoId = (int)$_GET['id'];
    if ($videoId <= 0) {
        http_response_code(400);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Bad Request</title></head><body><h1>400 Bad Request</h1></body></html>';
        exit;
    }

    require_once __DIR__ . '/config/database.php';
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare(
            'SELECT id, filename, file_type, description, thumbnail_url, views, likes,
                    COALESCE(published_at, created_at) AS published_at
             FROM videos
             WHERE id = :id AND status = \'approved\''
        );
        $stmt->execute([':id' => $videoId]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Server Error</title></head><body><h1>500 Internal Server Error</h1></body></html>';
        exit;
    }

    if (!$video) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>404 — Видео не найдено</h1></body></html>';
        exit;
    }

    // ── SEO metadata preparation ──
    $videoTitle = !empty($video['description'])
        ? mb_substr(strip_tags($video['description']), 0, 120)
        : (pathinfo($video['filename'], PATHINFO_FILENAME) ?: 'Видео');
    $videoDesc   = !empty($video['description'])
        ? mb_substr(strip_tags($video['description']), 0, 200)
        : 'Смотри ' . $videoTitle . ' на MasterHacks';
    $thumbUrl    = !empty($video['thumbnail_url'])
        ? $video['thumbnail_url']
        : 'https://masterhacks.ru/gk.png';
    $videoUrl    = 'https://masterhacks.ru/media/' . rawurlencode($video['filename']);
    $pageUrl     = 'https://masterhacks.ru/view.php?id=' . $videoId;
    $uploadDate  = date('c', strtotime($video['published_at'] ?? 'now'));
    $views       = (int)($video['views'] ?? 0);
    $likes       = (int)($video['likes'] ?? 0);

    // Escape for safe HTML attribute usage
    $eTitle       = htmlspecialchars($videoTitle, ENT_QUOTES, 'UTF-8');
    $eDesc        = htmlspecialchars($videoDesc, ENT_QUOTES, 'UTF-8');
    $eThumbUrl    = htmlspecialchars($thumbUrl, ENT_QUOTES, 'UTF-8');
    $eVideoUrl    = htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8');
    $ePageUrl     = htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8');

    // Redirect to index.php?p=ID for human visitors (JavaScript redirect)
    $humanUrl = 'https://masterhacks.ru/?p=' . $videoId;

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?= $eTitle ?> — MasterHacks</title>
    <meta name="description" content="<?= $eDesc ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= $eTitle ?>">
    <meta property="og:description" content="<?= $eDesc ?>">
    <meta property="og:image" content="<?= $eThumbUrl ?>">
    <meta property="og:url" content="<?= $ePageUrl ?>">
    <meta property="og:type" content="video.other">
    <meta property="og:video" content="<?= $eVideoUrl ?>">
    <meta property="og:video:type" content="video/mp4">
    <meta property="og:video:width" content="720">
    <meta property="og:video:height" content="1280">
    <meta property="og:site_name" content="MasterHacks">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="player">
    <meta name="twitter:title" content="<?= $eTitle ?>">
    <meta name="twitter:description" content="<?= $eDesc ?>">
    <meta name="twitter:image" content="<?= $eThumbUrl ?>">
    <meta name="twitter:player" content="<?= $eVideoUrl ?>">
    <meta name="twitter:player:width" content="720">
    <meta name="twitter:player:height" content="1280">

    <!-- Schema.org VideoObject JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "VideoObject",
      "name": <?= json_encode($videoTitle, JSON_UNESCAPED_UNICODE) ?>,
      "description": <?= json_encode($videoDesc, JSON_UNESCAPED_UNICODE) ?>,
      "thumbnailUrl": <?= json_encode($thumbUrl, JSON_UNESCAPED_UNICODE) ?>,
      "contentUrl": <?= json_encode($videoUrl, JSON_UNESCAPED_UNICODE) ?>,
      "embedUrl": <?= json_encode($videoUrl, JSON_UNESCAPED_UNICODE) ?>,
      "uploadDate": <?= json_encode($uploadDate, JSON_UNESCAPED_UNICODE) ?>,
      "interactionStatistic": [
        {
          "@type": "InteractionCounter",
          "interactionType": { "@type": "WatchAction" },
          "userInteractionCount": <?= $views ?>
        },
        {
          "@type": "InteractionCounter",
          "interactionType": { "@type": "LikeAction" },
          "userInteractionCount": <?= $likes ?>
        }
      ]
    }
    </script>

    <!-- Redirect humans to the feed page -->
    <script>
        window.location.replace(<?= json_encode($humanUrl, JSON_UNESCAPED_UNICODE) ?>);
    </script>
    <noscript>
        <meta http-equiv="refresh" content="0;url=<?= $ePageUrl ?>">
    </noscript>
</head>
<body>
    <p>Перенаправление на <a href="<?= htmlspecialchars($humanUrl, ENT_QUOTES, 'UTF-8') ?>">MasterHacks</a>…</p>
</body>
</html>
    <?php
    exit;
}

// ── POST: existing view-counting API (unchanged) ──
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();

if (!file_exists(__DIR__ . '/config/database.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database not configured'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/config/database.php';

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);
if (!is_array($input) || !isset($input['post_id']) || !is_numeric($input['post_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid post ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

$postId = (int)$input['post_id'];
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Post ID must be positive'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ttlSeconds = 600;
$key = 'view_' . $postId;
$now = time();

try {
    $pdo = getDatabaseConnection();

    $sel0 = $pdo->prepare('SELECT views FROM videos WHERE id = :id');
    $sel0->execute([':id' => $postId]);
    $row0 = $sel0->fetch(PDO::FETCH_ASSOC);

    if (!$row0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Post not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_SESSION[$key]) && is_int($_SESSION[$key]) && ($now - $_SESSION[$key]) < $ttlSeconds) {
        echo json_encode([
            'success' => true,
            'skipped' => true,
            'views' => (int)$row0['views']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION[$key] = $now;

    $updated = false;
    $lastErr = null;

    for ($i = 0; $i < 2; $i++) {
        try {
            $upd = $pdo->prepare('UPDATE videos SET views = views + 1 WHERE id = :id');
            $upd->execute([':id' => $postId]);

            if ($upd->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Post not found'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $updated = true;
            $lastErr = null;
            break;
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
            if ($i === 0 && preg_match('/Unknown column\s+\'?views\'?/i', $lastErr)) {
                $pdo->exec('ALTER TABLE videos ADD COLUMN views INT NOT NULL DEFAULT 0');
                continue;
            }
            break;
        }
    }

    $sel = $pdo->prepare('SELECT views FROM videos WHERE id = :id');
    $sel->execute([':id' => $postId]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => $updated,
        'views' => $row ? (int)$row['views'] : null,
        'error' => $updated ? null : $lastErr
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

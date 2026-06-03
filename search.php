<?php
/**
 * MasterHacks - Search page
 * Searches videos by description using FULLTEXT index
 */

require_once __DIR__ . '/config/database.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];
$searched = false;
$error = null;

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if (mb_strlen($q) >= 2) {
    $searched = true;
    try {
        $pdo = getDatabaseConnection();
        $searchTerm = $q . '*';

        $sql = "SELECT v.id, v.filename, v.file_type, v.description, v.likes, v.views,
                       COALESCE(v.published_at, v.created_at) AS published_at,
                       a.username
                FROM videos v
                LEFT JOIN authors a ON a.telegram_id = v.telegram_id
                WHERE v.status = 'approved'
                  AND MATCH(v.description) AGAINST(:q IN BOOLEAN MODE)
                ORDER BY COALESCE(v.published_at, v.created_at) DESC
                LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q' => $searchTerm]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        try {
            $stmt = $pdo->prepare(
                "SELECT v.id, v.filename, v.file_type, v.description, v.likes, v.views,
                        COALESCE(v.published_at, v.created_at) AS published_at,
                        a.username
                 FROM videos v
                 LEFT JOIN authors a ON a.telegram_id = v.telegram_id
                 WHERE v.status = 'approved'
                   AND v.description LIKE :q
                 ORDER BY COALESCE(v.published_at, v.created_at) DESC
                 LIMIT 50"
            );
            $stmt->execute([':q' => '%' . $q . '%']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) {
            $error = 'Search error. Try again later.';
            $results = [];
        }
    }
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Поиск: <?= h($q) ?> | MasterHacks</title>
<meta name="description" content="Результаты поиска по запросу «<?= h($q) ?>» на MasterHacks.">
<meta name="robots" content="noindex, follow">
<link rel="icon" type="image/png" href="/gk.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#000;color:#fff;min-height:100vh}
a{color:#fc7b07;text-decoration:none}
a:hover{text-decoration:underline}
.header{position:sticky;top:0;z-index:100;background:rgba(0,0,0,.92);backdrop-filter:blur(12px);border-bottom:1px solid rgba(252,123,7,.15);padding:10px 16px;display:flex;align-items:center;gap:12px}
.logo{height:36px;width:auto;flex-shrink:0}
.back-link{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#fff;font-size:14px;flex-shrink:0;transition:background .2s}
.back-link:hover{background:rgba(252,123,7,.2);text-decoration:none}
.search-form{flex:1;display:flex;gap:8px;min-width:0}
.search-input{flex:1;min-width:0;background:rgba(255,255,255,.08);border:1px solid rgba(252,123,7,.25);border-radius:20px;padding:8px 16px;color:#fff;font-size:15px;outline:none;transition:border-color .2s}
.search-input:focus{border-color:#fc7b07}
.search-input::placeholder{color:rgba(255,255,255,.4)}
.search-btn{width:38px;height:38px;border-radius:50%;flex-shrink:0;background:rgba(252,123,7,.85);border:1px solid rgba(255,255,255,.2);color:#fff;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s}
.search-btn:hover{background:#fc7b07}
.container{max-width:720px;margin:0 auto;padding:16px}
.results-info{font-size:13px;color:rgba(255,255,255,.6);margin-bottom:16px}
.no-results{text-align:center;padding:48px 16px;color:rgba(255,255,255,.5)}
.no-results i{font-size:48px;color:rgba(252,123,7,.3);margin-bottom:16px;display:block}
.no-results p{font-size:16px;margin-top:8px}
.error-msg{text-align:center;padding:32px 16px;color:#dc3545;font-size:14px}
.result-item{display:flex;gap:12px;align-items:flex-start;padding:12px;margin-bottom:10px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:14px;transition:border-color .2s,background .2s}
.result-item:hover{border-color:rgba(252,123,7,.25);background:rgba(255,255,255,.05)}
.result-thumb{width:100px;height:130px;flex-shrink:0;border-radius:10px;overflow:hidden;background:#111;border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;position:relative}
.result-thumb video,.result-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.result-thumb .play-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:32px;height:32px;border-radius:50%;background:rgba(252,123,7,.8);display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;pointer-events:none}
.result-body{flex:1;min-width:0}
.result-title{font-size:14px;font-weight:600;line-height:1.3;margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.result-author{font-size:12px;color:#fc7b07;margin-bottom:6px}
.result-meta{display:flex;gap:12px;flex-wrap:wrap;font-size:11px;color:rgba(255,255,255,.5)}
.result-meta span{display:flex;align-items:center;gap:4px}
.result-meta i{font-size:10px}
@media(max-width:480px){.header{padding:8px 12px;gap:8px}.logo{height:28px}.result-thumb{width:80px;height:105px}.container{padding:12px}}
</style>
</head>
<body>
<header class="header">
<a href="/" class="back-link" title="На главную"><i class="fas fa-arrow-left"></i></a>
<img src="/gk.png" alt="MasterHacks" class="logo" onerror="this.style.display='none'">
<form class="search-form" method="get" action="/search">
<input type="text" name="q" class="search-input" value="<?= h($q) ?>" placeholder="Поиск видео..." autofocus>
<button type="submit" class="search-btn" title="Найти"><i class="fas fa-search"></i></button>
</form>
</header>
<div class="container">
<?php if ($error): ?>
<div class="error-msg"><?= h($error) ?></div>
<?php elseif ($searched): ?>
<?php if (count($results) > 0): ?>
<div class="results-info">Найдено: <?= count($results) ?> результат<?= count($results) === 1 ? '' : (count($results) >= 5 ? 'ов' : 'а') ?> по запросу «<?= h($q) ?>»</div>
<?php foreach ($results as $row):
    $mediaSrc = '/media/' . h($row['filename']);
    $isVideo = ($row['file_type'] === 'video');
    $desc = $row['description'] ?: pathinfo($row['filename'], PATHINFO_FILENAME);
    $author = $row['username'] ? '@' . h($row['username']) : 'MasterHacks';
    $date = date('d.m.Y', strtotime($row['published_at']));
    $likes = (int)($row['likes'] ?? 0);
    $views = (int)($row['views'] ?? 0);
?>
<a href="/view.php?id=<?= (int)$row['id'] ?>" class="result-item" style="color:inherit;text-decoration:none">
<div class="result-thumb">
<?php if ($isVideo): ?>
<video src="<?= $mediaSrc ?>" muted preload="metadata" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-video\\' style=\\'color:rgba(252,123,7,0.4);font-size:24px\\'></i>'"></video>
<div class="play-icon"><i class="fas fa-play"></i></div>
<?php else: ?>
<img src="<?= $mediaSrc ?>" alt="" loading="lazy" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-image\\' style=\\'color:rgba(252,123,7,0.4);font-size:24px\\'></i>'">
<?php endif; ?>
</div>
<div class="result-body">
<div class="result-title"><?= h($desc) ?></div>
<div class="result-author"><?= $author ?></div>
<div class="result-meta">
<span><i class="fas fa-calendar-alt"></i> <?= $date ?></span>
<span><i class="fas fa-heart"></i> <?= $likes ?></span>
<span><i class="fas fa-eye"></i> <?= $views ?></span>
</div>
</div>
</a>
<?php endforeach; ?>
<?php else: ?>
<div class="no-results"><i class="fas fa-search"></i><p>Ничего не найдено</p><p style="font-size:13px;margin-top:8px">Попробуйте изменить запрос</p></div>
<?php endif; ?>
<?php else: ?>
<div class="no-results"><i class="fas fa-search"></i><p>Введите запрос для поиска видео</p></div>
<?php endif; ?>
</div>
</body>
</html>

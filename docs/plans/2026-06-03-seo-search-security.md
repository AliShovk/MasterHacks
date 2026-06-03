# MasterHacks.ru — План улучшений (v1)

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.
> **CRITICAL CONSTRAINT:** Ни одно изменение НЕ должно замедлить загрузку видео в ленте. Критический путь: `get_posts.php` → MySQL → JSON → index.php → lazy-load видео из `/media/`. Этот путь НЕ трогаем.

**Goal:** Улучшить SEO, навигацию, безопасность и контент сайта — чисто аддитивными изменениями.

**Architecture:** Raw PHP + MariaDB, без фреймворка. Все новые функции — отдельные PHP-файлы. Никаких изменений в `get_posts.php` и `index.php` (кроме минимальных точечных правок, не затрагивающих рендеринг ленты).

**Tech Stack:** PHP 8.x, MySQL/MariaDB (PDO), Apache 2.4, Vanilla JS, инлайн CSS.

**DB Schema (ключевое):**
- `videos`: id, filename, file_type, description TEXT, tags JSON, views, likes, status, published_at, FULLTEXT idx_description
- `authors`: id, telegram_id, username, first_name, reputation_score
- `subscriptions`: subscriber_telegram_id, author_telegram_id

---

## Параллельные группы (можно делать одновременно)

### Группа A: SEO-страницы видео + sitemap + schema.org

#### Task A1: Динамический sitemap.xml

**Objective:** Генерировать sitemap со всеми approved видео (сейчас только 2 URL).

**Files:**
- Create: `sitemap.php` — динамический генератор
- Modify: `.htaccess` — реврайт `sitemap.xml` → `sitemap.php`

**Implementation:**
```php
<?php
// sitemap.php — динамическая генерация sitemap.xml
header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/config/database.php';

$pdo = getDatabaseConnection();
$stmt = $pdo->query("SELECT id, filename, COALESCE(published_at, created_at) as lastmod FROM videos WHERE status='approved' ORDER BY id DESC LIMIT 5000");
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
// Главная
echo "  <url><loc>https://masterhacks.ru/</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>\n";
// Страницы видео
foreach ($videos as $v) {
    $loc = 'https://masterhacks.ru/view.php?id=' . intval($v['id']);
    $lastmod = date('Y-m-d', strtotime($v['lastmod']));
    echo "  <url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
}
echo "</urlset>\n";
```

**.htaccess addition:**
```apache
RewriteRule ^sitemap\.xml$ sitemap.php [L]
```

**Verification:** `curl -s https://masterhacks.ru/sitemap.xml | head -20`

---

#### Task A2: SEO-страница view.php с VideoObject schema

**Objective:** Каждое видео получает SSR-страницу с Open Graph, schema.org VideoObject и уникальным title/description.

**Files:**
- Modify: `view.php` — добавить SEO-мета и schema.org

**Implementation (добавить в `<head>` после подключения БД):**
```php
<?php
// В начале view.php после получения $video из БД
$video_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT v.*, a.username, a.first_name FROM videos v LEFT JOIN authors a ON a.telegram_id = v.telegram_id WHERE v.id = ? AND v.status = 'approved'");
$stmt->execute([$video_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if ($video) {
    $title = ($video['description'] ?: 'Видео') . ' | MasterHacks';
    $desc = $video['description'] ?: 'Смотрите короткие видео на MasterHacks';
    $videoUrl = 'https://masterhacks.ru/media/' . $video['filename'];
    $thumbUrl = $video['thumbnail_url'] ?: 'https://masterhacks.ru/gk.png';
    $pageUrl = 'https://masterhacks.ru/view.php?id=' . $video_id;
    
    // Open Graph
    echo '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . htmlspecialchars($desc) . '">' . "\n";
    echo '<meta property="og:image" content="' . $thumbUrl . '">' . "\n";
    echo '<meta property="og:url" content="' . $pageUrl . '">' . "\n";
    echo '<meta property="og:type" content="video.other">' . "\n";
    echo '<meta property="og:video" content="' . $videoUrl . '">' . "\n";
    
    // Schema.org VideoObject
    echo '<script type="application/ld+json">' . "\n";
    echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        'name' => $video['description'] ?: 'Видео',
        'description' => $desc,
        'thumbnailUrl' => $thumbUrl,
        'contentUrl' => $videoUrl,
        'uploadDate' => $video['published_at'] ?? $video['created_at'],
        'interactionStatistic' => [
            ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/WatchAction', 'userInteractionCount' => intval($video['views'] ?? 0)],
            ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/LikeAction', 'userInteractionCount' => intval($video['likes'] ?? 0)],
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '</script>' . "\n";
}
```

**Verification:** Открыть `view.php?id=191` → Проверить View Source на наличие og:title и ld+json.

---

### Группа B: Поиск + категории

#### Task B1: Поиск по видео

**Objective:** Страница поиска с FULLTEXT-поиском по `videos.description`.

**Files:**
- Create: `search.php` — форма + результаты

**Implementation:**
```php
<?php
// search.php
require_once __DIR__ . '/config/database.php';
$query = trim($_GET['q'] ?? '');
$results = [];
if (strlen($query) >= 2) {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        "SELECT v.id, v.filename, v.file_type, v.description, v.likes, v.views, v.published_at,
                a.username, a.first_name
         FROM videos v
         LEFT JOIN authors a ON a.telegram_id = v.telegram_id
         WHERE v.status = 'approved' AND MATCH(v.description) AGAINST(:q IN BOOLEAN MODE)
         ORDER BY v.published_at DESC LIMIT 50"
    );
    $stmt->execute([':q' => $query . '*']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Рендерим минимальную HTML-страницу с результатами
?>
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>Поиск: <?= htmlspecialchars($query) ?> | MasterHacks</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>/* минимальные стили — тёмная тема как на сайте */</style>
</head>
<body>
  <form method="get"><input name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Поиск видео..."></form>
  <?php foreach ($results as $v): ?>
    <div><a href="view.php?id=<?= $v['id'] ?>"><video src="media/<?= $v['filename'] ?>" muted></video></a>
    <p><?= htmlspecialchars($v['description'] ?: 'Без описания') ?></p></div>
  <?php endforeach; ?>
</body>
</html>
```

**Verification:** `curl -s "https://masterhacks.ru/search.php?q=ремонт"` — должны быть результаты.

---

#### Task B2: Страница категорий по тегам

**Objective:** Страницы категорий на основе `videos.tags` JSON.

**Files:**
- Create: `category.php?tag=ремонт`

**Implementation:**
```php
<?php
// category.php — фильтр по тегу
require_once __DIR__ . '/config/database.php';
$tag = trim($_GET['tag'] ?? '');
$results = [];
if ($tag) {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        "SELECT v.id, v.filename, v.file_type, v.description, v.likes, v.views
         FROM videos v WHERE v.status = 'approved' AND JSON_CONTAINS(v.tags, JSON_QUOTE(:tag))
         ORDER BY v.published_at DESC LIMIT 50"
    );
    $stmt->execute([':tag' => $tag]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// HTML как search.php, но с заголовком категории
```

**Verification:** `curl -s "https://masterhacks.ru/category.php?tag=ремонт"`

---

### Группа C: Безопасность

#### Task C1: CSP и security headers в .htaccess

**Objective:** Добавить Content-Security-Policy и secure cookie flags.

**Files:**
- Modify: `.htaccess` — добавить заголовки

**.htaccess additions:**
```apache
# Security headers
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://mc.yandex.ru; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: https://mc.yandex.ru; media-src 'self'; connect-src 'self' https://mc.yandex.ru; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"

# Secure session cookies
php_flag session.cookie_httponly On
php_flag session.cookie_secure On
php_value session.cookie_samesite "Lax"
```

**Verification:** `curl -sI https://masterhacks.ru/ | grep -i -E 'content-security|x-frame|x-content|x-xss|referrer|set-cookie'`

---

### Группа D: Контент

#### Task D1: Добавить поле title и описание к видео в БД (migration)

**Objective:** В таблице `videos` уже есть `description TEXT` и `tags JSON`. Нужно заполнить их для существующих видео тестовыми данными, чтобы лента стала осмысленной.

**Files:**
- Create: `data/migrate_descriptions.php` — одноразовый скрипт-миграция

**Implementation:**
```php
<?php
// data/migrate_descriptions.php — заполнить описания для существующих видео
require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$descriptions = [
    'Лайфхак: как быстро починить кран',
    'Ремонт пола своими руками за час',
    'Секрет идеальной заточки ножа',
    'Утепление окон без замены',
    'DIY: полка из поддонов за 30 минут',
    'Как заменить розетку безопасно',
    'Чистка кондиционера дома',
    'Сборка мебели ИКЕА: хитрости',
    'Покраска стен без разводов',
    'Сантехника: меняем сифон',
];

$stmt = $pdo->query("SELECT id FROM videos WHERE status='approved' AND (description IS NULL OR description = '')");
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$update = $pdo->prepare("UPDATE videos SET description = :desc, tags = :tags WHERE id = :id");
foreach ($videos as $i => $v) {
    $desc = $descriptions[$i % count($descriptions)];
    // Простые теги на основе слов из описания
    $words = preg_split('/[\s,]+/u', $desc);
    $tags = array_slice(array_filter($words, fn($w) => mb_strlen($w) > 3), 0, 3);
    $update->execute([
        ':desc' => $desc,
        ':tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
        ':id' => $v['id']
    ]);
}
echo "Обновлено: " . count($videos) . " видео\n";
```

**Verification:** `php data/migrate_descriptions.php`

---

## Порядок выполнения

1. **Параллельно (разные файлы, не конфликтуют):**
   - Agent 1: Task A1 + A2 (sitemap + view.php SEO)
   - Agent 2: Task B1 + B2 (поиск + категории)
   - Agent 3: Task C1 (security headers)

2. **После всех:** Task D1 (миграция описаний — нужен доступ к БД)

3. **Финал:** Все пуши в GitHub отдельными ветками.

---

## Что НЕ делаем (чтобы не трогать скорость ленты):
- ❌ Не меняем `get_posts.php` (критический путь загрузки видео)
- ❌ Не меняем рендеринг ленты в `index.php`
- ❌ Не добавляем JS-блокирующие скрипты
- ❌ Не добавляем лишние JOIN'ы в основной запрос
- ❌ Не трогаем прямую раздачу `/media/`
- ❌ Не добавляем редиректы на пути видеофайлов

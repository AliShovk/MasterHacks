<?php
/**
 * Dynamic XML Sitemap for MasterHacks
 * Generates sitemap with all approved videos for search engines.
 */
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        "SELECT id, filename, COALESCE(published_at, created_at) as lastmod
         FROM videos
         WHERE status = 'approved'
         ORDER BY id DESC
         LIMIT 5000"
    );
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<error>Database error</error>';
    exit;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
  <url>
    <loc>https://masterhacks.ru/</loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
<?php foreach ($videos as $video): ?>
  <url>
    <loc>https://masterhacks.ru/view.php?id=<?= (int)$video['id'] ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($video['lastmod'] ?? 'now')) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
<?php endforeach; ?>
</urlset>

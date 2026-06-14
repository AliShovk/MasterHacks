<?php
/**
 * Dynamic Sitemap Generator for MasterHacks
 * Outputs XML with all approved video pages + static pages
 */
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query(
        "SELECT id, COALESCE(published_at, created_at) AS lastmod
         FROM videos
         WHERE status = 'approved'
         ORDER BY id DESC"
    );
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Database error</error>';
    exit;
}

$base = 'https://masterhacks.ru';

// Static pages
$static = [
    ['loc' => '/',                    'changefreq' => 'daily',   'priority' => '1.0'],
    ['loc' => '/privacy.php',         'changefreq' => 'monthly', 'priority' => '0.3'],
    ['loc' => '/terms.php',           'changefreq' => 'monthly', 'priority' => '0.3'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static pages
foreach ($static as $p) {
    echo "  <url>\n";
    echo "    <loc>{$base}{$p['loc']}</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>{$p['changefreq']}</changefreq>\n";
    echo "    <priority>{$p['priority']}</priority>\n";
    echo "  </url>\n";
}

// Video pages
foreach ($videos as $v) {
    $lastmod = date('Y-m-d', strtotime($v['lastmod'] ?? 'now'));
    echo "  <url>\n";
    echo "    <loc>{$base}/view.php?id={$v['id']}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';

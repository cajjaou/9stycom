<?php
require_once __DIR__ . '/db.php';
try {
    $pdo = get_db();
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Database connection failed.");
}

$site_url = rtrim(get_setting('site_url', 'https://9sty.com'), '/');

header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Homepage
echo "  <url>\n";
echo "    <loc>" . htmlspecialchars($site_url) . "/</loc>\n";
echo "    <changefreq>always</changefreq>\n";
echo "    <priority>1.0</priority>\n";
echo "  </url>\n";

// 2. Categories
$categories = $pdo->query("SELECT slug FROM news_categories WHERE status = 'active'")->fetchAll();
foreach ($categories as $cat) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($site_url . '/' . $cat['slug']) . "</loc>\n";
    echo "    <changefreq>daily</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

// 3. Articles
$posts = $pdo->query("SELECT slug, created_at FROM news_posts WHERE status = 'published' AND deleted_at IS NULL ORDER BY id DESC")->fetchAll();
foreach ($posts as $post) {
    $lastmod = date(DateTime::W3C, strtotime($post['created_at']));
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($site_url . '/' . $post['slug']) . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($lastmod) . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
?>

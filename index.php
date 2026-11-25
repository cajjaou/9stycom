<?php
/**
 * index.php — Router wa7ed dyal 9sty. Kolchi (homepage, post, category)
 * kaydouz men hna b fal .htaccess (URLs clean: 9sty.com/slug).
 *
 * L logique dyal ikhtiyar: l slug momkin ykon dyal POST wla dyal
 * CATEGORY. Kanchecktiw f news_posts l'awal (post ghaleb l'ihtimam),
 * mn ba3d f news_categories.
 *
 * MOHIM: news_posts.slug w news_categories.slug 3andhom UNIQUE b
 * wa7dhom bark, machi cross-table. Ila khla9ti post w category b
 * nafs slug, l post howa li ghadi yban (l category tabqa machi
 * accessible b had slug). Khdem b slug_is_available() (f db.php)
 * mli kaykhal9o post/category jdida f l'admin panel bach matsalatch
 * l blasa.
 */
require __DIR__ . '/db.php';
$pdo = get_db();

$slug = trim($_GET['slug'] ?? '', '/');
$page = max(1, (int)($_GET['page'] ?? 1));

// --- Homepage ---
if ($slug === '') {
    require __DIR__ . '/templates/home.php';
    exit;
}

// --- 1) Slug dyal POST? ---
$stmt = $pdo->prepare("
    SELECT p.id, p.slug, p.title, p.excerpt, p.content, p.image,
           p.short_code, p.category_id, p.views, p.created_at, c.name AS category_name,
           c.slug AS category_slug
    FROM news_posts p
    LEFT JOIN news_categories c ON c.id = p.category_id
    WHERE p.slug = ? AND p.status = 'published' AND p.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$slug]);
$article = $stmt->fetch();

if ($article) {
    require __DIR__ . '/templates/post.php';
    exit;
}

// --- 2) Slug dyal CATEGORY? ---
$stmt = $pdo->prepare("SELECT id, name, slug FROM news_categories WHERE slug = ? AND status = 'active' LIMIT 1");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if ($category) {
    require __DIR__ . '/templates/category.php';
    exit;
}

// --- 3) Walo mkayench b had slug ---
http_response_code(404);
require __DIR__ . '/templates/404.php';

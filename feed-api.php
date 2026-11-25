<?php
// feed-api.php - AJAX API for loading more feed posts on the homepage

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/db.php';

$offset    = isset($_GET['offset']) ? (int)$_GET['offset'] : 24;
$limit     = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;
$slotStart = isset($_GET['slot_start']) ? (int)$_GET['slot_start'] : 5;

$pdo = get_db();

// Fetch posts
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.slug, p.title, p.image, p.category_id, p.created_at,
               p.short_code,
               c.name AS category_name,
               (SELECT COUNT(*) FROM news_comments nc WHERE nc.post_id = p.id AND (nc.status != 'hidden' OR nc.status IS NULL)) AS comments_count
        FROM news_posts p
        LEFT JOIN news_categories c ON c.id = p.category_id
        WHERE p.status = 'published' AND p.deleted_at IS NULL
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Feed API query failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات أثناء جلب الأخبار.']);
    exit;
}

$hasMore = count($posts) === $limit;

// Render HTML for the posts and the sponsored slot
ob_start();
if (!empty($posts)) {
    foreach ($posts as $p) {
        ?>
        <a href="/<?= htmlspecialchars($p['slug']) ?>" class="post-card">
          <div class="thumb">
            <img src="<?= htmlspecialchars(post_thumb($p['image'])) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy" onerror="this.onerror=null; this.src='<?= FALLBACK_IMAGE ?>';">
            <span class="badge" style="background:<?= category_color((int)$p['category_id']) ?>"><?= htmlspecialchars($p['category_name'] ?? 'عام') ?></span>
          </div>
          <div class="post-body">
            <h3><?= htmlspecialchars($p['title']) ?></h3>
            <div class="post-meta">
              <span class="comments">💬 <?= (int)$p['comments_count'] ?> تعليق</span>
              <span><?= time_ago($p['created_at']) ?></span>
              <?php if (!empty($p['short_code'])): ?>
                <span class="short-code-tag" style="font-family:monospace; font-size:11px; background:rgba(0,0,0,0.05); padding:1px 5px; border-radius:3px; color:var(--ink-soft);">#<?= htmlspecialchars($p['short_code']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php
    }
    
    // Sponsored banner slot at the end of the batch
    echo '<div class="jb-slot post-card sponsored" data-jb-slot="' . $slotStart . '"><div class="jb-skeleton"></div></div>';
}
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html,
    'has_more' => $hasMore,
    'next_offset' => $offset + count($posts),
    'next_slot' => $slotStart + 1
]);

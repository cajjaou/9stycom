<?php
/**
 * templates/category.php — Listing dyal posts f category mo3ayna.
 * $category w $pdo mawjodin mn index.php. $page mn index.php (default 1).
 * URL dyal pagination: /category-slug/page/2
 */
$perPage = 12;
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM news_posts WHERE category_id = ? AND status = 'published'");
$totalStmt->execute([$category['id']]);
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT p.id, p.slug, p.title, p.image, p.views, p.created_at, p.short_code,
           (SELECT COUNT(*) FROM news_comments nc WHERE nc.post_id = p.id AND (nc.status != 'hidden' OR nc.status IS NULL)) AS comments_count
    FROM news_posts p
    WHERE p.category_id = ? AND p.status = 'published'
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute([$category['id']]);
$posts = $stmt->fetchAll();

$trending = $pdo->query("SELECT title, slug FROM news_posts WHERE status='published' ORDER BY views DESC LIMIT 5")->fetchAll();

$pageTitle = htmlspecialchars($category['name']) . ' — 9sty';
require __DIR__ . '/header.php';
?>

  <main>
    <p class="crumb"><a href="/">الرئيسية</a> / <?= htmlspecialchars($category['name']) ?></p>
    <h1 class="feed-title"><?= htmlspecialchars($category['name']) ?> — <?= number_format($total) ?> خبر</h1>

    <?php if (empty($posts)): ?>
      <p style="color:var(--ink-soft);">لا توجد أخبار في هذا القسم حاليا.</p>
    <?php endif; ?>

    <div class="feed">
      <?php
      $slotNum = 0;
      foreach ($posts as $i => $p):
          if ($i > 0 && $i % 6 === 0):
              $slotNum++;
              echo '<div class="jb-slot post-card sponsored" data-jb-slot="' . $slotNum . '"><div class="jb-skeleton"></div></div>';
          endif;
      ?>
        <a href="/<?= htmlspecialchars($p['slug']) ?>" class="post-card">
          <div class="thumb">
            <img src="<?= htmlspecialchars(post_thumb($p['image'])) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy" onerror="this.onerror=null; this.src='<?= FALLBACK_IMAGE ?>';">
            <span class="badge" style="background:<?= category_color((int)$category['id']) ?>"><?= htmlspecialchars($category['name']) ?></span>
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
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="<?= $page - 1 === 1 ? '/' . htmlspecialchars($category['slug']) : '/' . htmlspecialchars($category['slug']) . '/page/' . ($page - 1) ?>">السابق</a>
        <?php endif; ?>
        <?php for ($n = 1; $n <= $totalPages; $n++): ?>
          <?php if ($n === $page): ?>
            <span class="current"><?= $n ?></span>
          <?php else: ?>
            <a href="<?= $n === 1 ? '/' . htmlspecialchars($category['slug']) : '/' . htmlspecialchars($category['slug']) . '/page/' . $n ?>"><?= $n ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="/<?= htmlspecialchars($category['slug']) ?>/page/<?= $page + 1 ?>">التالي</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>

  <aside>
    <?= render_adsense('sidebar_top') ?>
    <div class="sidebar-block">
      <p class="sidebar-title">🔥 الأكثر قراءة</p>
      <?php foreach ($trending as $i => $t): ?>
        <div class="trend-item">
          <span class="trend-num"><?= $i + 1 ?></span>
          <p><a href="/<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['title']) ?></a></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="sidebar-block">
      <p class="sidebar-title">🌿 عروض JanaBio</p>
      <div id="jb-sidebar-promos">
        <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="sb-promo jb-slot" data-jb-sidebar-slot="<?= $i ?>"><div class="jb-skeleton" style="width:64px;height:64px;border-radius:4px;flex-shrink:0;"></div></div>
        <?php endfor; ?>
      </div>
    </div>

    <?= render_adsense('sidebar_bottom') ?>
  </aside>

<?php require __DIR__ . '/footer.php'; ?>

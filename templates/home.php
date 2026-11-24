<?php
/**
 * templates/home.php — Homepage. Kaydouz mn index.php ila $slug khawi.
 * $pdo mawjoud mn index.php.
 */
$stmt = $pdo->prepare("
    SELECT p.id, p.slug, p.title, p.excerpt, p.content, p.image, p.category_id,
           p.short_code, p.views, p.created_at, c.name AS category_name,
           (SELECT COUNT(*) FROM news_comments nc WHERE nc.post_id = p.id AND (nc.status != 'hidden' OR nc.status IS NULL)) AS comments_count
    FROM news_posts p
    LEFT JOIN news_categories c ON c.id = p.category_id
    WHERE p.status = 'published'
    ORDER BY p.created_at DESC
    LIMIT 24
");
$stmt->execute();
$posts = $stmt->fetchAll();

$heroMain = $posts[0] ?? null;
$heroSide = array_slice($posts, 1, 3);
$feedPosts = array_slice($posts, 4);

$trending = $pdo->query("
    SELECT title, slug FROM news_posts
    WHERE status = 'published'
    ORDER BY views DESC LIMIT 5
")->fetchAll();

$pageTitle = '9sty — آخر الأخبار';
$showTicker = true;
require __DIR__ . '/header.php';
?>

  <main>

    <!-- HERO -->
    <?php if ($heroMain): ?>
    <div class="hero">
      <a href="/<?= htmlspecialchars($heroMain['slug']) ?>" class="hero-main">
        <img src="<?= htmlspecialchars(post_thumb($heroMain['image'])) ?>" alt="<?= htmlspecialchars($heroMain['title']) ?>" onerror="this.onerror=null; this.src='<?= FALLBACK_IMAGE ?>';">
        <div class="cap">
          <span class="cat" style="background:<?= category_color((int)$heroMain['category_id']) ?>"><?= htmlspecialchars($heroMain['category_name'] ?? 'عام') ?></span>
          <h2><?= htmlspecialchars($heroMain['title']) ?></h2>
        </div>
      </a>
      <div class="hero-side">
        <?php foreach ($heroSide as $p): ?>
          <a href="/<?= htmlspecialchars($p['slug']) ?>" class="item">
            <img src="<?= htmlspecialchars(post_thumb($p['image'])) ?>" alt="<?= htmlspecialchars($p['title']) ?>" onerror="this.onerror=null; this.src='<?= FALLBACK_IMAGE ?>';">
            <div>
              <span class="cat" style="background:<?= category_color((int)$p['category_id']) ?>"><?= htmlspecialchars($p['category_name'] ?? 'عام') ?></span>
              <h3><?= htmlspecialchars($p['title']) ?></h3>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- FEED -->
    <h1 class="feed-title">أحدث الأخبار</h1>
    <div class="feed">
      <?php
      $slotNum = 0;
      foreach ($feedPosts as $i => $p):
          if ($i > 0 && $i % 6 === 0):
              $slotNum++;
              echo '<div class="jb-slot post-card sponsored" data-jb-slot="' . $slotNum . '"><div class="jb-skeleton"></div></div>';
          endif;
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
      <?php endforeach; ?>

      <div class="jb-slot post-card sponsored" data-jb-slot="<?= ++$slotNum ?>"><div class="jb-skeleton"></div></div>
    </div>

    <div id="feed-sentinel" style="width: 100%; height: 20px; margin-top: 20px;"></div>
    <div id="feed-loader" style="text-align: center; display: none; padding: 20px; font-weight: bold; color: var(--ink-soft); font-family: 'Cairo', sans-serif; font-size: 14px;">جاري تحميل المزيد من الأخبار...</div>

  </main>

  <!-- SIDEBAR -->
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    var feedContainer = document.querySelector('.feed');
    var sentinel = document.getElementById('feed-sentinel');
    var loader = document.getElementById('feed-loader');
    
    if (!feedContainer || !sentinel) return;
    
    var offset = 24; // 1 main hero + 3 side hero + 20 feed posts
    var slotNum = <?= $slotNum + 1 ?>; // Get next slot number from PHP
    var isLoading = false;
    var hasMore = true;
    
    var observer = new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting && !isLoading && hasMore) {
            loadMorePosts();
        }
    }, {
        rootMargin: '300px' // Load posts 300px before reaching the end of the viewport
    });
    
    observer.observe(sentinel);
    
    function loadMorePosts() {
        isLoading = true;
        loader.style.display = 'block';
        
        fetch('/feed-api.php?offset=' + offset + '&slot_start=' + slotNum)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                isLoading = false;
                loader.style.display = 'none';
                
                if (!data.success) {
                    console.error('Feed API error:', data.message);
                    return;
                }
                
                if (data.html) {
                    feedContainer.insertAdjacentHTML('beforeend', data.html);
                    
                    // Automatically fill the new banner slots
                    if (window.fillFeedSlots && window.jbProducts) {
                        window.fillFeedSlots(window.jbProducts);
                    }
                }
                
                hasMore = data.has_more;
                offset = data.next_offset;
                slotNum = data.next_slot;
                
                if (!hasMore) {
                    observer.unobserve(sentinel);
                    sentinel.remove();
                }
            })
            .catch(function (err) {
                isLoading = false;
                loader.style.display = 'none';
                console.error('Failed to load more posts:', err);
            });
    }
});
</script>

<?php require __DIR__ . '/footer.php'; ?>

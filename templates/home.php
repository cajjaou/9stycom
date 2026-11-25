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
    WHERE p.status = 'published' AND p.deleted_at IS NULL
    ORDER BY p.created_at DESC
    LIMIT 27
");
$stmt->execute();
$posts = $stmt->fetchAll();

$heroSlides = array_slice($posts, 0, 4);
$heroSide = array_slice($posts, 4, 3);
$feedPosts = array_slice($posts, 7);

$trending = $pdo->query("
    SELECT title, slug FROM news_posts
    WHERE status = 'published' AND deleted_at IS NULL
    ORDER BY views DESC LIMIT 5
")->fetchAll();

$pageTitle = '9sty — آخر الأخبار';
$showTicker = true;
require __DIR__ . '/header.php';
?>

  <main>

    <!-- HERO SLIDESHOW -->
    <?php if (!empty($heroSlides)): ?>
    <div class="hero">
      <div class="hero-slider" id="hero-slider">
        <?php foreach ($heroSlides as $idx => $slide): ?>
          <a href="/<?= htmlspecialchars($slide['slug']) ?>" class="hero-slide <?= $idx === 0 ? 'active' : '' ?>">
            <img src="<?= htmlspecialchars(post_thumb($slide['image'])) ?>" alt="<?= htmlspecialchars($slide['title']) ?>" onerror="this.onerror=null; this.src='<?= FALLBACK_IMAGE ?>';">
            <div class="cap">
              <span class="cat" style="background:<?= category_color((int)$slide['category_id']) ?>"><?= htmlspecialchars($slide['category_name'] ?? 'عام') ?></span>
              <h2><?= htmlspecialchars($slide['title']) ?></h2>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if (count($heroSlides) > 1): ?>
          <button type="button" class="hero-nav-btn prev" id="hero-prev" aria-label="السابق"><i data-lucide="chevron-right"></i></button>
          <button type="button" class="hero-nav-btn next" id="hero-next" aria-label="التالي"><i data-lucide="chevron-left"></i></button>
          <div class="hero-dots" id="hero-dots">
            <?php foreach ($heroSlides as $idx => $_): ?>
              <button type="button" class="hero-dot <?= $idx === 0 ? 'active' : '' ?>" data-slide="<?= $idx ?>" aria-label="شريحة <?= $idx + 1 ?>"></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="hero-side">
        <?php foreach ($heroSide as $p): ?>
          <a href="/<?= htmlspecialchars($p['slug']) ?>" class="item">
            <img src="<?= htmlspecialchars(post_thumb($p['image'])) ?>" alt="<?= htmlspecialchars($p['title']) ?>" onerror="this.onerror=null; this.src='<?= FALLBACK_IMAGE ?>';">
            <div>
              <span class="cat" style="background:<?= category_color((int)$p['category_id']) ?>"><?= htmlspecialchars($p['category_name'] ?? 'عام') ?></span>
              <h3><?= htmlspecialchars($p['title']) ?></h3>
              <span class="item-time"><?= time_ago($p['created_at']) ?></span>
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
              <span class="comments"><i data-lucide="message-circle"></i> <?= (int)$p['comments_count'] ?> تعليق</span>
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
      <p class="sidebar-title"><i data-lucide="flame"></i> الأكثر قراءة</p>
      <?php foreach ($trending as $i => $t): ?>
        <div class="trend-item">
          <span class="trend-num"><?= $i + 1 ?></span>
          <p><a href="/<?= htmlspecialchars($t['slug']) ?>"><?= htmlspecialchars($t['title']) ?></a></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="sidebar-block">
      <p class="sidebar-title"><i data-lucide="sparkles"></i> عروض JanaBio</p>
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
    // Hero slideshow
    var slider = document.getElementById('hero-slider');
    if (slider) {
        var slides = slider.querySelectorAll('.hero-slide');
        var dots = slider.querySelectorAll('.hero-dot');
        var currentSlide = 0;
        var slideTimer = null;

        function goToSlide(idx) {
            if (!slides.length) return;
            slides[currentSlide].classList.remove('active');
            if (dots[currentSlide]) dots[currentSlide].classList.remove('active');
            currentSlide = (idx + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
            if (dots[currentSlide]) dots[currentSlide].classList.add('active');
        }

        function startAutoSlide() {
            if (slides.length <= 1) return;
            stopAutoSlide();
            slideTimer = setInterval(function () { goToSlide(currentSlide + 1); }, 5000);
        }

        function stopAutoSlide() {
            if (slideTimer) { clearInterval(slideTimer); slideTimer = null; }
        }

        var prevBtn = document.getElementById('hero-prev');
        var nextBtn = document.getElementById('hero-next');
        if (prevBtn) prevBtn.addEventListener('click', function () { goToSlide(currentSlide - 1); startAutoSlide(); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goToSlide(currentSlide + 1); startAutoSlide(); });
        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                goToSlide(parseInt(dot.getAttribute('data-slide'), 10) || 0);
                startAutoSlide();
            });
        });
        slider.addEventListener('mouseenter', stopAutoSlide);
        slider.addEventListener('mouseleave', startAutoSlide);
        startAutoSlide();
    }

    var feedContainer = document.querySelector('.feed');
    var sentinel = document.getElementById('feed-sentinel');
    var loader = document.getElementById('feed-loader');
    
    if (!feedContainer || !sentinel) return;
    
    var offset = 27; // 4 hero slides + 3 side hero + 20 feed posts
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
                    if (window.refreshIcons) window.refreshIcons();
                    
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

<?php
// post-api.php - AJAX API for loading the next article on single post view

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/db.php';

$currentCreated = $_GET['current_created'] ?? '';

if (empty($currentCreated)) {
    echo json_encode(['success' => false, 'message' => 'توقيت المقال الحالي مطلوب.']);
    exit;
}

$pdo = get_db();

// 1) Fetch the next chronological older post
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.slug, p.title, p.excerpt, p.content, p.image,
               p.short_code, p.category_id, p.views, p.created_at, c.name AS category_name,
               c.slug AS category_slug
        FROM news_posts p
        LEFT JOIN news_categories c ON c.id = p.category_id
        WHERE p.created_at < ? AND p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$currentCreated]);
    $article = $stmt->fetch();
    
    // Fallback: if we reached the oldest post, wrap around and load the newest post
    if (!$article) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.slug, p.title, p.excerpt, p.content, p.image,
                   p.short_code, p.category_id, p.views, p.created_at, c.name AS category_name,
                   c.slug AS category_slug
            FROM news_posts p
            LEFT JOIN news_categories c ON c.id = p.category_id
            WHERE p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT 1
        ");
        $stmt->execute();
        $article = $stmt->fetch();
    }
} catch (PDOException $e) {
    error_log('Post API fetch failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات أثناء جلب المقال.']);
    exit;
}

if (!$article) {
    echo json_encode(['success' => false, 'message' => 'لا توجد مقالات أخرى.']);
    exit;
}

// 2) Increment views (+1 view)
try {
    $pdo->prepare("UPDATE news_posts SET views = views + 1 WHERE id = ?")->execute([$article['id']]);
} catch (PDOException $e) {
    error_log('Post API increment view count failed: ' . $e->getMessage());
}

// 3) Parse paragraphs & choose ad banner spots
$paragraphs = preg_split('/\r\n|\r|\n/', trim(strip_tags($article['content'], '<h2><h3><h4><h5><h6><a><ul><li><ol><p><br><strong><b><i><em><u><blockquote><code><pre><hr>')));
$paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));
$totalParagraphs = count($paragraphs);
$bannerAfter = [];
if ($totalParagraphs >= 3) {
    $bannerAfter[] = rand(1, max(1, $totalParagraphs - 2));
}
if ($totalParagraphs >= 5) {
    $second = rand($bannerAfter[0] + 2, $totalParagraphs - 1);
    if ($second !== $bannerAfter[0]) {
        $bannerAfter[] = $second;
    }
}

// 4) Fetch comments (Visible only, 10 root comments per batch)
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM news_comments WHERE post_id = ? AND (status != 'hidden' OR status IS NULL)");
    $countStmt->execute([$article['id']]);
    $commentsCount = (int)$countStmt->fetchColumn();

    $rootCountStmt = $pdo->prepare("SELECT COUNT(*) FROM news_comments WHERE post_id = ? AND (parent_id IS NULL OR parent_id = 0) AND (status != 'hidden' OR status IS NULL)");
    $rootCountStmt->execute([$article['id']]);
    $totalRootComments = (int)$rootCountStmt->fetchColumn();

    $rootStmt = $pdo->prepare("
        SELECT id, parent_id, name, comment, created_at 
        FROM news_comments 
        WHERE post_id = ? AND (parent_id IS NULL OR parent_id = 0) AND (status != 'hidden' OR status IS NULL)
        ORDER BY created_at ASC 
        LIMIT 10
    ");
    $rootStmt->execute([$article['id']]);
    $rootComments = $rootStmt->fetchAll();

    $replyStmt = $pdo->prepare("
        SELECT id, parent_id, name, comment, created_at 
        FROM news_comments 
        WHERE post_id = ? AND (parent_id IS NOT NULL AND parent_id != 0) AND (status != 'hidden' OR status IS NULL)
        ORDER BY created_at ASC
    ");
    $replyStmt->execute([$article['id']]);
    $allReplies = $replyStmt->fetchAll();

    $commentsByParent = [];
    foreach ($allReplies as $c) {
        $commentsByParent[$c['parent_id']][] = $c;
    }
    $hasMoreComments = ($totalRootComments > 10);
} catch (PDOException $e) {
    error_log('Post API comments query failed: ' . $e->getMessage());
    $rootComments = [];
    $commentsByParent = [];
    $commentsCount = 0;
    $hasMoreComments = false;
}

// 5) Anti-spam parameters for comments form (stateless)
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$ans = $num1 + $num2;
$time = time();
$salt = '9sty_comment_anti_spam_2026';
$hash = hash('sha256', $ans . '|' . $time . '|' . $salt);

if (!function_exists('render_comment_tree')) {
    function render_comment_tree(array $comments, array $commentsByParent, int $depth = 0) {
        foreach ($comments as $c) {
            $padding = min($depth * 40, 120);
            ?>
            <div class="comment" id="comment-<?= $c['id'] ?>" style="margin-right: <?= $padding ?>px;">
                <div class="avatar"><?= htmlspecialchars(mb_substr($c['name'], 0, 1)) ?></div>
                <div class="comment-body">
                    <div class="comment-head">
                        <b><?= htmlspecialchars($c['name']) ?></b>
                        <span><?= time_ago($c['created_at']) ?></span>
                        <?php if ($depth > 0): ?>
                            <span class="reply-to-badge">رد</span>
                        <?php endif; ?>
                    </div>
                    <p><?= htmlspecialchars($c['comment']) ?></p>
                    <div class="comment-actions">
                        <button type="button" class="btn-reply" data-comment-id="<?= $c['id'] ?>" data-comment-author="<?= htmlspecialchars($c['name']) ?>">رد</button>
                    </div>
                </div>
            </div>
            <?php
            if (isset($commentsByParent[$c['id']])) {
                render_comment_tree($commentsByParent[$c['id']], $commentsByParent, $depth + 1);
            }
        }
    }
}

// 6) Render post body content
ob_start();
?>
    <p class="crumb">
      <a href="/">الرئيسية</a> /
      <?php if (!empty($article['category_slug'])): ?>
        <a href="/<?= htmlspecialchars($article['category_slug']) ?>"><?= htmlspecialchars($article['category_name']) ?></a>
      <?php else: ?>
        <?= htmlspecialchars($article['category_name'] ?? 'عام') ?>
      <?php endif; ?>
      / الخبر
    </p>
    <span class="cat" style="background:<?= category_color((int)$article['category_id']) ?>"><?= htmlspecialchars($article['category_name'] ?? 'عام') ?></span>
    <h1 class="art-title"><?= htmlspecialchars($article['title']) ?></h1>
    <div class="art-meta">
      <span>📅 <?= time_ago($article['created_at']) ?></span>
      <span>👁️ <?= number_format($article['views'] + 1) ?> مشاهدة</span>
      <span>💬 <?= $commentsCount ?> تعليق</span>
      <?php if (!empty($article['short_code'])): ?>
        <span class="art-shortcode" style="font-family:monospace; font-size:12px; font-weight:700; background:rgba(0,0,0,0.06); padding:2px 8px; border-radius:4px; color:var(--ink); letter-spacing:0.5px;">🏷️ <?= htmlspecialchars($article['short_code']) ?></span>
      <?php endif; ?>
    </div>

    <?= render_adsense('article_top') ?>

    <img class="art-cover" src="<?= htmlspecialchars(post_thumb($article['image'])) ?>" alt="" onerror="this.onerror=null; this.src='<?= FALLBACK_IMAGE ?>';">

    <?php 
    $shortUrl = "https://9sty.com/s/" . $article['short_code'];
    ?>
    <div class="box_social">
      <div class="btn-group" role="group">
        
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shortUrl) ?>" rel="noopener nofollow" target="_blank" class="share-btn facebook" title="شارك على Facebook">
          <span class="sm-icon">
            <svg viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
          </span>
        </a>

        <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shortUrl) ?>&text=<?= urlencode($article['title']) ?>" rel="noopener nofollow" target="_blank" class="share-btn twitter" title="شارك على تويتر">
          <span class="sm-icon">
            <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </span>
        </a>

        <a href="https://www.linkedin.com/shareArticle?mini=true&amp;url=<?= urlencode($shortUrl) ?>&amp;title=<?= urlencode($article['title']) ?>" rel="noopener nofollow" target="_blank" class="share-btn linkedin" title="Share on Linkedin">
          <span class="sm-icon">
            <svg viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.779-1.75-1.75s.784-1.75 1.75-1.75 1.75.779 1.75 1.75-.784 1.75-1.75 1.75zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
          </span>
        </a>

        <a href="https://telegram.me/share/url?text=<?= urlencode($article['title']) ?>&url=<?= urlencode($shortUrl) ?>" rel="noopener nofollow" target="_blank" class="share-btn telegram" title="Share on Telegram">
          <span class="sm-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-1-.65-.35-1 .22-1.62.15-.16 2.72-2.5 2.77-2.71.01-.03.01-.13-.05-.18-.06-.05-.14-.03-.21-.02-.09.02-1.49.94-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.52 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .24z"/></svg>
          </span>
        </a>

        <a href="https://api.whatsapp.com/send?text=<?= urlencode($article['title'] . ' ' . $shortUrl) ?>" rel="noopener nofollow" target="_blank" class="share-btn whatsapp" title="Share on Whatsapp">
          <span class="sm-icon">
            <svg viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.49-3.238c1.644.975 3.238 1.488 4.887 1.489 5.531 0 10.034-4.502 10.037-10.037.001-2.682-1.043-5.203-2.941-7.102C16.634 3.21 14.12 2.165 11.44 2.165c-5.54 0-10.046 4.503-10.049 10.04-.001 1.882.511 3.719 1.483 5.378L1.87 22.09l4.677-1.328z"/></svg>
          </span>
        </a>
      
        <button onclick="copyShareLink('<?= $shortUrl ?>', this)" class="copy copyLink share-btn link" title="Copy link">
          <span class="sm-icon">
            <svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
          </span>
        </button>

        <a href="javascript:window.print()" rel="noopener nofollow" class="print share-btn" title="Printable link">
          <span class="sm-icon">
            <svg viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
          </span>
        </a>

      </div>
    </div>

    <div class="art-body">
      <?php foreach ($paragraphs as $i => $p):
          $isBlock = preg_match('/^<(h[1-6]|ul|ol|li|p|blockquote|div|pre|hr)\b/i', $p);
          if ($isBlock) {
              echo $p;
          } else {
              echo '<p>' . $p . '</p>';
          }
      ?>
        <?php if ($i === (int)floor(count($paragraphs) / 2)): ?>
          <?= render_adsense('article_middle') ?>
        <?php endif; ?>

        <?php if (in_array($i, $bannerAfter, true)): ?>
          <div class="jb-inline jb-slot" data-jb-inline-slot>
            <div class="jb-skeleton-inline"></div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <?= render_adsense('article_bottom') ?>

    <div class="comments-section">
      <p class="comments-title">💬 التعليقات (<span id="comments-count"><?= $commentsCount ?></span>)</p>
      
      <form id="comment-form" action="/comment-api.php" method="POST" style="margin-bottom: 24px;">
        <input type="hidden" name="post_id" value="<?= $article['id'] ?>">
        <input type="hidden" name="parent_id" id="parent_id_input" value="">
        
        <div id="replying-to-status" style="display:none; background:var(--panel); padding:8px 12px; border-radius:6px; margin-bottom:12px; font-size:13px; align-items:center; justify-content:space-between;">
           <span>أنت ترد على: <b id="replying-to-author"></b></span>
           <button type="button" id="cancel-reply-btn" style="background:none; border:none; color:var(--accent); cursor:pointer; font-weight:700;">إلغاء</button>
        </div>
        
        <div class="form-row" style="display:flex; gap:10px; margin-bottom:10px;">
           <input type="text" name="name" placeholder="الاسم الكامل" style="flex:1; font-family:'Cairo',sans-serif; border:1px solid var(--line); border-radius:6px; padding:10px; font-size:14px; background:#fff; color:var(--ink);" required>
           <input type="email" name="email" placeholder="البريد الإلكتروني" style="flex:1; font-family:'Cairo',sans-serif; border:1px solid var(--line); border-radius:6px; padding:10px; font-size:14px; background:#fff; color:var(--ink);" required>
        </div>
        
        <textarea name="comment" placeholder="اكتب تعليقك هنا..." style="width:100%; font-family:'Cairo',sans-serif; border:1px solid var(--line); border-radius:6px; padding:10px; font-size:14px; resize:vertical; min-height:80px; margin-bottom:10px; background:#fff; color:var(--ink);" required></textarea>
        
        <div class="form-actions" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
           <div class="spam-challenge" style="display:flex; align-items:center; gap:8px;">
               <span id="spam-question" style="font-size:14px; font-weight:700; color:var(--ink-soft);"><?= $num1 ?> + <?= $num2 ?> = </span>
               <input type="number" name="spam_answer" placeholder="الإجابة" style="width:70px; font-family:'Cairo',sans-serif; border:1px solid var(--line); border-radius:6px; padding:6px; font-size:14px; text-align:center; background:#fff; color:var(--ink);" required>
               <input type="hidden" name="spam_time" id="spam_time" value="<?= $time ?>">
               <input type="hidden" name="spam_hash" id="spam_hash" value="<?= $hash ?>">
           </div>
           <button type="submit" style="background:var(--accent); color:#fff; border:none; padding:8px 24px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer; font-family:'Cairo',sans-serif;">نشر التعليق</button>
        </div>
      </form>
      
      <div id="comments-container" style="display: flex; flex-direction: column; gap: 14px;">
        <?php 
        if (!empty($rootComments)) {
            render_comment_tree($rootComments, $commentsByParent);
        } else {
            echo '<p class="no-comments-msg" style="color:var(--ink-soft);">لا توجد تعليقات بعد. كن أول من يعلق!</p>';
        }
        ?>
      </div>
      <?php if ($hasMoreComments): ?>
        <div class="load-more-comments-wrap" style="text-align: center; margin-top: 18px;">
          <button type="button" class="btn-load-more-comments" data-post-id="<?= $article['id'] ?>" data-offset="10">إظهار المزيد من التعليقات</button>
        </div>
      <?php endif; ?>
    </div>
<?php
$html = ob_get_clean();

// 7) Return JSON
echo json_encode([
    'success'    => true,
    'html'       => $html,
    'title'      => htmlspecialchars($article['title']) . ' — 9sty',
    'slug'       => htmlspecialchars($article['slug']),
    'created_at' => $article['created_at'],
    'post_id'    => $article['id']
]);

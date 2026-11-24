<?php
// comment-api.php - AJAX endpoint for submitting comments and replies

// Suppress error display so PHP warnings don't corrupt JSON output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Override exception handler to return JSON instead of HTML for API context
set_exception_handler(function(Throwable $e) {
    $timestamp = date('Y-m-d H:i:s');
    $msg = "[$timestamp] [Exception] " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . PHP_EOL;
    error_log($msg);
    if (function_exists('app_exception_response')) {
        app_exception_response($e, 'حدث خطأ في الخادم. يرجى المحاولة لاحقاً.');
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم. يرجى المحاولة لاحقاً.']);
    }
    exit;
});

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/db.php';

// Handle load more comments (AJAX pagination: 10 comments per batch)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_more') {
    $postId = (int)($_GET['post_id'] ?? 0);
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit  = 10;

    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'رقم المقال غير محدد.']);
        exit;
    }

    $pdo = get_db();

    // Total root comments count (visible only)
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM news_comments 
        WHERE post_id = ? AND (parent_id IS NULL OR parent_id = 0) AND (status != 'hidden' OR status IS NULL)
    ");
    $countStmt->execute([$postId]);
    $totalRoots = (int)$countStmt->fetchColumn();

    // Fetch next batch of 10 root comments
    $stmt = $pdo->prepare("
        SELECT id, parent_id, name, comment, created_at 
        FROM news_comments 
        WHERE post_id = ? AND (parent_id IS NULL OR parent_id = 0) AND (status != 'hidden' OR status IS NULL)
        ORDER BY created_at ASC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $nextRoots = $stmt->fetchAll();

    $hasMore = ($offset + count($nextRoots)) < $totalRoots;
    $nextOffset = $offset + count($nextRoots);

    // Fetch replies for these comments
    $replyStmt = $pdo->prepare("
        SELECT id, parent_id, name, comment, created_at 
        FROM news_comments 
        WHERE post_id = ? AND (parent_id IS NOT NULL AND parent_id != 0) AND (status != 'hidden' OR status IS NULL)
        ORDER BY created_at ASC
    ");
    $replyStmt->execute([$postId]);
    $allReplies = $replyStmt->fetchAll();

    $commentsByParent = [];
    foreach ($allReplies as $c) {
        $commentsByParent[$c['parent_id']][] = $c;
    }

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

    ob_start();
    render_comment_tree($nextRoots, $commentsByParent);
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'has_more' => $hasMore,
        'next_offset' => $nextOffset,
        'count' => count($nextRoots)
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صالحة.']);
    exit;
}

$postId    = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
$parentId  = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
$name      = trim($_POST['name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$comment   = trim($_POST['comment'] ?? '');

$spamAnswer = isset($_POST['spam_answer']) ? (int)$_POST['spam_answer'] : null;
$spamTime   = isset($_POST['spam_time']) ? (int)$_POST['spam_time'] : 0;
$spamHash   = $_POST['spam_hash'] ?? '';

// 1) General input validation
if (!$postId || empty($name) || empty($email) || empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'جميع الحقول مطلوبة (الاسم، البريد الإلكتروني، التعليق).']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني غير صالح.']);
    exit;
}

if (mb_strlen($name) > 100) {
    echo json_encode(['success' => false, 'message' => 'الاسم طويل جداً (الحد الأقصى 100 حرف).']);
    exit;
}

// 2) Stateless Math Challenge verification
$salt = '9sty_comment_anti_spam_2026';
if (time() - $spamTime > 3600) { // Expires f 1 hour
    echo json_encode(['success' => false, 'message' => 'انتهت صلاحية رمز التحقق من السبام. يرجى المحاولة مرة أخرى.']);
    exit;
}

$expectedHash = hash('sha256', $spamAnswer . '|' . $spamTime . '|' . $salt);
if (!hash_equals($expectedHash, $spamHash)) {
    echo json_encode(['success' => false, 'message' => 'إجابة التحقق من السبام غير صحيحة!']);
    exit;
}

$pdo = get_db();

// Verify post exists
$postCheck = $pdo->prepare("SELECT id FROM news_posts WHERE id = ? LIMIT 1");
$postCheck->execute([$postId]);
if (!$postCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'المقال غير موجود.']);
    exit;
}

// Verify parent comment exists if parent_id is set
if ($parentId) {
    $parentCheck = $pdo->prepare("SELECT id FROM news_comments WHERE id = ? AND post_id = ? LIMIT 1");
    $parentCheck->execute([$parentId, $postId]);
    if (!$parentCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'التعليق الذي تحاول الرد عليه غير موجود.']);
        exit;
    }
}

// 3) Insert comment
try {
    $stmt = $pdo->prepare("
        INSERT INTO news_comments (post_id, parent_id, name, email, comment, status)
        VALUES (?, ?, ?, ?, ?, 'approved')
    ");
    $stmt->execute([$postId, $parentId, $name, $email, $comment]);
    $commentId = $pdo->lastInsertId();
} catch (PDOException $e) {
    error_log('Comment insert failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات أثناء حفظ التعليق.']);
    exit;
}

// 4) Calculate nesting depth for styling
$depth = 0;
$currentParentId = $parentId;
while ($currentParentId) {
    $stmt = $pdo->prepare("SELECT parent_id FROM news_comments WHERE id = ? LIMIT 1");
    $stmt->execute([$currentParentId]);
    $row = $stmt->fetch();
    if ($row) {
        $depth++;
        $currentParentId = $row['parent_id'];
    } else {
        break;
    }
}
$padding = min($depth * 40, 120);

// 5) Render newly created comment HTML
ob_start();
?>
<div class="comment" id="comment-<?= $commentId ?>" style="margin-right: <?= $padding ?>px; display: none;">
    <div class="avatar"><?= htmlspecialchars(mb_substr($name, 0, 1)) ?></div>
    <div class="comment-body">
        <div class="comment-head">
            <b><?= htmlspecialchars($name) ?></b>
            <span>الآن</span>
            <?php if ($depth > 0): ?>
                <span class="reply-to-badge">رد</span>
            <?php endif; ?>
        </div>
        <p><?= htmlspecialchars($comment) ?></p>
        <div class="comment-actions">
            <button type="button" class="btn-reply" data-comment-id="<?= $commentId ?>" data-comment-author="<?= htmlspecialchars($name) ?>">رد</button>
        </div>
    </div>
</div>
<?php
$commentHtml = ob_get_clean();

// 6) Generate a new math challenge for next comment (stateless)
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$newAns = $num1 + $num2;
$newTime = time();
$newHash = hash('sha256', $newAns . '|' . $newTime . '|' . $salt);

// 7) Output success JSON response
echo json_encode([
    'success' => true,
    'html' => $commentHtml,
    'parent_id' => $parentId,
    'comment_id' => $commentId,
    'new_spam' => [
        'question' => "$num1 + $num2 = ?",
        'time' => $newTime,
        'hash' => $newHash
    ]
]);

<?php
require_once 'db.php';

// التحقق من وجود short_code في الرابط
$short_code = $_GET['c'] ?? '';

if (empty($short_code)) {
    header("Location: /");
    exit;
}

try {
    $pdo = get_db();
} catch (Exception $e) {
    die("Database connection failed.");
}

// 1) البحث عن الرابط في جدول المقالات news_posts
$stmt = $pdo->prepare("SELECT id, slug FROM news_posts WHERE short_code = ? LIMIT 1");
$stmt->execute([$short_code]);
$post = $stmt->fetch();

$target_url = null;
$is_post = false;
$post_id = null;

if ($post) {
    $target_url = "/" . urlencode($post['slug']);
    $is_post = true;
    $post_id = $post['id'];
} else {
    // 2) إذا لم يوجد في المقالات، نبحث عنه في جدول الروابط المختصرة short_links
    $stmt = $pdo->prepare("SELECT destination_url FROM short_links WHERE short_code = ? LIMIT 1");
    $stmt->execute([$short_code]);
    $link = $stmt->fetch();
    
    if ($link) {
        $target_url = $link['destination_url'];
    }
}

if ($target_url) {
    // 🔹 تحديث عدد المشاهدات/النقرات
    if ($is_post) {
        $stmt_upd = $pdo->prepare("UPDATE news_posts SET views = views + 1 WHERE id = ?");
        $stmt_upd->execute([$post_id]);
    } else {
        $stmt_upd = $pdo->prepare("UPDATE short_links SET clicks = clicks + 1 WHERE short_code = ?");
        $stmt_upd->execute([$short_code]);
    }

    // 🔹 جمع معلومات الزائر
    $ip         = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip         = trim(explode(',', $ip)[0]);
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $referer    = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500);

    // 🔹 جلب معلومات الدولة والمدينة بطريقة آمنة وسريعة
    $country = '';
    $city    = '';
    if (!empty($ip) && $ip !== '127.0.0.1' && $ip !== '::1') {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $geo_json = @file_get_contents("http://ip-api.com/json/" . $ip, false, $ctx);
        if ($geo_json) {
            $geo = json_decode($geo_json, true);
            if (is_array($geo)) {
                $country = $geo['country'] ?? '';
                $city    = $geo['city'] ?? '';
            }
        }
    }

    // 🔹 حفظ البيانات في جدول التتبع
    try {
        $stmt_track = $pdo->prepare("
            INSERT INTO news_tracking (short_code, ip_address, user_agent, referer, country, city)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_track->execute([$short_code, $ip, $user_agent, $referer, $country, $city]);
    } catch (Exception $e) {
        // لا نوقف الزائر إذا فشل التتبع
        error_log('news_tracking insert failed: ' . $e->getMessage());
    }

    // 🔹 تحويل الزائر إلى الرابط المستهدف
    header("Location: " . $target_url, true, 302);
    exit;

} else {
    header("Location: /404");
    exit;
}
?>

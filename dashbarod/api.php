<?php
// dashboard/api.php - AJAX Backend controller
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1) Verify Auth Status
if (!isset($_SESSION['admin_logged'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالدخول (سجل الدخول أولاً)']);
    exit;
}

require_once file_exists(__DIR__ . '/db.php') ? __DIR__ . '/db.php' : __DIR__ . '/../db.php';
$pdo = get_db();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Release session file lock immediately for read operations so concurrent AJAX requests execute in true parallel
if ($_SERVER['REQUEST_METHOD'] === 'GET' || strpos($action, 'analytics_') === 0 || strpos($action, 'list_') === 0 || strpos($action, 'get_') === 0) {
    session_write_close();
}

// Helper: Validate and normalize date string (Y-m-d)
function validate_date_ymd(?string $date): ?string
{
    if (!$date)
        return null;
    $d = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
        return null;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : null;
}

// Helper: Calculate percentage change between current and previous periods
function calc_percentage_change($current, $previous): array
{
    $cur = (float) $current;
    $prev = (float) $previous;
    if ($prev <= 0.0) {
        if ($cur <= 0.0) {
            return ['pct' => 0.0, 'trend' => 'neutral', 'label' => '0%'];
        }
        return ['pct' => 100.0, 'trend' => 'up', 'label' => '+100%'];
    }
    $change = (($cur - $prev) / $prev) * 100.0;
    $change = round($change, 1);
    if ($change > 0) {
        return ['pct' => $change, 'trend' => 'up', 'label' => '+' . $change . '%'];
    } elseif ($change < 0) {
        return ['pct' => $change, 'trend' => 'down', 'label' => $change . '%'];
    }
    return ['pct' => 0.0, 'trend' => 'neutral', 'label' => '0%'];
}

// Helper: Detect the date column in news_tracking ('created_at' or 'clicked_at')
function get_news_tracking_date_col(PDO $pdo): string
{
    static $col = null;
    if ($col === null) {
        $col = 'created_at';
        try {
            $pdo->query("SELECT created_at FROM news_tracking LIMIT 1");
        } catch (Exception $e) {
            try {
                $pdo->query("SELECT clicked_at FROM news_tracking LIMIT 1");
                $col = 'clicked_at';
            } catch (Exception $e2) {
            }
        }
    }
    return $col;
}

// Helper: Detect whether ads_tracking or jb_promo_clicks table exists

function get_ads_table(PDO $pdo): string
{
    static $tbl = null;
    if ($tbl !== null)
        return $tbl;

    $candidates = ['jb_promo_clicks', 'ads_tracking'];
    foreach ($candidates as $t) {
        try {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            if ($count > 0) {
                $tbl = $t;
                return $tbl;
            }
        } catch (Exception $e) {
            // table doesn't exist, try next
        }
    }
    // fallback: any existing table
    foreach ($candidates as $t) {
        try {
            $pdo->query("SELECT 1 FROM `$t` LIMIT 1");
            $tbl = $t;
            return $tbl;
        } catch (Exception $e) {
        }
    }
    $tbl = 'jb_promo_clicks';
    return $tbl;
}

// Helper: Detect the ad source column ('ad_source' or 'product_id')
function get_ads_source_col(PDO $pdo, string $tbl): string
{
    static $col = null;
    if ($col !== null)
        return $col;
    try {
        $pdo->query("SELECT ad_source FROM `$tbl` LIMIT 1");
        $col = 'ad_source';
    } catch (Exception $e) {
        $col = 'product_id';
    }
    return $col;
}

// Helper: Resolve analytics date ranges and previous comparison periods
function resolve_analytics_date_range(string $range, ?string $customStart = null, ?string $customEnd = null): array
{
    $validRanges = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'last_7_days', 'last_30_days', 'custom'];
    if (!in_array($range, $validRanges, true)) {
        $range = 'last_7_days';
    }

    $now = new DateTime('now');
    $todayStr = $now->format('Y-m-d');

    switch ($range) {
        case 'today':
            $label = 'اليوم';
            $prevLabel = 'أمس';
            $currStart = $todayStr . ' 00:00:00';
            $currEnd = $todayStr . ' 23:59:59';
            $yesterdayStr = (clone $now)->modify('-1 day')->format('Y-m-d');
            $prevStart = $yesterdayStr . ' 00:00:00';
            $prevEnd = $yesterdayStr . ' 23:59:59';
            $daysCount = 1;
            break;

        case 'yesterday':
            $label = 'أمس';
            $prevLabel = 'أول أمس';
            $yesterdayStr = (clone $now)->modify('-1 day')->format('Y-m-d');
            $dayBeforeYesterdayStr = (clone $now)->modify('-2 days')->format('Y-m-d');
            $currStart = $yesterdayStr . ' 00:00:00';
            $currEnd = $yesterdayStr . ' 23:59:59';
            $prevStart = $dayBeforeYesterdayStr . ' 00:00:00';
            $prevEnd = $dayBeforeYesterdayStr . ' 23:59:59';
            $daysCount = 1;
            break;

        case 'this_week':
            $label = 'هذا الأسبوع';
            $prevLabel = 'الأسبوع السابق';
            $startWeek = (clone $now)->modify('monday this week');
            $endWeek = (clone $startWeek)->modify('+6 days');
            $currStart = $startWeek->format('Y-m-d') . ' 00:00:00';
            $currEnd = $endWeek->format('Y-m-d') . ' 23:59:59';
            $prevStartWeek = (clone $startWeek)->modify('-7 days');
            $prevEndWeek = (clone $startWeek)->modify('-1 day');
            $prevStart = $prevStartWeek->format('Y-m-d') . ' 00:00:00';
            $prevEnd = $prevEndWeek->format('Y-m-d') . ' 23:59:59';
            $daysCount = 7;
            break;

        case 'last_week':
            $label = 'الأسبوع الماضي';
            $prevLabel = 'الأسبوع قبل الماضي';
            $mondayLastWeek = (clone $now)->modify('monday last week');
            $sundayLastWeek = (clone $mondayLastWeek)->modify('+6 days');
            $currStart = $mondayLastWeek->format('Y-m-d') . ' 00:00:00';
            $currEnd = $sundayLastWeek->format('Y-m-d') . ' 23:59:59';
            $mondayTwoWeeksAgo = (clone $mondayLastWeek)->modify('-7 days');
            $sundayTwoWeeksAgo = (clone $mondayLastWeek)->modify('-1 day');
            $prevStart = $mondayTwoWeeksAgo->format('Y-m-d') . ' 00:00:00';
            $prevEnd = $sundayTwoWeeksAgo->format('Y-m-d') . ' 23:59:59';
            $daysCount = 7;
            break;

        case 'this_month':
            $label = 'هذا الشهر';
            $prevLabel = 'الشهر السابق';
            $startMonth = new DateTime($now->format('Y-m-01'));
            $endMonth = new DateTime($now->format('Y-m-t'));
            $currStart = $startMonth->format('Y-m-d') . ' 00:00:00';
            $currEnd = $endMonth->format('Y-m-d') . ' 23:59:59';
            $prevStartMonth = (clone $startMonth)->modify('-1 month');
            $prevEndMonth = new DateTime($prevStartMonth->format('Y-m-t'));
            $prevStart = $prevStartMonth->format('Y-m-d') . ' 00:00:00';
            $prevEnd = $prevEndMonth->format('Y-m-d') . ' 23:59:59';
            $daysCount = (int) $now->format('t');
            break;

        case 'last_month':
            $label = 'الشهر الماضي';
            $prevLabel = 'الشهر قبل الماضي';
            $startLastMonth = (clone $now)->modify('first day of last month');
            $endLastMonth = (clone $now)->modify('last day of last month');
            $currStart = $startLastMonth->format('Y-m-d') . ' 00:00:00';
            $currEnd = $endLastMonth->format('Y-m-d') . ' 23:59:59';
            $startMonthBefore = (clone $startLastMonth)->modify('-1 month');
            $endMonthBefore = new DateTime($startMonthBefore->format('Y-m-t'));
            $prevStart = $startMonthBefore->format('Y-m-d') . ' 00:00:00';
            $prevEnd = $endMonthBefore->format('Y-m-d') . ' 23:59:59';
            $daysCount = (int) $startLastMonth->format('t');
            break;

        case 'last_30_days':
            $label = 'آخر 30 يوماً';
            $prevLabel = 'الـ 30 يوماً السابقة';
            $start30 = (clone $now)->modify('-29 days');
            $currStart = $start30->format('Y-m-d') . ' 00:00:00';
            $currEnd = $todayStr . ' 23:59:59';
            $prevStart30 = (clone $start30)->modify('-30 days');
            $prevEnd30 = (clone $start30)->modify('-1 day');
            $prevStart = $prevStart30->format('Y-m-d') . ' 00:00:00';
            $prevEnd = $prevEnd30->format('Y-m-d') . ' 23:59:59';
            $daysCount = 30;
            break;

        case 'custom':
            $validStart = validate_date_ymd($customStart);
            $validEnd = validate_date_ymd($customEnd);
            if (!$validStart || !$validEnd || $validStart > $validEnd) {
                return resolve_analytics_date_range('last_7_days');
            }
            $startDate = new DateTime($validStart);
            $endDate = new DateTime($validEnd);
            $diffSeconds = $endDate->getTimestamp() - $startDate->getTimestamp();
            $daysCount = max(1, (int) round($diffSeconds / 86400) + 1);
            if ($daysCount > 366) {
                $daysCount = 366;
                $startDate = (clone $endDate)->modify('-365 days');
                $validStart = $startDate->format('Y-m-d');
            }
            $label = "فترة مخصصة ($validStart إلى $validEnd)";
            $prevLabel = "الفترة السابقة المماثلة ($daysCount يوماً)";
            $currStart = $validStart . ' 00:00:00';
            $currEnd = $validEnd . ' 23:59:59';
            $prevEndDate = (clone $startDate)->modify('-1 day');
            $prevStartDate = (clone $prevEndDate)->modify('-' . ($daysCount - 1) . ' days');
            $prevStart = $prevStartDate->format('Y-m-d') . ' 00:00:00';
            $prevEnd = $prevEndDate->format('Y-m-d') . ' 23:59:59';
            break;

        case 'last_7_days':
        default:
            $range = 'last_7_days';
            $label = 'آخر 7 أيام';
            $prevLabel = 'الـ 7 أيام السابقة';
            $start7 = (clone $now)->modify('-6 days');
            $currStart = $start7->format('Y-m-d') . ' 00:00:00';
            $currEnd = $todayStr . ' 23:59:59';
            $prevStart7 = (clone $start7)->modify('-7 days');
            $prevEnd7 = (clone $start7)->modify('-1 day');
            $prevStart = $prevStart7->format('Y-m-d') . ' 00:00:00';
            $prevEnd = $prevEnd7->format('Y-m-d') . ' 23:59:59';
            $daysCount = 7;
            break;
    }

    return [
        'range' => $range,
        'label' => $label,
        'prev_label' => $prevLabel,
        'days_count' => $daysCount,
        'curr_start' => $currStart,
        'curr_end' => $currEnd,
        'curr_start_d' => substr($currStart, 0, 10),
        'curr_end_d' => substr($currEnd, 0, 10),
        'prev_start' => $prevStart,
        'prev_end' => $prevEnd,
        'prev_start_d' => substr($prevStart, 0, 10),
        'prev_end_d' => substr($prevEnd, 0, 10),
    ];
}

// Helper: Handle file upload to root uploads folder
function handle_image_upload($file_key)
{
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$file_key];
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('الصورة كبيرة بزاف أو غير صالحة. الحد الأقصى 5MB.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        throw new Exception('نوع الملف غير مدعوم. الصيغ المسموحة: ' . implode(', ', $allowed));
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMime, true) || @getimagesize($file['tmp_name']) === false) {
        throw new Exception('محتوى الملف ليس صورة صالحة.');
    }

    $filename = time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
    $target_dir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($target_dir) && !@mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
        throw new Exception('مجلد رفع الصور غير موجود أو ما عندوش صلاحية الكتابة.');
    }
    if (!is_writable($target_dir)) {
        throw new Exception('مجلد رفع الصور ما عندوش صلاحية الكتابة.');
    }

    if (move_uploaded_file($file['tmp_name'], $target_dir . $filename)) {
        return $filename;
    }
    return null;
}

// Helper: Download image from external URL to uploads folder and return filename
function handle_image_from_url($url): ?string
{
    $url = trim($url);
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    // If already on our CDN / uploads folder, just return the filename
    $cdnPrefix = IMAGE_BASE_URL;
    if (strpos($url, $cdnPrefix) === 0) {
        $basename = basename(parse_url($url, PHP_URL_PATH));
        if (!empty($basename)) {
            return $basename;
        }
    }

    $target_dir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($target_dir) && !@mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
        return null;
    }
    if (!is_writable($target_dir)) return null;

    $data = null;
    $mimeType = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mimeType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($data)) {
            return null;
        }
    } else {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];
        $context = stream_context_create($opts);
        $data = @file_get_contents($url, false, $context);
        if ($data === false || empty($data)) {
            return null;
        }
    }

    $imageInfo = @getimagesizefromstring($data);
    if (!$imageInfo || empty($imageInfo['mime'])) return null;
    $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = strtolower($imageInfo['mime']);
    if (!isset($mimeMap[$mime])) return null;
    $ext = $mimeMap[$mime];

    $filename = time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
    if (file_put_contents($target_dir . $filename, $data) !== false) {
        return $filename;
    }

    return null;
}

// Helper: Unique post slug generator
function generate_post_slug($title, $pdo, $postId = null)
{
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $title);
    $slug = trim($slug, '-');
    $slug = mb_strtolower($slug, 'UTF-8');
    if (empty($slug)) {
        $slug = 'post-' . rand(100, 999);
    }

    $originalSlug = $slug;
    $i = 1;
    do {
        if ($postId) {
            $stmt = $pdo->prepare("SELECT id FROM news_posts WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $postId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM news_posts WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        $exists = $stmt->fetch();
        if ($exists) {
            $slug = $originalSlug . '-' . $i;
            $i++;
        }
    } while ($exists);
    return $slug;
}
function generate_banner_slug(PDO $pdo): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $slug = '';
        for ($i = 0; $i < 12; $i++) {
            $slug .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare("SELECT 1 FROM 9s_custom_banners WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
    } while ($stmt->fetch());
    return $slug;
}
// Helper: Unique category slug generator
function generate_cat_slug($name, $pdo, $catId = null)
{
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $name);
    $slug = trim($slug, '-');
    $slug = mb_strtolower($slug, 'UTF-8');
    if (empty($slug)) {
        $slug = 'category-' . rand(100, 999);
    }

    $originalSlug = $slug;
    $i = 1;
    do {
        if ($catId) {
            $stmt = $pdo->prepare("SELECT id FROM news_categories WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $catId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM news_categories WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        $exists = $stmt->fetch();
        if ($exists) {
            $slug = $originalSlug . '-' . $i;
            $i++;
        }
    } while ($exists);
    return $slug;
}

try {
    switch ($action) {

        // ================= ANALYTICS: OVERVIEW & KPIS =================
        case 'analytics_overview':
            $range = $_GET['range'] ?? 'last_7_days';
            $cStart = $_GET['start_date'] ?? null;
            $cEnd = $_GET['end_date'] ?? null;
            $dateInfo = resolve_analytics_date_range($range, $cStart, $cEnd);

            $c_start = $dateInfo['curr_start'];
            $c_end = $dateInfo['curr_end'];
            $p_start = $dateInfo['prev_start'];
            $p_end = $dateInfo['prev_end'];
            $trackCol = get_news_tracking_date_col($pdo);
            $adsTbl = get_ads_table($pdo);
            $adsCol = get_ads_source_col($pdo, $adsTbl);

            // 1. Articles / Posts (All-time + period) in 1 fast query
            $total_posts = 0;
            $total_published = 0;
            $total_draft = 0;
            $total_archived = 0;
            $total_views = 0;
            $avg_views = 0;
            $max_views = 0;
            $curr_new_posts = 0;
            $prev_new_posts = 0;
            try {
                $pStmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) AS total,
                        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
                        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived,
                        COALESCE(SUM(views), 0) AS total_views,
                        COALESCE(AVG(views), 0) AS avg_views,
                        COALESCE(MAX(views), 0) AS max_views,
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_new,
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_new
                    FROM news_posts
                ");
                $pStmt->execute([$c_start, $c_end, $p_start, $p_end]);
                $pData = $pStmt->fetch() ?: [];

                $total_posts = (int) ($pData['total'] ?? 0);
                $total_published = (int) ($pData['published'] ?? 0);
                $total_draft = (int) ($pData['draft'] ?? 0);
                $total_archived = (int) ($pData['archived'] ?? 0);
                $total_views = (int) ($pData['total_views'] ?? 0);
                $avg_views = (float) ($pData['avg_views'] ?? 0);
                $max_views = (int) ($pData['max_views'] ?? 0);
                $curr_new_posts = (int) ($pData['curr_new'] ?? 0);
                $prev_new_posts = (int) ($pData['prev_new'] ?? 0);
            } catch (Exception $e) {
            }

            // 2. Views & Visits (From news_tracking)
            $total_visits_all_time = 0;
            $curr_visits = 0;
            $prev_visits = 0;
            try {
                $total_visits_all_time = (int) $pdo->query("SELECT COUNT(*) FROM news_tracking")->fetchColumn();
                $vStmt = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN $trackCol BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_v,
                        SUM(CASE WHEN $trackCol BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_v
                    FROM news_tracking
                    WHERE $trackCol BETWEEN ? AND ?
                ");
                $vStmt->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $vData = $vStmt->fetch() ?: [];
                $curr_visits = (int) ($vData['curr_v'] ?? 0);
                $prev_visits = (int) ($vData['prev_v'] ?? 0);
            } catch (Exception $e) {
            }

            $has_tracking_visits = ($total_visits_all_time > 0);
            $display_period_visits = $has_tracking_visits ? $curr_visits : $curr_new_posts;
            $display_prev_visits = $has_tracking_visits ? $prev_visits : $prev_new_posts;
            $visits_change = calc_percentage_change($display_period_visits, $display_prev_visits);

            // 3. Ad & Banner Clicks (From ads_tracking / jb_promo_clicks)
            $total_ad_clicks = 0;
            $curr_ad_clicks = 0;
            $prev_ad_clicks = 0;
            $top_ad_source = '—';
            try {
                $botFilter = "(user_agent IS NULL OR user_agent NOT LIKE '%Mediapartners-Google%')";
                $total_ad_clicks = (int) $pdo->query("SELECT COUNT(*) FROM `$adsTbl` WHERE $botFilter")->fetchColumn();
                $aStmt = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN clicked_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_a,
                        SUM(CASE WHEN clicked_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_a
                    FROM `$adsTbl`
                    WHERE clicked_at BETWEEN ? AND ? AND $botFilter
                ");
                $aStmt->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $aData = $aStmt->fetch() ?: [];
                $curr_ad_clicks = (int) ($aData['curr_a'] ?? 0);
                $prev_ad_clicks = (int) ($aData['prev_a'] ?? 0);

                $topStmt = $pdo->prepare("
                    SELECT COALESCE(NULLIF(`$adsCol`, ''), 'بدون مصدر') AS src, COUNT(*) AS c 
                    FROM `$adsTbl` 
                    WHERE clicked_at BETWEEN ? AND ? AND $botFilter 
                    GROUP BY src 
                    ORDER BY c DESC 
                    LIMIT 1
                ");
                $topStmt->execute([$c_start, $c_end]);
                $top_ad_source = $topStmt->fetchColumn() ?: '—';
            } catch (Exception $e) {
            }
            $ad_clicks_change = calc_percentage_change($curr_ad_clicks, $prev_ad_clicks);

            // 4. Comments
            $total_comments = 0;
            $curr_comments = 0;
            $prev_comments = 0;
            $curr_approved_comments = 0;
            $curr_hidden_comments = 0;
            $curr_replies = 0;
            try {
                $total_comments = (int) $pdo->query("SELECT COUNT(*) FROM news_comments")->fetchColumn();
                $cStmt = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_c,
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_c,
                        SUM(CASE WHEN (status = 'approved' OR status IS NULL) AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_app,
                        SUM(CASE WHEN status = 'hidden' AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_hid,
                        SUM(CASE WHEN parent_id IS NOT NULL AND parent_id > 0 AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_rep
                    FROM news_comments
                    WHERE created_at BETWEEN ? AND ?
                ");
                $cStmt->execute([$c_start, $c_end, $p_start, $p_end, $c_start, $c_end, $c_start, $c_end, $c_start, $c_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $cData = $cStmt->fetch() ?: [];
                $curr_comments = (int) ($cData['curr_c'] ?? 0);
                $prev_comments = (int) ($cData['prev_c'] ?? 0);
                $curr_approved_comments = (int) ($cData['curr_app'] ?? 0);
                $curr_hidden_comments = (int) ($cData['curr_hid'] ?? 0);
                $curr_replies = (int) ($cData['curr_rep'] ?? 0);
            } catch (Exception $e) {
                try {
                    $cStmt = $pdo->prepare("
                        SELECT 
                            SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_c,
                            SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_c
                        FROM news_comments
                        WHERE created_at BETWEEN ? AND ?
                    ");
                    $cStmt->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                    $cData = $cStmt->fetch() ?: [];
                    $curr_comments = (int) ($cData['curr_c'] ?? 0);
                    $prev_comments = (int) ($cData['prev_c'] ?? 0);
                    $curr_approved_comments = $curr_comments;
                } catch (Exception $e2) {
                }
            }

            // 5. Short Links & Clicks (direct fast query on short_links)
            // ✅ بعد — clicks derived from news_tracking via short_code match
            $total_short_links = 0;
            $total_link_clicks = 0;
            $curr_short_links = 0;
            $prev_short_links = 0;
            try {
                // إجمالي الروابط (all-time) — من جدول short_links
                $total_short_links = (int) $pdo->query("SELECT COUNT(*) FROM short_links")->fetchColumn();

                // إجمالي النقرات — كل صفوف news_tracking اللي الـ short_code ديالها كاين فـ short_links
                $total_link_clicks = (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM news_tracking t
                    INNER JOIN short_links s
                        ON s.short_code COLLATE utf8mb4_unicode_ci
                        = t.short_code COLLATE utf8mb4_unicode_ci
                ")->fetchColumn();

                // نقرات الفترة (الحالية + السابقة) — نفس الـ JOIN مع فلترة التاريخ
                $slStmt = $pdo->prepare("
                    SELECT
                        SUM(CASE WHEN t.$trackCol BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_sl,
                        SUM(CASE WHEN t.$trackCol BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_sl
                    FROM news_tracking t
                    INNER JOIN short_links s
                        ON s.short_code COLLATE utf8mb4_unicode_ci
                        = t.short_code COLLATE utf8mb4_unicode_ci
                    WHERE t.$trackCol BETWEEN ? AND ?
                ");
                $slStmt->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $slPeriod = $slStmt->fetch() ?: [];
                $curr_short_links = (int) ($slPeriod['curr_sl'] ?? 0);
                $prev_short_links = (int) ($slPeriod['prev_sl'] ?? 0);
            } catch (Exception $e) {
            }

            // 6. Users
            $total_users = 0;
            $admin_users = 0;
            $editor_users = 0;
            $curr_new_users = 0;
            $prev_new_users = 0;
            try {
                $uStmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) AS total,
                        SUM(CASE WHEN role = 1 THEN 1 ELSE 0 END) AS admins,
                        SUM(CASE WHEN role = 0 THEN 1 ELSE 0 END) AS editors,
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_u,
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_u
                    FROM 9s_users
                ");
                $uStmt->execute([$c_start, $c_end, $p_start, $p_end]);
                $uData = $uStmt->fetch() ?: [];
                $total_users = (int) ($uData['total'] ?? 0);
                $admin_users = (int) ($uData['admins'] ?? 0);
                $editor_users = (int) ($uData['editors'] ?? 0);
                $curr_new_users = (int) ($uData['curr_u'] ?? 0);
                $prev_new_users = (int) ($uData['prev_u'] ?? 0);
            } catch (Exception $e) {
            }

            // 7. Categories & Monetization
            $total_categories = 0;
            $active_categories = 0;
            try {
                $catData = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM news_categories")->fetch();
                $total_categories = (int) ($catData['total'] ?? 0);
                $active_categories = (int) ($catData['active'] ?? 0);
            } catch (Exception $e) {
            }

            $active_banners = 0;
            $total_banners = 0;
            try {
                $bData = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM 9s_custom_banners")->fetch();
                $total_banners = (int) ($bData['total'] ?? 0);
                $active_banners = (int) ($bData['active'] ?? 0);
            } catch (Exception $e) {
            }

            $active_adsense = 0;
            try {
                $active_adsense = (int) $pdo->query("SELECT COUNT(*) FROM 9s_adsense WHERE is_active = 1")->fetchColumn();
            } catch (Exception $e) {
            }

            // Geo info from news_tracking
            $top_country = 'المغرب';
            $unique_countries = 0;
            try {
                $gStmt = $pdo->prepare("SELECT country, COUNT(*) as c FROM news_tracking WHERE country IS NOT NULL AND country != '' AND $trackCol BETWEEN ? AND ? GROUP BY country ORDER BY c DESC LIMIT 1");
                $gStmt->execute([$c_start, $c_end]);
                $top_country = $gStmt->fetchColumn() ?: 'المغرب';

                $gStmt2 = $pdo->prepare("SELECT COUNT(DISTINCT country) FROM news_tracking WHERE country IS NOT NULL AND country != '' AND $trackCol BETWEEN ? AND ?");
                $gStmt2->execute([$c_start, $c_end]);
                $unique_countries = (int) $gStmt2->fetchColumn();
            } catch (Exception $e) {
            }
            $top_country_visits = 0;
            try {
                $gStmt = $pdo->prepare("
                    SELECT country, COUNT(*) as c 
                    FROM news_tracking 
                    WHERE country IS NOT NULL AND country != '' 
                    AND $trackCol BETWEEN ? AND ? 
                    GROUP BY country 
                    ORDER BY c DESC 
                    LIMIT 1
                ");
                $gStmt->execute([$c_start, $c_end]);
                $row = $gStmt->fetch();
                if ($row) {
                    $top_country = $row['country'];
                    $top_country_visits = (int) $row['c'];
                }
                // ... (الكود القديم ديال gStmt2 كتبقى)
            } catch (Exception $e) {
            }
            // Sparklines: visits, posts, comments
            $visitSparkRaw = [];
            try {
                $s = $pdo->prepare("SELECT DATE($trackCol) as day, COUNT(*) as cnt FROM news_tracking WHERE $trackCol BETWEEN ? AND ? GROUP BY DATE($trackCol) ORDER BY day ASC");
                $s->execute([$c_start, $c_end]);
                $visitSparkRaw = $s->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
            }

            $postSparkRaw = [];
            try {
                $s = $pdo->prepare("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM news_posts WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY day ASC");
                $s->execute([$c_start, $c_end]);
                $postSparkRaw = $s->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
            }

            $cmtSparkRaw = [];
            try {
                $s = $pdo->prepare("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM news_comments WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY day ASC");
                $s->execute([$c_start, $c_end]);
                $cmtSparkRaw = $s->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
            }

            echo json_encode([
                'success' => true,
                'period' => [
                    'range' => $dateInfo['range'],
                    'label' => $dateInfo['label'],
                    'prev_label' => $dateInfo['prev_label'],
                    'days_count' => $dateInfo['days_count'],
                    'curr_start' => $dateInfo['curr_start_d'],
                    'curr_end' => $dateInfo['curr_end_d'],
                    'prev_start' => $dateInfo['prev_start_d'],
                    'prev_end' => $dateInfo['prev_end_d'],
                ],
                'kpis' => [
                    'posts' => [
                        'all_time' => $total_posts,
                        'published' => $total_published,
                        'draft' => $total_draft,
                        'archived' => $total_archived,
                        'current' => $curr_new_posts,
                        'previous' => $prev_new_posts,
                        'change' => calc_percentage_change($curr_new_posts, $prev_new_posts),
                    ],
                    'views' => [
                        'all_time' => $total_views,
                        'avg' => round($avg_views, 1),
                        'max' => $max_views,
                        'period_visits' => $display_period_visits,
                        'prev_visits' => $display_prev_visits,
                        'change' => $visits_change,
                        'has_tracking' => $has_tracking_visits,
                        'total_visits' => $total_visits_all_time,
                    ],
                    'ads_clicks' => [
                        'all_time' => $total_ad_clicks,
                        'current' => $curr_ad_clicks,
                        'previous' => $prev_ad_clicks,
                        'change' => $ad_clicks_change,
                        'top_source' => $top_ad_source,
                    ],
                    'comments' => [
                        'all_time' => $total_comments,
                        'current' => $curr_comments,
                        'previous' => $prev_comments,
                        'change' => calc_percentage_change($curr_comments, $prev_comments),
                        'approved' => $curr_approved_comments,
                        'hidden' => $curr_hidden_comments,
                        'replies' => $curr_replies,
                    ],
                    'short_links' => [
                        'all_time' => $total_short_links,
                        'current' => $curr_short_links,
                        'previous' => $prev_short_links,
                        'change' => calc_percentage_change($curr_short_links, $prev_short_links),
                        'total_clicks' => $total_link_clicks,
                    ],
                    'geo' => [
                        'top_country' => $top_country,
                        'top_country_visits' => $top_country_visits,
                        'countries_cnt' => $unique_countries,
                    ],
                    'users' => [
                        'all_time' => $total_users,
                        'admins' => $admin_users,
                        'editors' => $editor_users,
                        'current' => $curr_new_users,
                        'previous' => $prev_new_users,
                        'change' => calc_percentage_change($curr_new_users, $prev_new_users),
                    ],
                    'categories' => [
                        'total' => $total_categories,
                        'active' => $active_categories,
                    ],
                    'monetization' => [
                        'banners' => $active_banners,
                        'banners_total' => $total_banners,
                        'adsense' => $active_adsense,
                    ]
                ],
                'sparklines' => [
                    'visits' => array_values($visitSparkRaw),
                    'posts' => array_values($postSparkRaw),
                    'comments' => array_values($cmtSparkRaw),
                ]
            ]);
            break;

        // ================= ANALYTICS: TRENDS OVER TIME =================
        case 'analytics_trends':
            $range = $_GET['range'] ?? 'last_7_days';
            $cStart = $_GET['start_date'] ?? null;
            $cEnd = $_GET['end_date'] ?? null;
            $dateInfo = resolve_analytics_date_range($range, $cStart, $cEnd);

            $c_start = $dateInfo['curr_start'];
            $c_end = $dateInfo['curr_end'];
            $trackCol = get_news_tracking_date_col($pdo);
            $adsTbl = get_ads_table($pdo);

            $startDate = new DateTime($dateInfo['curr_start_d']);
            $endDate = new DateTime($dateInfo['curr_end_d']);
            $endDate->modify('+1 day');
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($startDate, $interval, $endDate);

            $daysMap = [];
            foreach ($period as $dt) {
                $daysMap[$dt->format('Y-m-d')] = [
                    'visits' => 0,
                    'ad_clicks' => 0,
                    'posts' => 0,
                    'comments' => 0,
                    'links' => 0,
                ];
            }

            // Real visits from news_tracking
            try {
                $stmt = $pdo->prepare("
                    SELECT DATE($trackCol) AS day, COUNT(*) AS cnt
                    FROM news_tracking
                    WHERE $trackCol BETWEEN ? AND ?
                    GROUP BY DATE($trackCol)
                ");
                $stmt->execute([$c_start, $c_end]);
                while ($row = $stmt->fetch()) {
                    if (isset($daysMap[$row['day']])) {
                        $daysMap[$row['day']]['visits'] = (int) $row['cnt'];
                    }
                }
            } catch (Exception $e) {
            }

            // Promotional clicks from ads_tracking / jb_promo_clicks
            try {
                $stmt = $pdo->prepare("
                    SELECT DATE(clicked_at) AS day, COUNT(*) AS cnt
                    FROM `$adsTbl`
                    WHERE clicked_at BETWEEN ? AND ? AND (user_agent IS NULL OR user_agent NOT LIKE '%Mediapartners-Google%')
                    GROUP BY DATE(clicked_at)
                ");
                $stmt->execute([$c_start, $c_end]);
                while ($row = $stmt->fetch()) {
                    if (isset($daysMap[$row['day']])) {
                        $daysMap[$row['day']]['ad_clicks'] = (int) $row['cnt'];
                    }
                }
            } catch (Exception $e) {
            }

            // Posts per day
            try {
                $stmt = $pdo->prepare("
                    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
                    FROM news_posts
                    WHERE created_at BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                ");
                $stmt->execute([$c_start, $c_end]);
                while ($row = $stmt->fetch()) {
                    if (isset($daysMap[$row['day']])) {
                        $daysMap[$row['day']]['posts'] = (int) $row['cnt'];
                    }
                }
            } catch (Exception $e) {
            }

            // Comments per day
            try {
                $stmt = $pdo->prepare("
                    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
                    FROM news_comments
                    WHERE created_at BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                ");
                $stmt->execute([$c_start, $c_end]);
                while ($row = $stmt->fetch()) {
                    if (isset($daysMap[$row['day']])) {
                        $daysMap[$row['day']]['comments'] = (int) $row['cnt'];
                    }
                }
            } catch (Exception $e) {
            }

            // Short links per day (fast indexed query on short_links)
            try {
                $stmt = $pdo->prepare("
                    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
                    FROM short_links
                    WHERE created_at BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                ");
                $stmt->execute([$c_start, $c_end]);
                while ($row = $stmt->fetch()) {
                    if (isset($daysMap[$row['day']])) {
                        $daysMap[$row['day']]['links'] = (int) $row['cnt'];
                    }
                }
            } catch (Exception $e) {
            }

            $labels = array_keys($daysMap);
            $visits = array_column($daysMap, 'visits');
            $ad_clicks = array_column($daysMap, 'ad_clicks');
            $posts = array_column($daysMap, 'posts');
            $comments = array_column($daysMap, 'comments');
            $links = array_column($daysMap, 'links');

            echo json_encode([
                'success' => true,
                'period' => $dateInfo['label'],
                'labels' => $labels,
                'visits' => $visits,
                'ad_clicks' => $ad_clicks,
                'posts' => $posts,
                'comments' => $comments,
                'links' => $links,
            ]);
            break;

        // ================= ANALYTICS: DISTRIBUTIONS =================
        case 'analytics_distribution':
            $range = $_GET['range'] ?? 'last_7_days';
            $cStart = $_GET['start_date'] ?? null;
            $cEnd = $_GET['end_date'] ?? null;
            $dateInfo = resolve_analytics_date_range($range, $cStart, $cEnd);
            $trackCol = get_news_tracking_date_col($pdo);
            $adsTbl = get_ads_table($pdo);
            $adsCol = get_ads_source_col($pdo, $adsTbl);

            // 1. Categories
            $cats = [];
            try {
                $stmt = $pdo->query("
                    SELECT c.id, c.name,
                           COUNT(p.id) AS post_count,
                           COALESCE(SUM(p.views), 0) AS total_views
                    FROM news_categories c
                    LEFT JOIN news_posts p ON p.category_id = c.id
                    GROUP BY c.id, c.name
                    ORDER BY post_count DESC
                    LIMIT 8
                ");
                $cats = $stmt->fetchAll();
            } catch (Exception $e) {
            }

            // 2. Promotional clicks by source/product
            $adSources = [];
            try {
                $stmt = $pdo->query("
                    SELECT COALESCE(NULLIF(`$adsCol`, ''), 'بدون مصدر') AS source, COUNT(*) AS clicks
                    FROM `$adsTbl`
                    WHERE user_agent IS NULL OR user_agent NOT LIKE '%Mediapartners-Google%'
                    GROUP BY source
                    ORDER BY clicks DESC
                    LIMIT 8
                ");
                $adSources = $stmt->fetchAll();
            } catch (Exception $e) {
            }

            // 3. Top Countries (from news_tracking)
            $countries = [];
            try {
                $stmt = $pdo->query("
                    SELECT COALESCE(NULLIF(country, ''), 'المغرب') AS country, COUNT(*) AS visits
                    FROM news_tracking
                    WHERE country IS NOT NULL AND country != ''
                    GROUP BY country
                    ORDER BY visits DESC
                    LIMIT 6
                ");
                $countries = $stmt->fetchAll();
            } catch (Exception $e) {
            }

            // 4. Top Referrers
            $referrers = [];
            try {
                $stmt = $pdo->query("
                    SELECT COALESCE(NULLIF(referer, ''), 'Direct / مباشر') AS referer, COUNT(*) AS visits
                    FROM news_tracking
                    GROUP BY referer
                    ORDER BY visits DESC
                    LIMIT 6
                ");
                $referrers = $stmt->fetchAll();
            } catch (Exception $e) {
            }

            // 5. Post Status
            $postStatuses = [];
            try {
                $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM news_posts GROUP BY status");
                $postStatuses = $stmt->fetchAll();
            } catch (Exception $e) {
            }

            // 6. Comments Status
            $cmtStatuses = [];
            try {
                $stmt = $pdo->query("SELECT COALESCE(status, 'approved') AS status, COUNT(*) AS count FROM news_comments GROUP BY COALESCE(status, 'approved')");
                $cmtStatuses = $stmt->fetchAll();
            } catch (Exception $e) {
            }

            // 7. Authors Activity
            $authors = [];
            try {
                $stmt = $pdo->query("
                    SELECT u.id, u.username, u.role,
                           COUNT(p.id) AS post_count,
                           COALESCE(SUM(p.views), 0) AS total_views
                    FROM 9s_users u
                    LEFT JOIN news_posts p ON p.auther_id = u.id
                    GROUP BY u.id, u.username, u.role
                    ORDER BY post_count DESC
                    LIMIT 8
                ");
                $authors = $stmt->fetchAll();
            } catch (Exception $e) {
            }

            echo json_encode([
                'success' => true,
                'categories' => $cats,
                'ad_sources' => $adSources,
                'countries' => $countries,
                'referrers' => $referrers,
                'post_statuses' => $postStatuses,
                'cmt_statuses' => $cmtStatuses,
                'authors' => $authors,
            ]);
            break;

        // ================= ANALYTICS: COMPARISONS =================
        case 'analytics_comparison':
            $range = $_GET['range'] ?? 'last_7_days';
            $cStart = $_GET['start_date'] ?? null;
            $cEnd = $_GET['end_date'] ?? null;
            $dateInfo = resolve_analytics_date_range($range, $cStart, $cEnd);

            $c_start = $dateInfo['curr_start'];
            $c_end = $dateInfo['curr_end'];
            $p_start = $dateInfo['prev_start'];
            $p_end = $dateInfo['prev_end'];
            $trackCol = get_news_tracking_date_col($pdo);
            $adsTbl = get_ads_table($pdo);

            // Article visits comparison from news_tracking in 1 indexed query
            $c_visits = 0;
            $p_visits = 0;
            try {
                $s = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN $trackCol BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_v,
                        SUM(CASE WHEN $trackCol BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_v
                    FROM news_tracking
                    WHERE $trackCol BETWEEN ? AND ?
                ");
                $s->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $row = $s->fetch() ?: [];
                $c_visits = (int) ($row['curr_v'] ?? 0);
                $p_visits = (int) ($row['prev_v'] ?? 0);
            } catch (Exception $e) {
            }

            // Ad clicks comparison from ads_tracking / jb_promo_clicks in 1 indexed query
            $c_ads = 0;
            $p_ads = 0;
            try {
                $s = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN clicked_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_a,
                        SUM(CASE WHEN clicked_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_a
                    FROM `$adsTbl`
                    WHERE clicked_at BETWEEN ? AND ? AND (user_agent IS NULL OR user_agent NOT LIKE '%Mediapartners-Google%')
                ");
                $s->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $row = $s->fetch() ?: [];
                $c_ads = (int) ($row['curr_a'] ?? 0);
                $p_ads = (int) ($row['prev_a'] ?? 0);
            } catch (Exception $e) {
            }

            // Posts comparison in 1 indexed query
            $c_posts = 0;
            $p_posts = 0;
            try {
                $s = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_p,
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_p
                    FROM news_posts
                    WHERE created_at BETWEEN ? AND ?
                ");
                $s->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $row = $s->fetch() ?: [];
                $c_posts = (int) ($row['curr_p'] ?? 0);
                $p_posts = (int) ($row['prev_p'] ?? 0);
            } catch (Exception $e) {
            }

            // Comments comparison in 1 indexed query
            $c_cmts = 0;
            $p_cmts = 0;
            try {
                $s = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_c,
                        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_c
                    FROM news_comments
                    WHERE created_at BETWEEN ? AND ?
                ");
                $s->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $row = $s->fetch() ?: [];
                $c_cmts = (int) ($row['curr_c'] ?? 0);
                $p_cmts = (int) ($row['prev_c'] ?? 0);
            } catch (Exception $e) {
            }

            // Short links comparison (fast indexed query on short_links)
            // ✅ بعد — نقرات الروابط المختصرة من news_tracking
            $c_links = 0;
            $p_links = 0;

            try {
                $s = $pdo->prepare("
                    SELECT
                        SUM(CASE WHEN t.$trackCol BETWEEN ? AND ? THEN 1 ELSE 0 END) AS curr_l,
                        SUM(CASE WHEN t.$trackCol BETWEEN ? AND ? THEN 1 ELSE 0 END) AS prev_l
                    FROM news_tracking t
                    INNER JOIN short_links s
                        ON s.short_code COLLATE utf8mb4_unicode_ci
                        = t.short_code COLLATE utf8mb4_unicode_ci
                    WHERE t.$trackCol BETWEEN ? AND ?
                ");
                $s->execute([$c_start, $c_end, $p_start, $p_end, min($c_start, $p_start), max($c_end, $p_end)]);
                $row = $s->fetch() ?: [];
                $c_links = (int) ($row['curr_l'] ?? 0);
                $p_links = (int) ($row['prev_l'] ?? 0);
            } catch (Exception $e) {
            }

            echo json_encode([
                'success' => true,
                'period' => [
                    'current' => $dateInfo['label'],
                    'previous' => $dateInfo['prev_label'],
                ],
                'comparison' => [
                    'visits' => [
                        'title' => 'زيارات ومطالعات المقالات',
                        'current' => $c_visits,
                        'previous' => $p_visits,
                        'diff' => $c_visits - $p_visits,
                        'change' => calc_percentage_change($c_visits, $p_visits),
                    ],
                    'ad_clicks' => [
                        'title' => 'نقرات الإعلانات والبنرات',
                        'current' => $c_ads,
                        'previous' => $p_ads,
                        'diff' => $c_ads - $p_ads,
                        'change' => calc_percentage_change($c_ads, $p_ads),
                    ],
                    'posts' => [
                        'title' => 'المقالات الجديدة',
                        'current' => $c_posts,
                        'previous' => $p_posts,
                        'diff' => $c_posts - $p_posts,
                        'change' => calc_percentage_change($c_posts, $p_posts),
                    ],
                    'comments' => [
                        'title' => 'التعليقات الجديدة',
                        'current' => $c_cmts,
                        'previous' => $p_cmts,
                        'diff' => $c_cmts - $p_cmts,
                        'change' => calc_percentage_change($c_cmts, $p_cmts),
                    ],
                    'links' => [
                        'title' => 'الروابط المختصرة',
                        'current' => $c_links,
                        'previous' => $p_links,
                        'diff' => $c_links - $p_links,
                        'change' => calc_percentage_change($c_links, $p_links),
                    ],
                ]
            ]);
            break;

        // ================= ANALYTICS: TOP ARTICLES TABLE =================
        case 'analytics_top_posts':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = max(5, min(50, (int) ($_GET['per_page'] ?? 12)));
            $offset = ($page - 1) * $per_page;
            $search = trim($_GET['search'] ?? '');
            $sortBy = $_GET['sort_by'] ?? 'views';
            $order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $catId = !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null;

            $sortColumnMap = [
                'views' => 'p.views',
                'id' => 'p.id',
                'created_at' => 'p.created_at',
            ];
            $sqlSort = $sortColumnMap[$sortBy] ?? 'p.views';

            $range = $_GET['range'] ?? 'last_7_days';
            $cStart = $_GET['start_date'] ?? null;
            $cEnd = $_GET['end_date'] ?? null;
            $dateInfo = resolve_analytics_date_range($range, $cStart, $cEnd);
            $c_start = $dateInfo['curr_start'];
            $c_end = $dateInfo['curr_end'];
            $trackCol = get_news_tracking_date_col($pdo);

            $where = ['1=1'];
            $params = [];

            if (!empty($search)) {
                $where[] = '(p.title LIKE ? OR p.short_code LIKE ?)';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }

            if ($catId) {
                $where[] = 'p.category_id = ?';
                $params[] = $catId;
            }

            if (!empty($_GET['filter_date_range']) && $_GET['filter_date_range'] === '1') {
                $where[] = 'p.created_at BETWEEN ? AND ?';
                $params[] = $c_start;
                $params[] = $c_end;
            }

            $whereClause = implode(' AND ', $where);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM news_posts p WHERE $whereClause");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($total / $per_page));

            // Fast 2-step retrieval: First fetch the 12 target posts without heavy subqueries
            $sql = "
                SELECT p.id, p.title, p.status, p.views, p.created_at, p.image, p.short_code,
                       c.name AS category_name
                FROM news_posts p
                LEFT JOIN news_categories c ON c.id = p.category_id
                WHERE $whereClause
                ORDER BY $sqlSort $order, p.id DESC
                LIMIT $per_page OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $posts = $stmt->fetchAll();

            // Then batch-enrich ONLY those 12 posts with period visits & comments count
            if (!empty($posts)) {
                $postIds = array_column($posts, 'id');
                $shortCodes = array_filter(array_column($posts, 'short_code'));

                // Batch comments
                $cmCounts = [];
                if (!empty($postIds)) {
                    $inIds = implode(',', array_map('intval', $postIds));
                    try {
                        $s = $pdo->query("SELECT post_id, COUNT(*) as c FROM news_comments WHERE post_id IN ($inIds) GROUP BY post_id");
                        $cmCounts = $s->fetchAll(PDO::FETCH_KEY_PAIR);
                    } catch (Exception $e) {
                    }
                }

                // Batch period visits
                $pvCounts = [];
                if (!empty($shortCodes)) {
                    $placeholders = implode(',', array_fill(0, count($shortCodes), '?'));
                    try {
                        $s = $pdo->prepare("
                            SELECT short_code, COUNT(*) as c 
                            FROM news_tracking 
                            WHERE short_code IN ($placeholders) AND $trackCol BETWEEN ? AND ? 
                            GROUP BY short_code
                        ");
                        $s->execute(array_merge(array_values($shortCodes), [$c_start, $c_end]));
                        $pvCounts = $s->fetchAll(PDO::FETCH_KEY_PAIR);
                    } catch (Exception $e) {
                    }
                }

                foreach ($posts as &$p) {
                    $p['comments_count'] = (int) ($cmCounts[$p['id']] ?? 0);
                    $p['period_visits'] = (int) ($pvCounts[$p['short_code']] ?? 0);
                }
                unset($p);
            }

            echo json_encode([
                'success' => true,
                'posts' => $posts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ]
            ]);
            break;

        // ================= ANALYTICS: ADS & BANNERS TRACKING TABLE =================
        case 'analytics_promo_clicks':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = max(5, min(50, (int) ($_GET['per_page'] ?? 15)));
            $offset = ($page - 1) * $per_page;
            $search = trim($_GET['search'] ?? '');
            $source = trim($_GET['product_id'] ?? ($_GET['ad_source'] ?? ''));

            $range = $_GET['range'] ?? 'last_7_days';
            $cStart = $_GET['start_date'] ?? null;
            $cEnd = $_GET['end_date'] ?? null;
            $dateInfo = resolve_analytics_date_range($range, $cStart, $cEnd);

            $where = ["(user_agent IS NULL OR user_agent NOT LIKE '%Mediapartners-Google%')"];
            $params = [];

            if (!empty($_GET['filter_date_range']) && $_GET['filter_date_range'] === '1') {
                $where[] = 'clicked_at BETWEEN ? AND ?';
                $params[] = $dateInfo['curr_start'];
                $params[] = $dateInfo['curr_end'];
            }

            if (!empty($source)) {
                $where[] = 'product_id = ?';
                $params[] = $source;
            }

            if (!empty($search)) {
                $where[] = '(product_id LIKE ? OR post_id LIKE ? OR page_url LIKE ? OR device_type LIKE ? OR referrer LIKE ? OR ip_hash LIKE ? OR user_agent LIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $whereClause = implode(' AND ', $where);

            $total = 0;
            $totalPages = 1;
            $clicks = [];
            $sourceSummary = [];

            try {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM jb_promo_clicks WHERE $whereClause");
                $countStmt->execute($params);
                $total = (int) $countStmt->fetchColumn();
                $totalPages = max(1, (int) ceil($total / $per_page));

                $sql = "
                    SELECT id, product_id, post_id, page_url, device_type, user_agent, ip_hash, referrer, clicked_at
                    FROM jb_promo_clicks
                    WHERE $whereClause
                    ORDER BY clicked_at DESC, id DESC
                    LIMIT $per_page OFFSET $offset
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $clicks = $stmt->fetchAll();

                $sStmt = $pdo->query(" 
                    SELECT COALESCE(NULLIF(product_id, ''), 'بدون منتج') as source, COUNT(*) as clicks
                    FROM jb_promo_clicks
                    WHERE user_agent IS NULL OR user_agent NOT LIKE '%Mediapartners-Google%'
                    GROUP BY source
                    ORDER BY clicks DESC
                ");
                $sourceSummary = $sStmt->fetchAll();
                $summaryStmt = $pdo->prepare("SELECT COUNT(*) AS total_clicks, COUNT(DISTINCT product_id) AS distinct_sources, COUNT(DISTINCT ip_hash) AS distinct_ips FROM jb_promo_clicks WHERE $whereClause");
                $summaryStmt->execute($params);
                $summary = $summaryStmt->fetch() ?: [];
            } catch (Exception $e) {
                error_log('[analytics_promo_clicks] ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'تعذر تحميل سجل نقرات العروض', 'debug' => $e->getMessage()]);
                break;
            }

            $summary = $summary ?? ['total_clicks' => $total, 'distinct_sources' => 0, 'distinct_ips' => 0];
            $summary['total_clicks'] = (int) ($summary['total_clicks'] ?? 0);
            $summary['distinct_sources'] = (int) ($summary['distinct_sources'] ?? 0);
            $summary['distinct_ips'] = (int) ($summary['distinct_ips'] ?? 0);
            $summary['top_source'] = $sourceSummary[0]['source'] ?? '—';

            echo json_encode([
                'success' => true,
                'clicks' => $clicks,
                'summary' => $summary,
                'source_summary' => $sourceSummary,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ]
            ]);
            break;

        // ================= ANALYTICS: NEWS & VISITS TRACKING TABLE =================
        case 'analytics_news_tracking':
            $trackCol = get_news_tracking_date_col($pdo);
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = max(5, min(50, (int) ($_GET['per_page'] ?? 15)));
            $offset = ($page - 1) * $per_page;
            $search = trim($_GET['search'] ?? '');

            $range = $_GET['range'] ?? 'last_7_days';
            $cStart = $_GET['start_date'] ?? null;
            $cEnd = $_GET['end_date'] ?? null;
            $dateInfo = resolve_analytics_date_range($range, $cStart, $cEnd);

            $where = ["(t.user_agent IS NULL OR t.user_agent NOT LIKE '%Mediapartners-Google%')"];
            $params = [];

            if (!empty($_GET['filter_date_range']) && $_GET['filter_date_range'] === '1') {
                $where[] = "t.$trackCol BETWEEN ? AND ?";
                $params[] = $dateInfo['curr_start'];
                $params[] = $dateInfo['curr_end'];
            }

            if (!empty($search)) {
                $where[] = '(t.short_code LIKE ? OR t.country LIKE ? OR t.city LIKE ? OR t.referer LIKE ? OR p.title LIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            $whereClause = implode(' AND ', $where);

            $total = 0;
            $totalPages = 1;
            $visits = [];

            try {
                $countStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM news_tracking t 
                    LEFT JOIN news_posts p ON p.short_code COLLATE utf8mb4_unicode_ci = t.short_code COLLATE utf8mb4_unicode_ci
                    WHERE $whereClause
                ");
                $countStmt->execute($params);
                $total = (int) $countStmt->fetchColumn();
                $totalPages = max(1, (int) ceil($total / $per_page));

                $sql = "
                    SELECT t.id, t.short_code, t.ip_address, t.referer, t.country, t.city, t.$trackCol AS visited_at,
                           p.id AS post_id, p.title AS post_title
                    FROM news_tracking t
                    LEFT JOIN news_posts p ON p.short_code COLLATE utf8mb4_unicode_ci = t.short_code COLLATE utf8mb4_unicode_ci
                    WHERE $whereClause
                    ORDER BY t.$trackCol DESC, t.id DESC
                    LIMIT $per_page OFFSET $offset
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $visits = $stmt->fetchAll();
            } catch (Exception $e) {
            }

            echo json_encode([
                'success' => true,
                'visits' => $visits,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ]
            ]);
            break;

        // ================= GOOGLE MEDIAPARTNERS CRAWL LOG =================
        case 'analytics_google_crawler':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = 25;
            $offset = ($page - 1) * $per_page;
            $botPattern = '%Mediapartners-Google%';
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM jb_promo_clicks WHERE user_agent LIKE ?");
            $countStmt->execute([$botPattern]);
            $total = (int) $countStmt->fetchColumn();
            $stmt = $pdo->prepare("
                SELECT id, product_id, post_id, page_url, device_type, ip_hash, referrer,
                       user_agent, clicked_at AS visited_at
                FROM jb_promo_clicks
                WHERE user_agent LIKE ?
                ORDER BY clicked_at DESC, id DESC
                LIMIT $per_page OFFSET $offset
            ");
            $stmt->execute([$botPattern]);
            echo json_encode([
                'success' => true,
                'visits' => $stmt->fetchAll(),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => max(1, (int) ceil($total / $per_page)),
                ],
            ]);
            break;

        // ================= ANALYTICS: CATEGORIES TABLE =================
        case 'analytics_categories_table':
            $stmt = $pdo->query("
                SELECT c.id, c.name, c.slug, c.status, c.show_in_header,
                       COUNT(p.id) AS posts_count,
                       COALESCE(SUM(p.views), 0) AS total_views,
                       COALESCE(ROUND(AVG(p.views)), 0) AS avg_views,
                       (
                         SELECT COUNT(cm.id)
                         FROM news_comments cm
                         JOIN news_posts np ON np.id = cm.post_id
                         WHERE np.category_id = c.id
                       ) AS comments_count
                FROM news_categories c
                LEFT JOIN news_posts p ON p.category_id = c.id
                GROUP BY c.id, c.name, c.slug, c.status, c.show_in_header
                ORDER BY total_views DESC, posts_count DESC
            ");
            echo json_encode([
                'success' => true,
                'categories' => $stmt->fetchAll()
            ]);
            break;

        // ================= OVERVIEW STATS =================
        case 'get_stats':
            $total_posts = $pdo->query("SELECT COUNT(*) FROM news_posts WHERE deleted_at IS NULL")->fetchColumn();
            $total_views = $pdo->query("SELECT SUM(views) FROM news_posts WHERE deleted_at IS NULL")->fetchColumn() ?: 0;
            $total_cats = $pdo->query("SELECT COUNT(*) FROM news_categories")->fetchColumn();
            $total_comments = $pdo->query("SELECT COUNT(*) FROM news_comments")->fetchColumn();

            $active_banners = 0;
            try {
                $active_banners = $pdo->query("SELECT COUNT(*) FROM 9s_custom_banners WHERE is_active = 1")->fetchColumn();
            } catch (Exception $e) {
            }

            echo json_encode([
                'success' => true,
                'stats' => [
                    'posts' => (int) $total_posts,
                    'views' => (int) $total_views,
                    'categories' => (int) $total_cats,
                    'comments' => (int) $total_comments,
                    'banners' => (int) $active_banners
                ]
            ]);
            break;

        // ================= POSTS CRUD =================
        case 'list_posts':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = max(1, min(50, (int) ($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $per_page;

            $total = (int) $pdo->query("SELECT COUNT(*) FROM news_posts WHERE deleted_at IS NULL AND (post_type = 'article' OR post_type IS NULL OR post_type = '')")->fetchColumn();
            $total_pages = max(1, (int) ceil($total / $per_page));

            $stmt = $pdo->prepare("
                SELECT p.id, p.title, p.status, p.views, p.created_at, p.image, p.short_code, p.slug, p.post_type,
                    c.name AS category_name
                FROM news_posts p
                LEFT JOIN news_categories c ON c.id = p.category_id
                WHERE p.deleted_at IS NULL AND (p.post_type = 'article' OR p.post_type IS NULL OR p.post_type = '')
                ORDER BY p.id DESC
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'posts' => $stmt->fetchAll(),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $total_pages
                ]
            ]);
            break;

        case 'get_post':
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, title, category_id, content, excerpt, status, short_code, post_id, image, metas, post_type FROM news_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            if ($post) {
                echo json_encode(['success' => true, 'post' => $post]);
            } else {
                echo json_encode(['success' => false, 'message' => 'الخبر غير موجود.']);
            }
            break;

        case 'save_post':
            $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
            $title = trim($_POST['title'] ?? '');
            $category_id = (int) ($_POST['category_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $status = $_POST['status'] ?? 'draft';

            if (empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'العنوان والمحتوى حقول إجبارية.']);
                exit;
            }

            // Image upload handling
            $image = handle_image_upload('image');
            if (!$image && !empty($_POST['image_url'])) {
                $image = handle_image_from_url($_POST['image_url']);
            }

            if ($id) {
                // Update
                $slug = generate_post_slug($title, $pdo, $id);
                if ($image) {
                    $stmt = $pdo->prepare("
                        UPDATE news_posts 
                        SET title = ?, category_id = ?, content = ?, excerpt = ?, status = ?, slug = ?, image = ?, post_type = 'article'
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $category_id, $content, $excerpt, $status, $slug, $image, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE news_posts 
                        SET title = ?, category_id = ?, content = ?, excerpt = ?, status = ?, slug = ?, post_type = 'article'
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $category_id, $content, $excerpt, $status, $slug, $id]);
                }
            } else {
                // Insert: Generate unique post_id and short_code
                $slug = generate_post_slug($title, $pdo);

                // post_id uniqueness check
                do {
                    $post_id = sprintf("%05d", rand(1, 99999));
                    $chk = $pdo->prepare("SELECT id FROM news_posts WHERE post_id = ?");
                    $chk->execute([$post_id]);
                } while ($chk->fetch());

                // short_code uniqueness check
                do {
                    $short_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4);
                    $chk = $pdo->prepare("SELECT id FROM news_posts WHERE short_code = ?");
                    $chk->execute([$short_code]);
                } while ($chk->fetch());

                $stmt = $pdo->prepare("
                    INSERT INTO news_posts (post_id, short_code, auther_id, category_id, title, slug, excerpt, content, metas, image, status, post_type)
                    VALUES (?, ?, 1, ?, ?, ?, ?, ?, '', ?, ?, 'article')
                ");
                $stmt->execute([$post_id, $short_code, $category_id, $title, $slug, $excerpt, $content, $image ?: '', $status]);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ الخبر بنجاح.']);
            break;

        case 'delete_post':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE news_posts SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم نقل الخبر إلى سلة المهملات.']);
            break;

        // ================= SHORT POSTS CRUD (post_type = 'post') =================
        case 'list_short_posts':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = max(1, min(50, (int) ($_GET['per_page'] ?? 15)));
            $offset = ($page - 1) * $per_page;
            $search = trim($_GET['search'] ?? '');
            $filterStatus = trim($_GET['status'] ?? '');

            $where = ["p.deleted_at IS NULL", "p.post_type = 'post'"];
            $params = [];

            if (!empty($search)) {
                $where[] = "(p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ? OR p.slug LIKE ?)";
                $like = '%' . $search . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            if (!empty($filterStatus) && in_array($filterStatus, ['published', 'draft', 'archived'])) {
                $where[] = "p.status = ?";
                $params[] = $filterStatus;
            }

            $whereClause = implode(' AND ', $where);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM news_posts p WHERE $whereClause");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();
            $total_pages = max(1, (int) ceil($total / $per_page));

            $sql = "
                SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.metas, p.status, p.views, p.created_at, p.image, p.short_code, p.post_type,
                       c.name AS category_name
                FROM news_posts p
                LEFT JOIN news_categories c ON c.id = p.category_id
                WHERE $whereClause
                ORDER BY p.id DESC
                LIMIT $per_page OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $posts = $stmt->fetchAll();

            // Decode metas for JSON-friendly payload
            foreach ($posts as &$postItem) {
                if (!empty($postItem['metas'])) {
                    $decoded = json_decode($postItem['metas'], true);
                    $postItem['metas_parsed'] = is_array($decoded) ? $decoded : null;
                } else {
                    $postItem['metas_parsed'] = null;
                }
            }
            unset($postItem);

            echo json_encode([
                'success' => true,
                'posts' => $posts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $total_pages
                ]
            ]);
            break;

        case 'get_short_post':
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT p.id, p.title, p.slug, p.category_id, p.content, p.excerpt, p.metas, p.status, p.short_code, p.post_id, p.image, p.post_type, p.created_at,
                       c.name AS category_name
                FROM news_posts p
                LEFT JOIN news_categories c ON c.id = p.category_id
                WHERE p.id = ? AND p.post_type = 'post' AND p.deleted_at IS NULL
            ");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            if ($post) {
                if (!empty($post['metas'])) {
                    $decoded = json_decode($post['metas'], true);
                    $post['metas_parsed'] = is_array($decoded) ? $decoded : null;
                } else {
                    $post['metas_parsed'] = null;
                }
                echo json_encode(['success' => true, 'post' => $post]);
            } else {
                echo json_encode(['success' => false, 'message' => 'المنشور القصير غير موجود.']);
            }
            break;

        case 'generate_short_post_code':
            $code = function_exists('generate_short_post_code') 
                ? generate_short_post_code($pdo) 
                : substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
            echo json_encode(['success' => true, 'slug' => $code]);
            break;

        case 'save_short_post':
            $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['content'] ?? ($_POST['description'] ?? ''));
            $excerpt = trim($_POST['excerpt'] ?? '');
            $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
            $status = $_POST['status'] ?? 'published';
            if (!in_array($status, ['published', 'draft', 'archived'])) {
                $status = 'published';
            }

            // Description / Content is required (title is optional)
            if (empty($description)) {
                echo json_encode(['success' => false, 'message' => 'محتوى أو وصف المنشور حقل إجباري.']);
                exit;
            }

            // If title is empty, generate an automatic fallback from description
            if (empty($title)) {
                $plainDesc = trim(strip_tags($description));
                $title = mb_substr($plainDesc, 0, 70, 'UTF-8');
                if (mb_strlen($plainDesc, 'UTF-8') > 70) {
                    $title .= '...';
                }
                if (empty($title)) {
                    $title = 'منشور سريع';
                }
            }

            // Excerpt: use provided excerpt, or first 250 chars of description
            if (empty($excerpt)) {
                $excerpt = mb_substr(strip_tags($description), 0, 250, 'UTF-8');
            }

            // Handle Slug (code like "AjNz6hWej0")
            $slug = trim($_POST['slug'] ?? '');
            if (empty($slug)) {
                $slug = function_exists('generate_short_post_code') ? generate_short_post_code($pdo, $id) : substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
            } else {
                // Clean slug: alphanumeric + dash + underscore
                $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug);
                if (empty($slug)) {
                    $slug = function_exists('generate_short_post_code') ? generate_short_post_code($pdo, $id) : substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
                }
                // Check uniqueness
                $slugChk = $pdo->prepare("SELECT id FROM news_posts WHERE slug = ?" . ($id ? " AND id != ?" : "") . " LIMIT 1");
                $slugChk->execute($id ? [$slug, $id] : [$slug]);
                if ($slugChk->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'رمز الرابط (Slug) مستخدم بالفعل، المرجو استخدام كود آخر.']);
                    exit;
                }
            }

            // Image handling (upload or external url)
            $image = handle_image_upload('image');
            $imageUrl = trim($_POST['image_url'] ?? '');

            // If an external URL is provided and no file was uploaded, download it to our uploads folder
            if (!$image && !empty($imageUrl)) {
                $downloaded = handle_image_from_url($imageUrl);
                if ($downloaded) {
                    $image = $downloaded;
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'تعذر تحميل وحفظ الصورة من الرابط المدخل. يرجى التأكد من أن الرابط مباشر لصورة صالحة أو رفعها من الجهاز.'
                    ]);
                    exit;
                }
            }

            // Meta tags for Google SEO
            $metaTitle = trim($_POST['meta_title'] ?? '');
            if (empty($metaTitle)) {
                $metaTitle = $title . ' | أخبار المغرب';
            }

            $metaDesc = trim($_POST['meta_description'] ?? '');
            if (empty($metaDesc)) {
                $metaDesc = mb_substr(strip_tags($description), 0, 155, 'UTF-8');
            }

            $metaKeywords = trim($_POST['meta_keywords'] ?? '');

            $ogImage = $image ? IMAGE_BASE_URL . $image : FALLBACK_IMAGE;

            $siteUrl = rtrim(get_setting('site_url', 'https://9sty.com'), '/');
            $metasArray = [
                'meta_title'       => $metaTitle,
                'meta_description' => $metaDesc,
                'keywords'         => $metaKeywords,
                'canonical'        => $siteUrl . '/' . $slug,
                'og_title'         => $metaTitle,
                'og_description'   => $metaDesc,
                'og_image'         => $ogImage,
                'robots'           => 'index, follow',
                'google_bot'       => 'index, follow, max-snippet:-1, max-image-preview:large',
                'schema_type'      => 'NewsArticle'
            ];
            $metasJson = json_encode($metasArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($id) {
                // Update
                if ($image) {
                    $stmt = $pdo->prepare("
                        UPDATE news_posts
                        SET title = ?, slug = ?, category_id = ?, content = ?, excerpt = ?, status = ?, metas = ?, image = ?, post_type = 'post'
                        WHERE id = ? AND post_type = 'post'
                    ");
                    $stmt->execute([$title, $slug, $category_id, $description, $excerpt, $status, $metasJson, $image, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE news_posts
                        SET title = ?, slug = ?, category_id = ?, content = ?, excerpt = ?, status = ?, metas = ?, post_type = 'post'
                        WHERE id = ? AND post_type = 'post'
                    ");
                    $stmt->execute([$title, $slug, $category_id, $description, $excerpt, $status, $metasJson, $id]);
                }
            } else {
                // Insert: Generate unique post_id and short_code
                do {
                    $post_id = sprintf("%05d", rand(1, 99999));
                    $chk = $pdo->prepare("SELECT id FROM news_posts WHERE post_id = ?");
                    $chk->execute([$post_id]);
                } while ($chk->fetch());

                do {
                    $short_code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4);
                    $chk = $pdo->prepare("SELECT id FROM news_posts WHERE short_code = ?");
                    $chk->execute([$short_code]);
                } while ($chk->fetch());

                $chosenImage = $image ?: '';

                $stmt = $pdo->prepare("
                    INSERT INTO news_posts (post_id, short_code, auther_id, category_id, title, slug, excerpt, content, metas, image, status, post_type)
                    VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, 'post')
                ");
                $stmt->execute([$post_id, $short_code, $category_id, $title, $slug, $excerpt, $description, $metasJson, $chosenImage, $status]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'تم حفظ المنشور القصير بنجاح.',
                'slug' => $slug
            ]);
            break;

        case 'delete_short_post':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE news_posts SET deleted_at = NOW() WHERE id = ? AND post_type = 'post' AND deleted_at IS NULL");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم نقل المنشور إلى سلة المهملات.']);
            break;

        // ================= CATEGORIES CRUD =================
        case 'list_categories':
            $stmt = $pdo->query("SELECT id, name, slug, status, show_in_header, meta_title, meta_description FROM news_categories ORDER BY id DESC");
            echo json_encode(['success' => true, 'categories' => $stmt->fetchAll()]);
            break;

        case 'save_category':
            $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
            $name = trim($_POST['name'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $show_in_header = isset($_POST['show_in_header']) ? (int) $_POST['show_in_header'] : 1;
            $meta_title = trim($_POST['meta_title'] ?? '');
            $meta_description = trim($_POST['meta_description'] ?? '');

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'اسم القسم حقل إجباري.']);
                exit;
            }

            if ($id) {
                $slug = generate_cat_slug($name, $pdo, $id);
                $stmt = $pdo->prepare("
                    UPDATE news_categories 
                    SET name = ?, slug = ?, status = ?, show_in_header = ?, meta_title = ?, meta_description = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $slug, $status, $show_in_header, $meta_title, $meta_description, $id]);
            } else {
                $slug = generate_cat_slug($name, $pdo);
                $stmt = $pdo->prepare("
                    INSERT INTO news_categories (name, slug, status, show_in_header, at_home, meta_title, meta_description) 
                    VALUES (?, ?, ?, ?, 0, ?, ?)
                ");
                $stmt->execute([$name, $slug, $status, $show_in_header, $meta_title, $meta_description]);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ القسم بنجاح.']);
            break;

        case 'delete_category':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM news_categories WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف القسم بنجاح.']);
            break;

        // ================= ADSENSE CONFIG =================
        case 'list_adsense':
            $stmt = $pdo->query("SELECT id, slot_name, ad_client, ad_slot, ad_format, code, is_active FROM 9s_adsense ORDER BY id ASC");
            echo json_encode(['success' => true, 'adsense' => $stmt->fetchAll()]);
            break;

        case 'save_adsense':
            $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
            $slot_name = trim($_POST['slot_name'] ?? '');
            $ad_client = trim($_POST['ad_client'] ?? '');
            $ad_slot = trim($_POST['ad_slot'] ?? '');
            $ad_format = trim($_POST['ad_format'] ?? 'auto');
            $code = trim($_POST['code'] ?? '');
            $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

            if (empty($slot_name)) {
                echo json_encode(['success' => false, 'message' => 'اسم المساحة الإعلانية حقل إجباري.']);
                exit;
            }

            // If custom code is empty, client/slot are required
            if (empty($code) && (empty($ad_client) || empty($ad_slot))) {
                echo json_encode(['success' => false, 'message' => 'يجب إدخال كود الإعلان المخصص أو ملء معرفات جوجل أدسنس (Client & Slot).']);
                exit;
            }

            // Uniqueness check for slot_name if new
            if (!$id) {
                $chk = $pdo->prepare("SELECT id FROM 9s_adsense WHERE slot_name = ?");
                $chk->execute([$slot_name]);
                if ($chk->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'اسم المساحة الإعلانية مستخدم بالفعل!']);
                    exit;
                }
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE 9s_adsense 
                    SET slot_name = ?, ad_client = ?, ad_slot = ?, ad_format = ?, code = ?, is_active = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$slot_name, $ad_client, $ad_slot, $ad_format, $code ?: null, $is_active, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO 9s_adsense (slot_name, ad_client, ad_slot, ad_format, code, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$slot_name, $ad_client, $ad_slot, $ad_format, $code ?: null, $is_active]);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ إعدادات الإعلان بنجاح.']);
            break;

        case 'delete_adsense':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM 9s_adsense WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف المساحة الإعلانية بنجاح.']);
            break;

        // ================= CUSTOM BANNERS CRUD =================
 case 'list_banners':
    try {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $total = (int)$pdo->query("SELECT COUNT(*) FROM 9s_custom_banners WHERE deleted_at IS NULL")->fetchColumn();
        $total_pages = max(1, (int)ceil($total / $per_page));

        $botFilter = "(user_agent IS NULL OR user_agent NOT LIKE '%Mediapartners-Google%')";

        $stmt = $pdo->prepare("
            SELECT b.id, b.slug, b.name, b.image, b.url, b.price, b.promo_price, b.is_active, b.created_at,
                (SELECT COUNT(*) FROM jb_promo_clicks c WHERE c.product_id = b.slug AND $botFilter) AS total_clicks,
                (SELECT COUNT(*) FROM jb_promo_clicks c WHERE c.product_id = b.slug AND $botFilter AND c.clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS clicks_7d,
                (SELECT COUNT(*) FROM jb_promo_clicks c WHERE c.product_id = b.slug AND $botFilter AND c.clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS clicks_30d,
                (SELECT COUNT(DISTINCT ip_hash) FROM jb_promo_clicks c WHERE c.product_id = b.slug AND $botFilter) AS unique_ips,
                (SELECT MAX(clicked_at) FROM jb_promo_clicks c WHERE c.product_id = b.slug AND $botFilter) AS last_click
            FROM 9s_custom_banners b
            WHERE b.deleted_at IS NULL
            ORDER BY total_clicks DESC, b.id DESC
            LIMIT :lim OFFSET :off
        ");
        $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $banners = $stmt->fetchAll();

        // إجمالي النقرات + إحصائيات عامة
        $stats = ['total_clicks' => 0, 'total_unique_ips' => 0, 'top_banner' => '—'];
        try {
            $s = $pdo->query("
                SELECT COUNT(*) AS total_clicks,
                       COUNT(DISTINCT ip_hash) AS unique_ips
                FROM jb_promo_clicks WHERE $botFilter
            ")->fetch();
            $stats['total_clicks'] = (int)($s['total_clicks'] ?? 0);
            $stats['total_unique_ips'] = (int)($s['unique_ips'] ?? 0);
            $top = $pdo->query("
                SELECT COALESCE(NULLIF(product_id, ''), 'بدون معرّف') AS src, COUNT(*) AS c
                FROM jb_promo_clicks WHERE $botFilter AND product_id IS NOT NULL AND product_id != ''
                GROUP BY src ORDER BY c DESC LIMIT 1
            ")->fetch();
            if ($top) $stats['top_banner'] = $top['src'];
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'banners' => $banners,
            'stats' => $stats,
            'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => $total_pages],
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحميل البنرات: ' . $e->getMessage()]);
    }
    break;

        case 'banner_clicks':
            // تفاصيل نقرات بنر محدد
            $slug = trim($_GET['slug'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = 20;
            $offset = ($page - 1) * $per_page;
            if ($slug === '') { echo json_encode(['success' => false, 'message' => 'slug مفقود']); break; }

            $botFilter = "(user_agent IS NULL OR user_agent NOT LIKE '%Mediapartners-Google%')";
            try {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM jb_promo_clicks WHERE product_id = ? AND $botFilter");
                $cnt->execute([$slug]);
                $total = (int)$cnt->fetchColumn();

                $stmt = $pdo->prepare("
                    SELECT id, page_url, device_type, ip_hash, referrer, clicked_at, post_id, user_agent
                    FROM jb_promo_clicks
                    WHERE product_id = ? AND $botFilter
                    ORDER BY clicked_at DESC LIMIT $per_page OFFSET $offset
                ");
                $stmt->execute([$slug]);
                $clicks = $stmt->fetchAll();

                // إحصائيات
                $s = $pdo->prepare("
                    SELECT COUNT(DISTINCT ip_hash) AS ips,
                        COUNT(DISTINCT DATE(clicked_at)) AS days
                    FROM jb_promo_clicks WHERE product_id = ? AND $botFilter
                ");
                $s->execute([$slug]);
                $summary = $s->fetch() ?: [];

                echo json_encode([
                    'success' => true,
                    'clicks' => $clicks,
                    'summary' => [
                        'total' => $total,
                        'unique_ips' => (int)($summary['ips'] ?? 0),
                        'active_days' => (int)($summary['days'] ?? 0),
                    ],
                    'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => max(1, (int)ceil($total / $per_page))],
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'save_banner':
            $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
            $name = trim($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $price = trim($_POST['price'] ?? '');
            $promo_price = trim($_POST['promo_price'] ?? '');
            $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

            if (empty($name) || empty($url)) {
                echo json_encode(['success' => false, 'message' => 'اسم البنر والرابط حقول إجبارية.']);
                exit;
            }

            // Image handling (can upload file or use text input URL)
            $image = handle_image_upload('image');
            if (!$image && !empty($_POST['image_url'])) {
                $downloaded = handle_image_from_url(trim($_POST['image_url']));
                $image = $downloaded ?: trim($_POST['image_url']);
            }

            // If image is still empty and it's a new banner, return error
            if (!$id && !$image) {
                echo json_encode(['success' => false, 'message' => 'يجب رفع صورة للبنر أو وضع رابط صورة خارجي.']);
                exit;
            }

            if ($id) {
                if ($image) {
                    $stmt = $pdo->prepare("
                        UPDATE 9s_custom_banners 
                        SET name = ?, image = ?, url = ?, price = ?, promo_price = ?, is_active = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $image, $url, $price ?: null, $promo_price ?: null, $is_active, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE 9s_custom_banners 
                        SET name = ?, url = ?, price = ?, promo_price = ?, is_active = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $url, $price ?: null, $promo_price ?: null, $is_active, $id]);
                }
            } else {
                $slug = generate_banner_slug($pdo);
                $stmt = $pdo->prepare("
                    INSERT INTO 9s_custom_banners (slug, name, image, url, price, promo_price, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$slug, $name, $image, $url, $price ?: null, $promo_price ?: null, $is_active]);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ البنر بنجاح.']);
            break;

        case 'toggle_banner':
            $id = (int) ($_POST['id'] ?? 0);
            $is_active = (int) ($_POST['is_active'] ?? 0);
            $stmt = $pdo->prepare("UPDATE 9s_custom_banners SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $id]);
            echo json_encode(['success' => true, 'message' => 'تم تعديل حالة النشاط للبنر.']);
            break;

        case 'delete_banner':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE 9s_custom_banners SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم نقل البنر إلى سلة المهملات.']);
            break;

        // ================= COMMENTS MODERATION =================
        case 'list_comments':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = max(1, min(50, (int) ($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $per_page;

            $total = (int) $pdo->query("SELECT COUNT(*) FROM news_comments")->fetchColumn();
            $total_pages = max(1, (int) ceil($total / $per_page));

            $stmt = $pdo->prepare("
                SELECT c.id, c.name, c.email, c.comment,
                       COALESCE(c.status, 'approved') AS status,
                       c.created_at, c.post_id, p.title AS post_title
                FROM news_comments c
                LEFT JOIN news_posts p ON p.id = c.post_id
                WHERE c.deleted_at IS NULL
                ORDER BY c.id DESC
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'comments' => $stmt->fetchAll(),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $total_pages
                ]
            ]);
            break;

        case 'get_comment':
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name, email, comment, COALESCE(status, 'approved') AS status FROM news_comments WHERE id = ?");
            $stmt->execute([$id]);
            $comment = $stmt->fetch();
            if ($comment) {
                echo json_encode(['success' => true, 'comment' => $comment]);
            } else {
                echo json_encode(['success' => false, 'message' => 'التعليق غير موجود.']);
            }
            break;

        case 'save_comment':
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $comment = trim($_POST['comment'] ?? '');
            $status = $_POST['status'] ?? 'approved';

            if (!$id || empty($name) || empty($comment)) {
                echo json_encode(['success' => false, 'message' => 'الاسم والتعليق حقول إجبارية.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE news_comments SET name = ?, comment = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $comment, $status, $id]);
            echo json_encode(['success' => true, 'message' => 'تم حفظ تعديل التعليق بنجاح.']);
            break;

        case 'toggle_hide_comment':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT status FROM news_comments WHERE id = ?");
            $stmt->execute([$id]);
            $curr = $stmt->fetchColumn() ?: 'approved';
            $newStatus = ($curr === 'hidden') ? 'approved' : 'hidden';

            $upd = $pdo->prepare("UPDATE news_comments SET status = ? WHERE id = ?");
            $upd->execute([$newStatus, $id]);
            $msg = ($newStatus === 'hidden') ? 'تم إخفاء التعليق من المقال.' : 'تم إظهار التعليق في المقال.';
            echo json_encode(['success' => true, 'message' => $msg, 'new_status' => $newStatus]);
            break;

        case 'delete_comment':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE news_comments SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم نقل التعليق إلى سلة المهملات.']);
            break;

        // ================= USERS MANAGEMENT =================
        case 'list_users':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = 50;
            $offset = ($page - 1) * $per_page;

            $total = (int) $pdo->query("SELECT COUNT(*) FROM 9s_users")->fetchColumn();
            $total_pages = max(1, (int) ceil($total / $per_page));

            $stmt = $pdo->prepare("
                SELECT id, username, email, role, created_at
                FROM 9s_users ORDER BY id DESC
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'users' => $stmt->fetchAll(),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $total_pages
                ]
            ]);
            break;

        case 'save_user':
            $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = (int) ($_POST['role'] ?? 0);
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'اسم المستخدم والبريد الإلكتروني حقول إجبارية.']);
                exit;
            }

            if ($id) {
                // Check uniqueness for email/username excluding current ID
                $chk = $pdo->prepare("SELECT id FROM 9s_users WHERE (username = ? OR email = ?) AND id != ?");
                $chk->execute([$username, $email, $id]);
                if ($chk->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل!']);
                    exit;
                }

                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE 9s_users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $hash, $role, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE 9s_users SET username = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role, $id]);
                }
            } else {
                if (empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'كلمة المرور حقل إجباري للحساب الجديد.']);
                    exit;
                }

                // Check uniqueness for email/username
                $chk = $pdo->prepare("SELECT id FROM 9s_users WHERE username = ? OR email = ?");
                $chk->execute([$username, $email]);
                if ($chk->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل!']);
                    exit;
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO 9s_users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hash, $role]);
            }
            echo json_encode(['success' => true, 'message' => 'تم حفظ حساب المستخدم بنجاح.']);
            break;

        case 'delete_user':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) ($_SESSION['admin_user_id'] ?? 0)) {
                echo json_encode(['success' => false, 'message' => 'لا يمكنك حذف حسابك الحالي أثناء تسجيل الدخول!']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM 9s_users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف حساب المستخدم بنجاح.']);
            break;

        // ================= SETTINGS / TRACKING / SOCIAL =================
        case 'get_settings':
            $allSettings = get_all_settings();
            echo json_encode([
                'success' => true,
                'settings' => [
                    'google_analytics_id' => $allSettings['google_analytics_id'] ?? '',
                    'facebook_pixel_id' => $allSettings['facebook_pixel_id'] ?? '',
                    'social_facebook' => $allSettings['social_facebook'] ?? '',
                    'social_twitter' => $allSettings['social_twitter'] ?? '',
                    'social_instagram' => $allSettings['social_instagram'] ?? '',
                    'social_youtube' => $allSettings['social_youtube'] ?? '',
                    'social_telegram' => $allSettings['social_telegram'] ?? '',
                    'social_whatsapp' => $allSettings['social_whatsapp'] ?? '',
                    'site_url' => $allSettings['site_url'] ?? '',
                    'site_title' => $allSettings['site_title'] ?? '',
                    'site_description' => $allSettings['site_description'] ?? '',
                    'default_seo_image' => $allSettings['default_seo_image'] ?? '',
                    'site_logo' => $allSettings['site_logo'] ?? '',
                    'janabio_api_url' => $allSettings['janabio_api_url'] ?? '',
                    'janabio_api_key' => $allSettings['janabio_api_key'] ?? '',
                    'janabio_api_secret' => $allSettings['janabio_api_secret'] ?? '',
                ]
            ]);
            break;

        case 'save_settings':
            $keys = [
                'google_analytics_id',
                'facebook_pixel_id',
                'social_facebook',
                'social_twitter',
                'social_instagram',
                'social_youtube',
                'social_telegram',
                'social_whatsapp',
                'site_url',
                'site_title',
                'site_description',
                'default_seo_image',
                'site_logo',
                'janabio_api_url',
                'janabio_api_key',
                'janabio_api_secret'
            ];
            foreach ($keys as $k) {
                if (isset($_POST[$k])) {
                    save_setting($k, trim($_POST[$k]));
                }
            }

            // Handle file upload for site logo
            if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
                try {
                    $logo_filename = handle_image_upload('site_logo_file');
                    if ($logo_filename) {
                        save_setting('site_logo', IMAGE_BASE_URL . $logo_filename);
                    }
                } catch (Exception $ex) {
                    echo json_encode(['success' => false, 'message' => 'خطأ في رفع الشعار: ' . $ex->getMessage()]);
                    exit;
                }
            }

            // Handle file upload for default SEO image
            if (isset($_FILES['default_seo_image_file']) && $_FILES['default_seo_image_file']['error'] === UPLOAD_ERR_OK) {
                try {
                    $seo_filename = handle_image_upload('default_seo_image_file');
                    if ($seo_filename) {
                        save_setting('default_seo_image', IMAGE_BASE_URL . $seo_filename);
                    }
                } catch (Exception $ex) {
                    echo json_encode(['success' => false, 'message' => 'خطأ في رفع صورة المشاركة الافتراضية: ' . $ex->getMessage()]);
                    exit;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'تم حفظ الإعدادات بنجاح!'
            ]);
            break;

        // ================= SHORT LINKS CRUD =================
        case 'list_short_links':
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per_page = max(1, min(50, (int) ($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $per_page;

            $total = (int) $pdo->query("SELECT COUNT(*) FROM short_links")->fetchColumn();
            $total_pages = max(1, (int) ceil($total / $per_page));

            $stmt = $pdo->prepare("
                    SELECT id, short_code, destination_url, clicks, created_at
                    FROM short_links
                    WHERE deleted_at IS NULL
                    ORDER BY id DESC
                    LIMIT :lim OFFSET :off
                ");
            $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'short_links' => $stmt->fetchAll(),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $total_pages
                ]
            ]);
            break;
        case 'save_short_link':
            $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
            $destination_url = trim($_POST['destination_url'] ?? '');
            $short_code = trim($_POST['short_code'] ?? '');

            if (empty($destination_url)) {
                echo json_encode(['success' => false, 'message' => 'الرابط المستهدف حقل إجباري.']);
                exit;
            }

            if (empty($short_code)) {
                // Generate a unique 5-character alphanumeric short code
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                do {
                    $short_code = '';
                    for ($i = 0; $i < 5; $i++) {
                        $short_code .= $chars[rand(0, strlen($chars) - 1)];
                    }

                    // check in news_posts
                    $stmt = $pdo->prepare("SELECT id FROM news_posts WHERE short_code = ? LIMIT 1");
                    $stmt->execute([$short_code]);
                    $existsInPosts = $stmt->fetch();

                    // check in short_links
                    $stmt = $pdo->prepare("SELECT id FROM short_links WHERE short_code = ? AND (? IS NULL OR id != ?) LIMIT 1");
                    $stmt->execute([$short_code, $id, $id]);
                    $existsInShortLinks = $stmt->fetch();
                } while ($existsInPosts || $existsInShortLinks);
            } else {
                // Validate if user-entered code is already taken
                $stmt = $pdo->prepare("SELECT id FROM news_posts WHERE short_code = ? LIMIT 1");
                $stmt->execute([$short_code]);
                $existsInPosts = $stmt->fetch();

                $stmt = $pdo->prepare("SELECT id FROM short_links WHERE short_code = ? AND (? IS NULL OR id != ?) LIMIT 1");
                $stmt->execute([$short_code, $id, $id]);
                $existsInShortLinks = $stmt->fetch();

                if ($existsInPosts || $existsInShortLinks) {
                    echo json_encode(['success' => false, 'message' => 'هذا الكود المختصر مستخدم بالفعل، يرجى اختيار كود آخر.']);
                    exit;
                }
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE short_links SET short_code = ?, destination_url = ? WHERE id = ?");
                $stmt->execute([$short_code, $destination_url, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO short_links (short_code, destination_url, clicks) VALUES (?, ?, 0)");
                $stmt->execute([$short_code, $destination_url]);
            }

            echo json_encode(['success' => true, 'message' => 'تم حفظ الرابط المختصر بنجاح.']);
            break;

        case 'delete_short_link':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE short_links SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم نقل الرابط إلى سلة المهملات.']);
            break;
        // ================= COLOR SETTINGS =================
        case 'get_color_settings':
            echo json_encode([
                'success' => true,
                'colors' => ja_get_theme_colors(),
                'defaults' => ja_get_theme_defaults(),
            ]);
            break;

    case 'save_color_settings':
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        if (!is_array($input)) { echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']); break; }

        $allowed = array_keys(ja_get_theme_defaults());
        $saved = 0;
        $invalid = [];
        foreach ($allowed as $k) {
            if (!isset($input[$k])) continue;
            $val = trim((string)$input[$k]);
            // validate: hex or rgba() or named color
            if (!preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]{1,60}\)|[a-zA-Z]{2,25})$/', $val)) {
                $invalid[] = $k;
                continue;
            }
            if (ja_save_setting($k, $val)) $saved++;
        }
        echo json_encode([
            'success' => true,
            'message' => 'تم حفظ ' . $saved . ' لون',
            'invalid' => $invalid,
            'colors' => ja_get_theme_colors(),
        ]);
        break;

    case 'reset_color_settings':
        $defaults = ja_get_theme_defaults();
        foreach ($defaults as $k => $v) ja_save_setting($k, $v);
        echo json_encode([
            'success' => true,
            'message' => 'تم استرجاع الألوان الافتراضية',
            'colors' => $defaults,
        ]);
        break;
        // ================= TRASH MANAGEMENT =================

case 'list_trash':
    $type = $_GET['type'] ?? 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 30;
    $offset = ($page - 1) * $per;
    $search = trim($_GET['search'] ?? '');

    $all = [];

    // Posts / Short posts
    if (in_array($type, ['all', 'post', 'short_post'], true)) {
        try {
            $where = ["deleted_at IS NOT NULL"];
            $params = [];
            if ($type === 'post')        $where[] = "(post_type = 'article' OR post_type IS NULL OR post_type = '')";
            if ($type === 'short_post')  $where[] = "post_type = 'post'";
            if ($search) { $where[] = "title LIKE ?"; $params[] = "%$search%"; }
            $w = implode(' AND ', $where);
            $s = $pdo->prepare("SELECT id, title AS name, slug, post_type, created_at, deleted_at FROM news_posts WHERE $w ORDER BY deleted_at DESC LIMIT 500");
            $s->execute($params);
            foreach ($s->fetchAll() as $row) {
                $row['item_type'] = ($row['post_type'] === 'post') ? 'short_post' : 'post';
                $row['type_label'] = ($row['item_type'] === 'post') ? 'مقال' : 'منشور قصير';
                $all[] = $row;
            }
        } catch (Exception $e) {}
    }

    // Comments
    if (in_array($type, ['all', 'comment'], true)) {
        try {
            $where = ["deleted_at IS NOT NULL"];
            $params = [];
            if ($search) { $where[] = "(name LIKE ? OR comment LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
            $w = implode(' AND ', $where);
            $s = $pdo->prepare("SELECT id, CONCAT(name, ' — ', SUBSTRING(comment, 1, 80)) AS name, email, post_id, created_at, deleted_at FROM news_comments WHERE $w ORDER BY deleted_at DESC LIMIT 500");
            $s->execute($params);
            foreach ($s->fetchAll() as $row) {
                $row['item_type'] = 'comment';
                $row['type_label'] = 'تعليق';
                $all[] = $row;
            }
        } catch (Exception $e) {}
    }

    // Short links
    if (in_array($type, ['all', 'short_link'], true)) {
        try {
            $where = ["deleted_at IS NOT NULL"];
            $params = [];
            if ($search) { $where[] = "(short_code LIKE ? OR destination_url LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
            $w = implode(' AND ', $where);
            $s = $pdo->prepare("SELECT id, short_code AS name, destination_url, clicks, created_at, deleted_at FROM short_links WHERE $w ORDER BY deleted_at DESC LIMIT 500");
            $s->execute($params);
            foreach ($s->fetchAll() as $row) {
                $row['item_type'] = 'short_link';
                $row['type_label'] = 'رابط مختصر';
                $all[] = $row;
            }
        } catch (Exception $e) {}
    }

    // Banners
    if (in_array($type, ['all', 'banner'], true)) {
        try {
            $where = ["deleted_at IS NOT NULL"];
            $params = [];
            if ($search) { $where[] = "name LIKE ?"; $params[] = "%$search%"; }
            $w = implode(' AND ', $where);
            $s = $pdo->prepare("SELECT id, name, image, url, created_at, deleted_at FROM 9s_custom_banners WHERE $w ORDER BY deleted_at DESC LIMIT 500");
            $s->execute($params);
            foreach ($s->fetchAll() as $row) {
                $row['item_type'] = 'banner';
                $row['type_label'] = 'بنر';
                $all[] = $row;
            }
        } catch (Exception $e) {}
    }

    usort($all, fn($a, $b) => strcmp($b['deleted_at'] ?? '', $a['deleted_at'] ?? ''));

    $counts = ['all' => count($all), 'post' => 0, 'short_post' => 0, 'comment' => 0, 'short_link' => 0, 'banner' => 0];
    foreach ($all as $i) $counts[$i['item_type']] = ($counts[$i['item_type']] ?? 0) + 1;

    $items = array_slice($all, $offset, $per);

    echo json_encode([
        'success' => true,
        'items' => $items,
        'counts' => $counts,
        'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $counts['all'], 'total_pages' => max(1, (int)ceil($counts['all'] / $per))],
    ]);
    break;

case 'restore_trash':
    $itemType = $_POST['item_type'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $map = ['post' => 'news_posts', 'short_post' => 'news_posts', 'comment' => 'news_comments', 'short_link' => 'short_links', 'banner' => '9s_custom_banners'];
    if (!isset($map[$itemType]) || !$id) { echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']); break; }
    try {
        $pdo->prepare("UPDATE `{$map[$itemType]}` SET deleted_at = NULL WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'تمت استعادة العنصر']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    break;

case 'delete_forever':
    $itemType = $_POST['item_type'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $map = ['post' => 'news_posts', 'short_post' => 'news_posts', 'comment' => 'news_comments', 'short_link' => 'short_links', 'banner' => '9s_custom_banners'];
    if (!isset($map[$itemType]) || !$id) { echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']); break; }
    try {
        // لو كان بنر → حذف الصورة من السيرفر اختياري
        if ($itemType === 'banner') {
            $st = $pdo->prepare("SELECT image FROM 9s_custom_banners WHERE id = ?");
            $st->execute([$id]);
            $img = $st->fetchColumn();
            if ($img && strpos($img, 'http') !== 0) {
                $file = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . basename($img);
                if (is_file($file)) @unlink($file);
            }
        }
        $pdo->prepare("DELETE FROM `{$map[$itemType]}` WHERE id = ? AND deleted_at IS NOT NULL")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'تم الحذف النهائي']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    break;

case 'empty_trash':
    $deleted = 0;
    foreach (['news_comments', 'short_links', '9s_custom_banners'] as $tbl) {
        try { $deleted += (int)$pdo->exec("DELETE FROM `$tbl` WHERE deleted_at IS NOT NULL"); } catch (Exception $e) {}
    }
    try { $deleted += (int)$pdo->exec("DELETE FROM news_posts WHERE deleted_at IS NOT NULL"); } catch (Exception $e) {}
    echo json_encode(['success' => true, 'message' => "تم تفريغ السلة ($deleted عنصر)"]);
    break;
        default:
            echo json_encode(['success' => false, 'message' => 'الطلب غير معروف.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()]);
}

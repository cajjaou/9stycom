<?php
/**
 * go/promo.php — Endpoint dyal tracking + redirect l banners janabio.
 *
 * Usage f href: /go/promo.php?pid=106&post_id=648&page_url=...
 *   pid       = id dyal produit (obligatoire)
 *   post_id   = id dyal l'article mnin ja click (optionnel - null ila
 *               click ja mn feed/sidebar machi mn dakhel article)
 *   page_url  = l page l7a9i9iya fin wa9e3 click (bach mankounch
 *               muhtajin l referrer ghi)
 *
 * Kaydir: 1) yssajel click f jb_promo_clicks (m3a device, referrer,
 * ip hash), 2) ydir redirect (302) l URL l7a9i9i dyal produit b UTM.
 */
require __DIR__ . '/../db.php';
require __DIR__ . '/../janabio-products.php';

// --- Decryption/Decoding logic ---
$hash = $_GET['hash'] ?? '';
$productId = null;
$postId    = null;
$pageUrl   = null;

if (!empty($hash)) {
    // Decode URL-safe Base64
    $base64 = str_replace(['-', '_'], ['+', '/'], $hash);
    $pad = strlen($base64) % 4;
    if ($pad) {
        $base64 .= str_repeat('=', 4 - $pad);
    }
    $decodedStr = base64_decode($base64);
    if ($decodedStr !== false) {
        $data = json_decode($decodedStr, true);
        if (is_array($data)) {
            $productId = $data['p'] ?? null;
            $postId    = !empty($data['art']) ? (int)$data['art'] : null;
            $pageUrl   = !empty($data['u']) ? substr($data['u'], 0, 500) : null;
        }
    }
}

// Fallback to normal query parameters (for backwards compatibility)
if (!$productId) {
    $productId = $_GET['pid'] ?? null;
    $postId    = isset($_GET['post_id']) && $_GET['post_id'] !== '' ? (int)$_GET['post_id'] : null;
}

if (!$productId) {
    http_response_code(400);
    die('Missing product id.');
}

// --- 1) L9a l produit (bach n3rfo l url l7a9i9i dyalo, w n verifiw b7al kayn) ---
$products = get_janabio_products(false); // false: khali même les rupture stock ytsajel click, ghir matrediyouch
$product = null;
foreach ($products as $p) {
    if ((string)$p['id'] === (string)$productId) {
        $product = $p;
        break;
    }
}

// Backwards compatibility for legacy cb_<id> custom banner links
if (!$product && preg_match('/^cb_(\d+)$/', (string)$productId, $m)) {
    try {
        $pdo = get_db();
        $bStmt = $pdo->prepare("SELECT id, slug, url FROM 9s_custom_banners WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $bStmt->execute([(int)$m[1]]);
        $banner = $bStmt->fetch();
        if ($banner && !empty($banner['url'])) {
            if (!empty($banner['slug'])) {
                $productId = $banner['slug'];
            }
            $product = ['id' => $productId, 'url' => $banner['url']];
        }
    } catch (Exception $e) {
    }
}

if (!$product) {
    http_response_code(404);
    die('Product not found.');
}

// --- 2) Chmen jihaz (mobile / tablet / desktop) mn User-Agent ---
function detect_device(string $ua): string
{
    $ua = strtolower($ua);
    if (preg_match('/ipad|tablet|kindle|playbook/', $ua)) {
        return 'tablet';
    }
    if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/', $ua)) {
        return 'mobile';
    }
    return 'desktop';
}

$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$deviceType = detect_device($userAgent);
$referrer = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500);
if ($pageUrl === null) {
    $pageUrl = substr($_GET['page_url'] ?? $referrer, 0, 500);
}

// IP machaya bach n3rfo click ma ykrarch bzaf (bots, fraud) - mkhzna
// b hash machi f wodo7 (privacy)
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = trim(explode(',', $ip)[0]);
$ipHash = hash('sha256', $ip . 'jb_promo_salt_9sty'); // bdel salt b chi 7aja khassa bik

// --- 3) Ssajel click f database ---
try {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        INSERT INTO jb_promo_clicks
            (product_id, post_id, page_url, device_type, user_agent, ip_hash, referrer)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$productId, $postId, $pageUrl, $deviceType, $userAgent, $ipHash, $referrer]);
} catch (Exception $e) {
    // Ila l insert fcha, matw9afch l zaer - khaleh ymchi l produit b7al ma khass,
    // ghir sjjel l error f log dyal PHP
    error_log('jb_promo_clicks insert failed: ' . $e->getMessage());
}

// --- 4) Redirect l URL l7a9i9i dyal produit, b UTM tracking ---
$campaign = $postId ? 'post_' . $postId : 'feed';
$targetUrl = $product['url'] . (str_contains($product['url'], '?') ? '&' : '?')
    . http_build_query([
        'utm_source'   => '9sty',
        'utm_medium'   => 'banner',
        'utm_campaign' => $campaign,
    ]);

header('Location: ' . $targetUrl, true, 302);
exit;

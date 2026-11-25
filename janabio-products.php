<?php
require_once __DIR__ . '/errors.php';
if (!function_exists('get_db')) {
    require_once __DIR__ . '/db.php';
}
/**
 * janabio-products.php
 * Kayjib les produits mn API dyal janabio (status: public), o kaydir cache
 * mahali bach matb9ash tdir curl f kol page load.
 *
 * Usage:
 *   require 'janabio-products.php';
 *   $products = get_janabio_products();
 */

// Retrieve configuration dynamically from settings database table with fallback defaults
$janabio_api_url = get_setting('janabio_api_url', 'https://team.janabio.com/api/products/read') ?: 'https://team.janabio.com/api/products/read';
$janabio_api_key = get_setting('janabio_api_key', getenv('JANABIO_API_KEY') ?: '') ?: '';
$janabio_api_secret = get_setting('janabio_api_secret', getenv('JANABIO_API_SECRET') ?: '') ?: '';

define('JANABIO_API_URL', $janabio_api_url);
define('JANABIO_API_KEY', $janabio_api_key);
define('JANABIO_API_SECRET', $janabio_api_secret);

define('JANABIO_CACHE_FILE', __DIR__ . '/cache/janabio_products.json');
define('JANABIO_CACHE_TTL', 1800); // 30 minutes f seconds

/**
 * Kayjib les produits, mn cache ila mazal saleh, wla mn API ila khass refresh.
 * Kayrja3 array dyal produits: ['name' => ..., 'image' => ..., 'url' => ..., 'price' => ...]
 *
 * @param bool $inStockOnly Ila true (default), kayfilteri wla ghi les produits
 *                          li 3ndhom stock > 0, bach l banner matbanach fih
 *                          chi produit machi mtwafer.
 */
function get_janabio_products(bool $inStockOnly = true): array
{
    $products = [];

    // 1) Cache saleh? rje3 mno mabashra
    if (is_readable(JANABIO_CACHE_FILE)) {
        $age = time() - filemtime(JANABIO_CACHE_FILE);
        if ($age < JANABIO_CACHE_TTL) {
            $cached = json_decode(file_get_contents(JANABIO_CACHE_FILE), true);
            if (is_array($cached) && !empty($cached)) {
                $products = $cached;
            }
        }
    }

    // 2) Cache khass refresh (wla mkayenach) -> dir curl
    if (empty($products)) {
        $raw = janabio_fetch_from_api();
        if ($raw === null) {
            // API failed -> ila kayen cache 9dim (7ta ila fat lo TTL), khod b ida
            // bach matb9ash bla banner
            if (is_readable(JANABIO_CACHE_FILE)) {
                $stale = json_decode(file_get_contents(JANABIO_CACHE_FILE), true);
                if (is_array($stale)) {
                    $products = $stale;
                }
            }
        } else {
            $products = janabio_normalize_products($raw);
            janabio_write_cache($products);
        }
    }

    if ($inStockOnly) {
        $products = array_values(array_filter($products, fn($p) => !empty($p['in_stock'])));
    }

    // 3) Retrieve active custom banners from 9s_custom_banners
    try {
        $pdo = get_db();
        $stmt = $pdo->query("SELECT id, slug, name, image, url, price, promo_price FROM 9s_custom_banners WHERE is_active = 1 AND deleted_at IS NULL");
        $customBanners = [];
        while ($row = $stmt->fetch()) {
            $hasPrice = (!empty($row['price']) && (float)$row['price'] > 0);
            $img = trim((string)($row['image'] ?? ''));
            if ($img !== '' && !preg_match('#^https?://#i', $img)) {
                $img = IMAGE_BASE_URL . ltrim($img, '/');
            }
            $customBanners[] = [
                'id'          => !empty($row['slug']) ? $row['slug'] : ('cb_' . $row['id']),
                'name'        => $row['name'],
                'image'       => $img,
                'url'         => $row['url'],
                'price'       => $hasPrice ? $row['price'] : null,
                'promo_price' => $hasPrice ? $row['promo_price'] : null,
                'in_stock'    => true, // Custom banners are always considered in stock
                'tag'         => $hasPrice ? 'إعلان مخصص' : 'موصى به',
                'is_custom'   => true
            ];
        }
        if (!empty($customBanners)) {
            $products = array_merge($products, $customBanners);
        }
    } catch (Exception $e) {
        // Table might not exist yet during migration, or DB connection failed
    }

    return $products;
}

function janabio_fetch_from_api(): ?array
{
    $ch = curl_init(JANABIO_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['status' => 'public']),
        CURLOPT_HTTPHEADER => [
            'X-API-KEY: ' . JANABIO_API_KEY,
            'X-API-SECRET: ' . JANABIO_API_SECRET,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 8,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log("janabio API fetch failed (HTTP $httpCode): $error");
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('janabio API returned invalid JSON');
        return null;
    }

    return $decoded;
}

/**
 * Kaybadel raw response dyal l'API l shakl sahel l l'banner:
 * id, name, image, url, price, promo_price, in_stock.
 *
 * Chkl l7a9i9i dyal l'API: array fiha wahd object [0] li fih
 * status/message/data, w "data" hiya array dyal les produits.
 * Kol produit fih: title, main_image, slug, price, promo_price, stock...
 */
function janabio_normalize_products(array $raw): array
{
    // L'API kayrja3 array fiha wahd object wahd: [{ status, message, data }]
    $envelope = isset($raw[0]) && is_array($raw[0]) ? $raw[0] : $raw;
    $items = $envelope['data'] ?? $envelope['products'] ?? [];

    if (!is_array($items)) {
        return [];
    }

    $products = [];
    foreach ($items as $item) {
        if (empty($item['main_image']) || empty($item['slug'])) {
            continue;
        }

        $products[] = [
            'id'          => $item['id'] ?? $item['slug'],
            'name'        => $item['title'] ?? '',
            'image'       => $item['main_image'],
            'url'         => 'http://ma.janabio.com/item/' . $item['slug'],
            'price'       => $item['price'] ?? null,
            'promo_price' => $item['promo_price'] ?? null, // ila kayn, houwa taman qbel tkhfid (barré)
            'in_stock'    => (int)($item['stock'] ?? 0) > 0,
            'tag'         => 'إعلان — JanaBio'
        ];
    }

    return $products;
}

function janabio_write_cache(array $products): void
{
    $dir = dirname(JANABIO_CACHE_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(JANABIO_CACHE_FILE, json_encode($products, JSON_UNESCAPED_UNICODE));
}

/*
 * ---------------------------------------------------------------
 * Mithal dyal l'usage (f la page/template dyal 9sty):
 * ---------------------------------------------------------------
 *
 * require __DIR__ . '/janabio-products.php';
 *
 * $products = get_janabio_products();       // ghi les produits f stock
 * if (!empty($products)) {
 *     // khtar wahd b random, wla wahd fixe (mtl ila bghiti dima nafs
 *     // l'produit "featured"), wla rotate 3la 7sab l'yawm
 *     $product = $products[array_rand($products)];
 *
 *     $priceHtml = $product['promo_price']
 *         ? "<s>{$product['promo_price']} MAD</s> {$product['price']} MAD"
 *         : "{$product['price']} MAD";
 *
 *     echo '<a href="/go/promo.php?pid=' . $product['id'] . '" class="jb-banner">';
 *     echo '  <img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">';
 *     echo '  <span>' . htmlspecialchars($product['name']) . '</span>';
 *     echo '  <span class="jb-price">' . $priceHtml . '</span>';
 *     echo '</a>';
 * }
 */

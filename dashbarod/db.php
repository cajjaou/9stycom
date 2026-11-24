<?php
if (file_exists(__DIR__ . '/errors.php')) {
    require_once __DIR__ . '/errors.php';
} elseif (file_exists(__DIR__ . '/../errors.php')) {
    require_once __DIR__ . '/../errors.php';
}
/**
 * db.php — connexion PDO l database dyal 9sty.
 * Bdel les valeurs b 7sab l'environnement dyalek (local/production).
 * Khaseha tkon barra mn webroot ila mmkn, wla mzida f .htaccess/nginx
 * bach matb9ach accessible mباشرة b URL.
 */

// Supply credentials through the PHP-FPM/Apache environment. Never commit them.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('CDN_BASE_URL', rtrim(getenv('CDN_BASE_URL') ?: 'https://cdn.9sty.com', '/'));
define('IMAGE_BASE_URL', CDN_BASE_URL . '/uploads/');
define('FALLBACK_IMAGE', IMAGE_BASE_URL . 'no-images-news.webp');
define('UPLOAD_DIR', rtrim(getenv('UPLOAD_DIR') ?: (dirname(__DIR__) . '/uploads'), '/\\'));

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $missing = [];
        foreach (['DB_NAME', 'DB_USER', 'DB_PASS'] as $required) {
            if (constant($required) === '') {
                $missing[] = $required;
            }
        }
        if ($missing) {
            throw new RuntimeException('Database configuration is missing: ' . implode(', ', $missing) . '.');
        }
        if (!ctype_digit((string) DB_PORT) || (int) DB_PORT < 1 || (int) DB_PORT > 65535) {
            throw new RuntimeException('DB_PORT must be a valid TCP port number.');
        }
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('MySQL connection failed: ' . $e->getMessage(), 0, $e);
        }

        // Auto-migration: Ensure parent_id column exists in news_comments
        try {
            $pdo->query("SELECT parent_id FROM news_comments LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE news_comments ADD COLUMN parent_id INT(11) DEFAULT NULL AFTER post_id");
                $pdo->exec("ALTER TABLE news_comments ADD INDEX idx_parent_id (parent_id)");
            } catch (PDOException $ex) {
                // Ignore or log error
            }
        }

        // Auto-migration: Ensure 9s_adsense table exists and has default slots
        try {
            $pdo->query("SELECT id FROM 9s_adsense LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE `9s_adsense` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `slot_name` VARCHAR(50) NOT NULL UNIQUE,
                      `ad_client` VARCHAR(50) NOT NULL,
                      `ad_slot` VARCHAR(50) NOT NULL,
                      `ad_format` VARCHAR(20) NOT NULL DEFAULT 'auto',
                      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Seed default values
                $stmt = $pdo->prepare("INSERT INTO 9s_adsense (slot_name, ad_client, ad_slot, ad_format, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute(['sidebar_top', 'ca-pub-1234567890123456', '1111111111', 'auto', 1]);
                $stmt->execute(['sidebar_bottom', 'ca-pub-1234567890123456', '2222222222', 'auto', 1]);
            } catch (PDOException $ex) {
                // Ignore or log error
            }
        }

        // Auto-migration: Ensure 9s_custom_banners table exists
        try {
            $pdo->query("SELECT id FROM 9s_custom_banners LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE `9s_custom_banners` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `name` VARCHAR(150) NOT NULL,
                      `image` VARCHAR(255) NOT NULL,
                      `url` VARCHAR(255) NOT NULL,
                      `price` VARCHAR(50) DEFAULT NULL,
                      `promo_price` VARCHAR(50) DEFAULT NULL,
                      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Seed a placeholder custom banner
                $stmt = $pdo->prepare("INSERT INTO 9s_custom_banners (name, image, url, price, promo_price, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute(['JanaBio Gold Honey', FALLBACK_IMAGE, 'https://digitalbyanas.com', '199', '299', 0]);
            } catch (PDOException $ex) {
                // Ignore or log error
            }
        }

        // Auto-migration: Ensure news_tracking table exists
        try {
            $pdo->query("SELECT id FROM news_tracking LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `news_tracking` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `short_code` VARCHAR(50) NOT NULL,
                      `ip_address` VARCHAR(45) DEFAULT NULL,
                      `user_agent` VARCHAR(500) DEFAULT NULL,
                      `referer` VARCHAR(500) DEFAULT NULL,
                      `country` VARCHAR(100) DEFAULT NULL,
                      `city` VARCHAR(100) DEFAULT NULL,
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      KEY `idx_short_code` (`short_code`),
                      KEY `idx_created_at` (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (PDOException $ex) {
            }
        }

        // Auto-migration: Ensure post_type column exists in news_posts with default 'article'
        try {
            $pdo->query("SELECT post_type FROM news_posts LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE news_posts ADD COLUMN post_type VARCHAR(20) NOT NULL DEFAULT 'article' AFTER status");
                $pdo->exec("ALTER TABLE news_posts ADD INDEX idx_nposts_post_type (post_type)");
            } catch (PDOException $ex) {
            }
        }
        // Ensure legacy posts without post_type have post_type = 'article'
        try {
            $pdo->exec("UPDATE news_posts SET post_type = 'article' WHERE post_type IS NULL OR post_type = ''");
        } catch (PDOException $ex) {
        }

        // Cleanup: Ensure no external full URLs remain in news_posts.image
        try {
            $urlRows = $pdo->query("SELECT id, image FROM news_posts WHERE image LIKE 'http%' LIMIT 30")->fetchAll();
            if (!empty($urlRows)) {
                $upd = $pdo->prepare("UPDATE news_posts SET image = ? WHERE id = ?");
                foreach ($urlRows as $r) {
                    $fn = null;
                    if (function_exists('handle_image_from_url')) {
                        $fn = handle_image_from_url($r['image']);
                    }
                    if (!$fn) {
                        $fn = basename(parse_url($r['image'], PHP_URL_PATH));
                    }
                    if (!empty($fn)) {
                        $upd->execute([$fn, $r['id']]);
                    }
                }
            }
        } catch (Exception $ex) {
        }

        // Auto-migration: Ensure indexes on created_at for fast date range filtering in analytics
        try {
            $pdo->exec("ALTER TABLE news_comments ADD INDEX idx_cmt_created (created_at)");
        } catch (PDOException $e) {
        }
        try {
            $pdo->exec("ALTER TABLE short_links ADD INDEX idx_sl_created (created_at)");
        } catch (PDOException $e) {
        }

        // Date-range analytics use these columns to avoid full-table scans.
        foreach ([
            ['news_posts', 'idx_nposts_created_at', 'created_at'],
            ['news_posts', 'idx_nposts_status', 'status'],
            ['news_posts', 'idx_nposts_category_id', 'category_id'],
            ['news_comments', 'idx_cmt_created', 'created_at'],
            ['news_comments', 'idx_cmt_post_id', 'post_id'],
            ['short_links', 'idx_sl_created', 'created_at'],
            ['news_tracking', 'idx_nt_created_at', 'created_at'],
            ['news_tracking', 'idx_nt_short_code', 'short_code'],
            ['jb_promo_clicks', 'idx_jbpc_clicked_at', 'clicked_at'],
            ['ads_tracking', 'idx_at_clicked_at', 'clicked_at'],
            ['ads_tracking', 'idx_at_source', 'ad_source'],
        ] as [$tableName, $indexName, $columnName]) {
            try {
                $indexCheck = $pdo->prepare("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1");
                $indexCheck->execute([$tableName, $indexName]);
                if (!$indexCheck->fetch()) {
                    $pdo->exec("ALTER TABLE `$tableName` ADD INDEX `$indexName` (`$columnName`)");
                }
            } catch (PDOException $e) {
            }
        }
    }
    // ================= Seed default theme colors into existing `settings` table =================
   // Seed default theme colors into `settings` table
try {
    $defaults = [
        'color_bg'            => '#f0f7fa',
        'color_surface'       => '#ffffff',
        'color_surface_2'     => '#f6fafd',
        'color_border'        => '#dcebf2',
        'color_border_strong' => '#b8d4e3',
        'color_text'          => '#1c3452',
        'color_text_muted'    => '#556b86',
        'color_text_faint'    => '#8fa5ba',
        'color_brand'         => '#f1d025',
        'color_brand_600'     => '#8a6e0a',
        'color_brand_50'      => '#fefbe8',
        'color_brand_100'     => '#fbf0a8',
        'color_sidebar_bg'    => '#1c3452',
        'color_sidebar_bg_2'  => '#132740',
        'color_sidebar_text'  => '#d1ebf3',
        'color_grad_start'    => '#f7e46a',
        'color_grad_mid'      => '#f1d025',
        'color_grad_end'      => '#d4b01e',
    ];
    $chk = $pdo->prepare("SELECT 1 FROM settings WHERE setting_key = ? LIMIT 1");
    $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $chk->execute([$k]);
        if (!$chk->fetch()) $ins->execute([$k, $v]);
    }
} catch (PDOException $ex) {}
// ================= Auto-migration: deleted_at for comments, links, banners =================
foreach ([
    ['news_comments',    'idx_ncmt_deleted_at'],
    ['short_links',      'idx_sl_deleted_at'],
    ['9s_custom_banners','idx_banner_deleted_at'],
] as [$tbl, $idxName]) {
    try {
        $pdo->query("SELECT deleted_at FROM `$tbl` LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
        } catch (PDOException $ex) {}
    }
    try {
        $chk = $pdo->prepare("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1");
        $chk->execute([$tbl, $idxName]);
        if (!$chk->fetch()) {
            $pdo->exec("ALTER TABLE `$tbl` ADD INDEX `$idxName` (deleted_at)");
        }
    } catch (PDOException $ex) {}
}
    return $pdo;

}

/*
 * IMAGE_BASE_URL: `image` f news_posts fiha ghi smiya dial fichier
 * (mtl "1780260271_363f3c6d.jpg"), machi URL kamla. Bdel hadi b l'path
 * l7a9i9i fin mkhznin les images f server dyalek.
 */

/**
 * news_categories ma fihach 3mod dyal loun (color). Bach kol category
 * ykhrj biha loun tabet w mayet bddelch f kol load, kanjeneriw loun
 * mn palette fixe b 7sab category_id (modulo).
 */
function category_color(int $categoryId): string
{
    $palette = ['#7AA1A2', '#1B3651', '#4A607A', '#5E7D8A', '#3C5C77', '#8CA8A6'];
    return $palette[$categoryId % count($palette)];
}

function post_thumb(?string $image): string
{
    return $image ? IMAGE_BASE_URL . $image : FALLBACK_IMAGE;
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 3600)
        return 'منذ ' . max(1, floor($diff / 60)) . ' دقيقة';
    if ($diff < 86400)
        return 'منذ ' . floor($diff / 3600) . ' ساعة';
    return 'منذ ' . floor($diff / 86400) . ' يوم';
}

/**
 * slug_is_available() — khdmha f l'admin panel dyalek mli kaykhal9o
 * post jdid wla category jdida, bach ma ywq3ch conflit bin les deux
 * jdolat (news_posts.slug w news_categories.slug 3andhom UNIQUE b
 * wa7dhom, walakin MySQL ma3andouch UNIQUE cross-table, khaskha
 * check b PHP).
 *
 * @param string $slug
 * @param string $excludeType 'post' wla 'category' — jdol li ghadi
 *   n excludiw منو l id ($excludeId), bach l'update dyal nafs l
 *   post/category ma yban lich conflit m3a rassou.
 * @param int|null $excludeId
 */
function slug_is_available(string $slug, string $excludeType = '', ?int $excludeId = null): bool
{
    $pdo = get_db();

    $postSql = "SELECT id FROM news_posts WHERE slug = ?" . ($excludeType === 'post' && $excludeId ? " AND id != ?" : '');
    $params = $excludeType === 'post' && $excludeId ? [$slug, $excludeId] : [$slug];
    $stmt = $pdo->prepare($postSql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        return false;
    }

    $catSql = "SELECT id FROM news_categories WHERE slug = ?" . ($excludeType === 'category' && $excludeId ? " AND id != ?" : '');
    $params = $excludeType === 'category' && $excludeId ? [$slug, $excludeId] : [$slug];
    $stmt = $pdo->prepare($catSql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        return false;
    }
    // Auto-migration: Ensure slug column exists in 9s_custom_banners
    try {
        $pdo->query("SELECT slug FROM 9s_custom_banners LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE 9s_custom_banners ADD COLUMN slug VARCHAR(50) NULL AFTER id");
        } catch (PDOException $ex) {
        }
    }

    // Backfill أي صف بلا slug (كايخدم حتى لو كان column موجود و لكن فيه NULLs)
    try {
        $rows = $pdo->query("SELECT id FROM 9s_custom_banners WHERE slug IS NULL OR slug = ''")->fetchAll();
        if (!empty($rows)) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $updateStmt = $pdo->prepare("UPDATE 9s_custom_banners SET slug = ? WHERE id = ?");
            $chkStmt = $pdo->prepare("SELECT 1 FROM 9s_custom_banners WHERE slug = ? LIMIT 1");
            foreach ($rows as $row) {
                do {
                    $slug = '';
                    for ($i = 0; $i < 12; $i++)
                        $slug .= $chars[random_int(0, strlen($chars) - 1)];
                    $chkStmt->execute([$slug]);
                } while ($chkStmt->fetch());
                $updateStmt->execute([$slug, $row['id']]);
            }
        }
    } catch (PDOException $ex) {
    }

    // افرض NOT NULL + UNIQUE (idempotent — الـ try/catch كايبلع الأخطاء إلا كانو ديجا)
    try {
        $pdo->exec("ALTER TABLE 9s_custom_banners MODIFY COLUMN slug VARCHAR(50) NOT NULL");
    } catch (PDOException $ex) {
    }
    try {
        $pdo->exec("ALTER TABLE 9s_custom_banners ADD UNIQUE INDEX idx_banner_slug (slug)");
    } catch (PDOException $ex) {
    }
    // Auto-migration: Ensure deleted_at exists in news_posts
    try {
        $pdo->query("SELECT deleted_at FROM news_posts LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE news_posts ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            $pdo->exec("ALTER TABLE news_posts ADD INDEX idx_nposts_deleted_at (deleted_at)");
        } catch (PDOException $ex) {
        }
    }
    return true;
}

/**
 * توليد كود عشوائي فريد للـ Short Posts مثل "AjNz6hWej0" بطول 10 خانات
 */
function generate_short_post_code(PDO $pdo, ?int $excludeId = null): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len = strlen($chars);
    do {
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }
        $sql = "SELECT id FROM news_posts WHERE slug = ?" . ($excludeId ? " AND id != ?" : "") . " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($excludeId ? [$code, $excludeId] : [$code]);
    } while ($stmt->fetch());
    return $code;
}

/*
 * Schema l7a9i9i dyal 9sty (news_posts / news_categories / news_comments):
 *
 * news_posts: id, post_id, auther_id, category_id, short_code, title,
 *   slug, excerpt, content (text 3adi, paragraphes mfarqin b \r\n,
 *   machi JSON), metas, image (ghi smiya dial fichier), status
 *   ('draft'|'published'|'archived'), fb_status, created_at, views.
 *
 * news_categories: id, name, slug, status, at_home, meta_title,
 *   meta_description, created_at. (ma fihach color - chouf category_color())
 *
 * news_comments: id, post_id, name, email, comment, created_at.
 */

function get_adsense_ads(): array
{
    static $ads = null;
    if ($ads === null) {
        $ads = [];
        try {
            $pdo = get_db();
            $stmt = $pdo->query("SELECT slot_name, ad_client, ad_slot, ad_format, is_active FROM 9s_adsense WHERE is_active = 1");
            while ($row = $stmt->fetch()) {
                $ads[$row['slot_name']] = $row;
            }
        } catch (PDOException $e) {
            // Ignore database errors during table migration
        }
    }
    return $ads;
}

function render_adsense(string $slotName): string
{
    $ads = get_adsense_ads();
    if (!isset($ads[$slotName]) || !$ads[$slotName]['is_active']) {
        // Fallback placeholder styling so that it doesn't cause layout shift
        return '<div class="ad-slot" style="background:#f1ece1;border:1px dashed #cfc7b8;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#a29c8c;font-size:12px;padding:40px 10px;margin-bottom:20px; font-family:\'Cairo\',sans-serif;">AdSense Slot: ' . htmlspecialchars($slotName) . '</div>';
    }

    $ad = $ads[$slotName];
    $client = htmlspecialchars($ad['ad_client']);
    $slot = htmlspecialchars($ad['ad_slot']);
    $format = htmlspecialchars($ad['ad_format']);

    return '
    <div class="adsense-slot-container" style="margin-bottom: 20px; text-align: center; overflow: hidden; width: 100%;">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="' . $client . '"
             data-ad-slot="' . $slot . '"
             data-ad-format="' . $format . '"
             data-full-width-responsive="true"></ins>
        <script>
             (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>';
}

/* ================= SETTINGS HELPERS ================= */

function get_all_settings(bool $force_refresh = false): array
{
    static $cache = null;
    if ($cache === null || $force_refresh) {
        $cache = [];
        try {
            $pdo = get_db();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM 9s_settings");
            while ($row = $stmt->fetch()) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            // Table may not exist yet — return empty
        }
    }
    return $cache;
}

function get_setting(string $key, $default = null)
{
    $all = get_all_settings();
    return $all[$key] ?? $default;
}

function save_setting(string $key, $value): bool
{
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO 9s_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$key, (string) $value]);
        get_all_settings(true);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
/* ================= JA SETTINGS HELPERS ================= */

function ja_get_all_settings(bool $force_refresh = false): array
{
    static $cache = null;
    if ($cache === null || $force_refresh) {
        $cache = [];
        try {
            $pdo = get_db();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {}
    }
    return $cache;
}

function ja_get_setting(string $key, $default = null)
{
    $all = ja_get_all_settings();
    return $all[$key] ?? $default;
}

function ja_save_setting(string $key, $value): bool
{
    try {
        $pdo = get_db();
        $chk = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? LIMIT 1");
        $chk->execute([$key]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")
                ->execute([(string)$value, $key]);
        } else {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")
                ->execute([$key, (string)$value]);
        }
        ja_get_all_settings(true);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function ja_get_theme_defaults(): array
{
    return [
        'color_bg'            => '#f0f7fa',
        'color_surface'       => '#ffffff',
        'color_surface_2'     => '#f6fafd',
        'color_border'        => '#dcebf2',
        'color_border_strong' => '#b8d4e3',
        'color_text'          => '#1c3452',
        'color_text_muted'    => '#556b86',
        'color_text_faint'    => '#8fa5ba',
        'color_brand'         => '#f1d025',
        'color_brand_600'     => '#8a6e0a',
        'color_brand_50'      => '#fefbe8',
        'color_brand_100'     => '#fbf0a8',
        'color_sidebar_bg'    => '#1c3452',
        'color_sidebar_bg_2'  => '#132740',
        'color_sidebar_text'  => '#d1ebf3',
        'color_grad_start'    => '#f7e46a',
        'color_grad_mid'      => '#f1d025',
        'color_grad_end'      => '#d4b01e',
    ];
}

function ja_get_theme_colors(): array
{
    $defaults = ja_get_theme_defaults();
    $all = ja_get_all_settings();
    $out = [];
    foreach ($defaults as $k => $v) {
        $out[$k] = (isset($all[$k]) && $all[$k] !== '') ? $all[$k] : $v;
    }
    return $out;
}

function ja_theme_css_vars(): array
{
    $c = ja_get_theme_colors();
    return [
        '--bg'              => $c['color_bg'],
        '--surface'         => $c['color_surface'],
        '--surface-2'       => $c['color_surface_2'],
        '--border'          => $c['color_border'],
        '--border-strong'   => $c['color_border_strong'],
        '--text'            => $c['color_text'],
        '--text-muted'      => $c['color_text_muted'],
        '--text-faint'      => $c['color_text_faint'],
        '--brand'           => $c['color_brand'],
        '--brand-600'       => $c['color_brand_600'],
        '--brand-50'        => $c['color_brand_50'],
        '--brand-100'       => $c['color_brand_100'],
        '--sidebar-bg'      => $c['color_sidebar_bg'],
        '--sidebar-bg-2'    => $c['color_sidebar_bg_2'],
        '--sidebar-text'    => $c['color_sidebar_text'],
        '--brand-grad'      => sprintf(
            'linear-gradient(135deg, %s 0%%, %s 55%%, %s 100%%)',
            $c['color_grad_start'],
            $c['color_grad_mid'],
            $c['color_grad_end']
        ),
    ];
}

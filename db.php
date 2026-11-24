<?php
require_once __DIR__ . '/errors.php';
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
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
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
            
            // Check if 'code' column exists
            try {
                $pdo->query("SELECT code FROM 9s_adsense LIMIT 1");
            } catch (PDOException $colEx) {
                $pdo->exec("ALTER TABLE `9s_adsense` ADD COLUMN `code` TEXT NULL AFTER `ad_format`");
            }

            // Ensure article_top, article_middle, article_bottom exist in the table
            $slotsToCheck = ['article_top', 'article_middle', 'article_bottom'];
            foreach ($slotsToCheck as $sName) {
                $chk = $pdo->prepare("SELECT id FROM 9s_adsense WHERE slot_name = ?");
                $chk->execute([$sName]);
                if (!$chk->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO 9s_adsense (slot_name, ad_client, ad_slot, ad_format, is_active) VALUES (?, ?, ?, ?, ?)");
                    $ins->execute([$sName, 'ca-pub-1234567890123456', '3333333333', 'auto', 0]); // Default disabled
                }
            }
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE `9s_adsense` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `slot_name` VARCHAR(50) NOT NULL UNIQUE,
                      `ad_client` VARCHAR(50) NOT NULL,
                      `ad_slot` VARCHAR(50) NOT NULL,
                      `ad_format` VARCHAR(20) NOT NULL DEFAULT 'auto',
                      `code` TEXT NULL,
                      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
                
                // Seed default values
                $stmt = $pdo->prepare("INSERT INTO 9s_adsense (slot_name, ad_client, ad_slot, ad_format, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute(['sidebar_top', 'ca-pub-1234567890123456', '1111111111', 'auto', 1]);
                $stmt->execute(['sidebar_bottom', 'ca-pub-1234567890123456', '2222222222', 'auto', 1]);
                $stmt->execute(['article_top', 'ca-pub-1234567890123456', '3333333333', 'auto', 0]);
                $stmt->execute(['article_middle', 'ca-pub-1234567890123456', '4444444444', 'auto', 0]);
                $stmt->execute(['article_bottom', 'ca-pub-1234567890123456', '5555555555', 'auto', 0]);
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
                $stmt->execute(['JanaBio Gold Honey', 'https://cdn.9sty.com/uploads/no-images-news.webp', 'https://digitalbyanas.com', '199', '299', 0]);
            } catch (PDOException $ex) {
                // Ignore or log error
            }
        }

        // Auto-migration: Ensure 9s_users table exists
        try {
            $pdo->query("SELECT id FROM 9s_users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE `9s_users` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `username` VARCHAR(50) NOT NULL UNIQUE,
                      `email` VARCHAR(150) NOT NULL UNIQUE,
                      `password` VARCHAR(255) NOT NULL,
                      `role` TINYINT(1) NOT NULL DEFAULT 0,
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (PDOException $ex) {
                // Ignore or log error
            }
        }

        // Auto-migration: Ensure news_comments table has status and parent_id
        try {
            $pdo->query("SELECT status FROM news_comments LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `news_comments` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'approved'");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT parent_id FROM news_comments LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `news_comments` ADD COLUMN `parent_id` INT(11) NULL DEFAULT NULL AFTER `post_id`");
            } catch (PDOException $ex) {}
        }

        // Auto-migration: Ensure all existing news_posts have a unique alphanumeric short_code
        try {
            $stmt = $pdo->query("SELECT id, short_code FROM news_posts");
            $allPosts = $stmt->fetchAll();
            foreach ($allPosts as $ep) {
                $code = trim($ep['short_code'] ?? '');
                // If short_code is null, empty, or purely numeric (like 645)
                if ($code === '' || is_numeric($code)) {
                    do {
                        $newCode = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4);
                        // Make sure it has at least one letter so it's not purely numeric
                        if (is_numeric($newCode)) {
                            continue;
                        }
                        $chk = $pdo->prepare("SELECT id FROM news_posts WHERE short_code = ?");
                        $chk->execute([$newCode]);
                    } while ($chk->fetch());
                    
                    $upd = $pdo->prepare("UPDATE news_posts SET short_code = ? WHERE id = ?");
                    $upd->execute([$newCode, $ep['id']]);
                }
            }
        } catch (PDOException $ex) {
            // Ignore if table does not exist
        }

        // Auto-migration: Ensure settings table exists
        try {
            $pdo->query("SELECT id FROM settings LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE `settings` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                      `setting_value` TEXT NULL,
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (PDOException $ex) {
                // Ignore or log error
            }
        }

        // Auto-migration: Ensure show_in_header column exists in news_categories
        try {
            $pdo->query("SELECT show_in_header FROM news_categories LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE news_categories ADD COLUMN show_in_header TINYINT(1) NOT NULL DEFAULT 1 AFTER status");
            } catch (PDOException $ex) {
                // Ignore or log error
            }
        }

        // Auto-migration: Ensure short_links table exists
        try {
            $pdo->query("SELECT id FROM short_links LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE `short_links` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `short_code` VARCHAR(50) NOT NULL UNIQUE,
                      `destination_url` TEXT NOT NULL,
                      `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      KEY `idx_short_code` (`short_code`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
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
                    CREATE TABLE `news_tracking` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `short_code` VARCHAR(50) NOT NULL,
                      `ip_address` VARCHAR(45) DEFAULT NULL,
                      `user_agent` VARCHAR(500) DEFAULT NULL,
                      `referer` VARCHAR(500) DEFAULT NULL,
                      `country` VARCHAR(100) DEFAULT NULL,
                      `city` VARCHAR(100) DEFAULT NULL,
                      `clicked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      KEY `idx_short_code` (`short_code`),
                      KEY `idx_clicked_at` (`clicked_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (PDOException $ex) {
                // Ignore or log error
            }
        }
    }
    return $pdo;
}

/**
 * Settings Helper Functions
 */
function get_setting(string $key, string $default = ''): string
{
    static $settingsCache = null;
    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            $pdo = get_db();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $settingsCache[$row['setting_key']] = $row['setting_value'] ?? '';
            }
        } catch (PDOException $e) {
            // Table may not exist yet
        }
    }
    return $settingsCache[$key] ?? $default;
}

function get_all_settings(): array
{
    $settings = [];
    try {
        $pdo = get_db();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'] ?? '';
        }
    } catch (PDOException $e) {
        // Table may not exist yet
    }
    return $settings;
}

function save_setting(string $key, string $value): bool
{
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

function render_tracking_codes(): string
{
    $html = '';
    
    // 1) Google Analytics
    $ga = trim(get_setting('google_analytics_id', ''));
    if (!empty($ga)) {
        if (strpos($ga, '<script') !== false) {
            $html .= "\n" . $ga . "\n";
        } else {
            $gaEsc = htmlspecialchars($ga, ENT_QUOTES, 'UTF-8');
            $html .= '
<!-- Google Analytics (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=' . $gaEsc . '"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());
  gtag(\'config\', \'' . $gaEsc . '\');
</script>
';
        }
    }

    // 2) Facebook Pixel
    $fb = trim(get_setting('facebook_pixel_id', ''));
    if (!empty($fb)) {
        if (strpos($fb, '<script') !== false) {
            $html .= "\n" . $fb . "\n";
        } else {
            $fbEsc = htmlspecialchars($fb, ENT_QUOTES, 'UTF-8');
            $html .= '
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,\'script\',
\'https://connect.facebook.net/en_US/fbevents.js\');
fbq(\'init\', \'' . $fbEsc . '\');
fbq(\'track\', \'PageView\');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.net/tr?id=' . $fbEsc . '&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
';
        }
    }
    
    return $html;
}

/*
 * IMAGE_BASE_URL: `image` f news_posts fiha ghi smiya dial fichier
 * (mtl "1780260271_363f3c6d.jpg"), machi URL kamla. Bdel hadi b l'path
 * l7a9i9i fin mkhznin les images f server dyalek.
 */
define('IMAGE_BASE_URL', 'https://cdn.9sty.com/uploads/');
define('FALLBACK_IMAGE', 'https://cdn.9sty.com/uploads/no-images-news.webp');

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
    if ($diff < 3600) return 'منذ ' . max(1, floor($diff / 60)) . ' دقيقة';
    if ($diff < 86400) return 'منذ ' . floor($diff / 3600) . ' ساعة';
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

    return true;
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
        return '<div class="ad-slot" style="background:#f1ece1;border:1px dashed #cfc7b8;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#a29c8c;font-size:12px;padding:40px 10px;margin-bottom:20px; font-family:\'Cairo\',sans-serif;">AdSense Slot: ' . htmlspecialchars($slotName) . '</div>';
    }
    
    $ad = $ads[$slotName];
    
    // Render custom tag if set
    if (!empty($ad['code'])) {
        return '
        <div class="custom-ad-slot-container" style="margin-bottom: 20px; text-align: center; overflow: hidden; width: 100%;">
            ' . $ad['code'] . '
        </div>';
    }
    
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

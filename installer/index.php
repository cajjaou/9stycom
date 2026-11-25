<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/errors.php';
require_once dirname(__DIR__) . '/db.php';

$cspNonce = base64_encode(random_bytes(16));
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-" . $cspNonce . "'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

if (getenv('INSTALLER_ENABLED') !== '1' || !app_debug_enabled()) {
    http_response_code(404);
    exit('Not found');
}

$installerToken = (string) getenv('INSTALLER_TOKEN');
if (strlen($installerToken) < 32) {
    http_response_code(503);
    exit('Installer is not configured.');
}

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
    'path' => '/installer/',
]);
session_start();
$_SESSION['installer_csrf'] ??= bin2hex(random_bytes(32));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_time_limit(600);
ini_set('memory_limit', '512M');
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '110M');

function installer_db(): mysqli
{
    return new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
}

function installer_restore_complete(mysqli $db): bool
{
    try {
        return (bool) $db->query("SELECT 1 FROM `_9sty_installer_lock` WHERE id = 1 LIMIT 1")->fetch_row();
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

function installer_mark_complete(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS `_9sty_installer_lock` (
        `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        `completed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("INSERT IGNORE INTO `_9sty_installer_lock` (`id`) VALUES (1)");
}

/**
 * Repair integer id columns from the known application backup schema.
 * This runs only after an authenticated installer action, never on normal
 * site requests. A non-unique index is sufficient for AUTO_INCREMENT and
 * preserves legacy rows even if duplicate ids are present.
 */
function installer_schema_status(mysqli $db): array
{
    $tables = [
        '9s_adsense', '9s_custom_banners', '9s_users',
        'jb_news_sources', 'jb_promo_clicks', 'news_categories', 'news_comments',
        'news_posts', 'news_shortlinks', 'news_tracking',
        'settings', 'share_links', 'short_links',
    ];
    $status = [];
    foreach ($tables as $table) {
        try {
            $stmt = $db->prepare("
                SELECT COLUMN_TYPE, EXTRA
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'id'
                LIMIT 1
            ");
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $column = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$column) {
                $status[] = ['table' => $table, 'type' => 'id missing', 'ok' => false];
                continue;
            }
            $auto = stripos((string) $column['EXTRA'], 'auto_increment') !== false;
            $status[] = [
                'table' => $table,
                'type' => (string) $column['COLUMN_TYPE'],
                'ok' => $auto,
            ];
        } catch (Throwable $e) {
            $status[] = ['table' => $table, 'type' => 'check failed', 'ok' => false];
        }
    }

    try {
        $slug = $db->query("SHOW COLUMNS FROM `9s_custom_banners` LIKE 'slug'")->fetch_assoc();
        $status[] = [
            'table' => '9s_custom_banners.slug',
            'type' => $slug ? (string) $slug['Type'] : 'column missing',
            'ok' => (bool) $slug && stripos((string) $slug['Null'], 'NO') !== false,
        ];
    } catch (Throwable $e) {
        $status[] = ['table' => '9s_custom_banners.slug', 'type' => 'check failed', 'ok' => false];
    }
    return $status;
}

function installer_fix_auto_increment_ids(mysqli $db): array
{
    $tables = [
        '9s_adsense',
        '9s_custom_banners',
        '9s_users',
        'jb_news_sources',
        'jb_promo_clicks',
        'news_categories',
        'news_comments',
        'news_posts',
        'news_shortlinks',
        'news_tracking',
        'settings',
        'share_links',
        'short_links',
    ];
    $result = ['fixed' => [], 'skipped' => [], 'errors' => []];
    $integerTypes = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'];

    foreach ($tables as $table) {
        try {
            $columnQuery = $db->prepare("
                SELECT COLUMN_TYPE, DATA_TYPE, EXTRA, IS_NULLABLE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'id'
                LIMIT 1
            ");
            $columnQuery->bind_param('s', $table);
            $columnQuery->execute();
            $column = $columnQuery->get_result()->fetch_assoc();
            $columnQuery->close();

            if (!$column) {
                $result['skipped'][] = $table . ' (table/id not present)';
                continue;
            }
            if (!in_array(strtolower((string) $column['DATA_TYPE']), $integerTypes, true)) {
                $result['skipped'][] = $table . ' (id is not an integer)';
                continue;
            }
            if (stripos((string) $column['EXTRA'], 'auto_increment') !== false) {
                continue;
            }

            $columnType = strtolower((string) $column['COLUMN_TYPE']);
            if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint)(?:\\([0-9]+\\))?(?: unsigned)?$/D', $columnType)) {
                $result['skipped'][] = $table . ' (unsupported id type)';
                continue;
            }

            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            if (strtoupper((string) $column['IS_NULLABLE']) === 'YES') {
                $nullCount = (int) $db->query(
                    'SELECT COUNT(*) FROM ' . $quotedTable . ' WHERE `id` IS NULL'
                )->fetch_row()[0];
                if ($nullCount > 0) {
                    $result['errors'][] = $table . ': id contains NULL values; left unchanged';
                    continue;
                }
            }

            $indexes = $db->query('SHOW INDEX FROM ' . $quotedTable)->fetch_all(MYSQLI_ASSOC);
            $idIsLeadingIndex = false;
            $indexNames = [];
            foreach ($indexes as $index) {
                $indexNames[(string) $index['Key_name']] = true;
                if ((string) $index['Column_name'] === 'id' && (int) $index['Seq_in_index'] === 1) {
                    $idIsLeadingIndex = true;
                }
            }

            $alterParts = [];
            if (!$idIsLeadingIndex) {
                $indexName = 'idx_9sty_auto_id';
                $suffix = 1;
                while (isset($indexNames[$indexName])) {
                    $indexName = 'idx_9sty_auto_id_' . $suffix++;
                }
                $quotedIndex = '`' . $indexName . '`';
                $alterParts[] = 'ADD INDEX ' . $quotedIndex . ' (`id`)';
            }
            $alterParts[] = 'MODIFY `id` ' . $columnType . ' NOT NULL AUTO_INCREMENT';
            $db->query('ALTER TABLE ' . $quotedTable . ' ' . implode(', ', $alterParts));
            $result['fixed'][] = $table;
        } catch (Throwable $e) {
            $result['errors'][] = $table . ': ' . $e->getMessage();
            error_log('[installer-id-migration] ' . $table . ': ' . $e->getMessage());
        }
    }

    return $result;
}

function installer_id_repair_message(array $result): string
{
    $message = '';
    if ($result['fixed']) {
        $message .= ' Enabled AUTO_INCREMENT for: ' . implode(', ', $result['fixed']) . '.';
    }
    if ($result['skipped']) {
        $message .= ' Skipped: ' . implode(', ', $result['skipped']) . '.';
    }
    if ($result['errors']) {
        $message .= ' ID repair warnings: ' . implode(' | ', $result['errors']) . '.';
    }
    return $message;
}

function installer_repair_banner_slug(mysqli $db): string
{
    try {
        $tableQuery = $db->prepare("
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '9s_custom_banners'
            LIMIT 1
        ");
        $tableQuery->execute();
        if (!$tableQuery->get_result()->fetch_row()) {
            return ' Banner slug migration skipped: table 9s_custom_banners is absent.';
        }

        $columnQuery = $db->query("SHOW COLUMNS FROM `9s_custom_banners` LIKE 'slug'");
        if (!$columnQuery->fetch_assoc()) {
            $db->query("ALTER TABLE `9s_custom_banners` ADD COLUMN `slug` VARCHAR(32) NULL AFTER `id`");
        }

        $rows = $db->query("
            SELECT id FROM `9s_custom_banners`
            WHERE slug IS NULL OR slug = ''
            ORDER BY id
        ")->fetch_all(MYSQLI_ASSOC);
        $check = $db->prepare("SELECT 1 FROM `9s_custom_banners` WHERE slug = ? LIMIT 1");
        $update = $db->prepare("
            UPDATE `9s_custom_banners`
            SET slug = ?
            WHERE id = ? AND (slug IS NULL OR slug = '')
            LIMIT 1
        ");
        foreach ($rows as $row) {
            $base = 'legacy-' . max(0, (int) $row['id']);
            $slug = $base;
            $suffix = 1;
            do {
                $check->bind_param('s', $slug);
                $check->execute();
                if (!$check->get_result()->fetch_row()) {
                    break;
                }
                $slug = $base . '-' . $suffix++;
            } while (true);

            $bannerId = (int) $row['id'];
            $update->bind_param('si', $slug, $bannerId);
            $update->execute();
        }

        $db->query("ALTER TABLE `9s_custom_banners` MODIFY COLUMN `slug` VARCHAR(50) NOT NULL");
        $indexes = $db->query("SHOW INDEX FROM `9s_custom_banners`")->fetch_all(MYSQLI_ASSOC);
        $hasUniqueSlugIndex = false;
        foreach ($indexes as $index) {
            if ((string) $index['Column_name'] === 'slug' && (int) $index['Non_unique'] === 0) {
                $hasUniqueSlugIndex = true;
                break;
            }
        }
        if (!$hasUniqueSlugIndex) {
            $db->query("ALTER TABLE `9s_custom_banners` ADD UNIQUE KEY `uq_9s_custom_banners_slug` (`slug`)");
        }

        return ' Added/backfilled slug on 9s_custom_banners.';
    } catch (Throwable $e) {
        error_log('[installer-banner-migration] ' . $e->getMessage());
        return ' Banner slug migration warning: ' . $e->getMessage();
    }
}

/** Split a plain SQL dump on semicolons outside strings, identifiers, and comments. */
function installer_sql_statements(string $sql): Generator
{
    $length = strlen($sql);
    $start = 0;
    $quote = null;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($ch === "\n") $lineComment = false;
            continue;
        }
        if ($blockComment) {
            if ($ch === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }
        if ($quote !== null) {
            if ($ch === '\\') {
                $i++;
                continue;
            }
            if ($ch === $quote) {
                if ($next === $quote) {
                    $i++;
                    continue;
                }
                $quote = null;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
        } elseif ($ch === '#' || ($ch === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))) {
            $lineComment = true;
            if ($ch === '-') $i++;
        } elseif ($ch === '/' && $next === '*') {
            $blockComment = true;
            $i++;
        } elseif ($ch === ';') {
            $statement = trim(substr($sql, $start, $i - $start));
            if ($statement !== '') yield $statement;
            $start = $i + 1;
        }
    }

    $statement = trim(substr($sql, $start));
    if ($statement !== '') yield $statement;
}

function installer_without_leading_comments(string $statement): string
{
    $statement = ltrim($statement);
    while ($statement !== '') {
        if (preg_match('/^(?:--[^\r\n]*(?:\r?\n|$)|\#[^\r\n]*(?:\r?\n|$)|\/\*.*?\*\/)/s', $statement, $match)) {
            $statement = ltrim(substr($statement, strlen($match[0])));
            continue;
        }
        break;
    }
    return $statement;
}

function installer_table_ddl(string $statement): ?array
{
    $sql = installer_without_leading_comments($statement);
    $identifier = '(?:`((?:``|[^`])+)`|([A-Za-z0-9_$]+))';
    $pattern = '/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:' . $identifier . '\s*\.\s*)?' . $identifier . '/i';
    if (!preg_match($pattern, $sql, $match)) return null;

    $table = str_replace('``', '`', ($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));
    $ddl = preg_replace('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`(?:``|[^`])+`|[A-Za-z0-9_$]+)\s*\.\s*/i', 'CREATE TABLE IF NOT EXISTS ', $sql, 1, $qualified);
    if (!$qualified) {
        $ddl = preg_replace('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?/i', 'CREATE TABLE IF NOT EXISTS ', $sql, 1);
    }
    return [$table, $ddl];
}

function installer_local_sql_files(): array
{
    $files = [];
    $roots = [
        '' => __DIR__,
        '../' => dirname(__DIR__),
    ];
    foreach ($roots as $prefix => $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            continue;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($fullPath)) {
                continue;
            }
            $lower = strtolower($entry);
            if (str_ends_with($lower, '.sql') || str_ends_with($lower, '.sql.gz')) {
                $key = $prefix . $entry;
                $files[$key] = [
                    'path' => $fullPath,
                    'label' => $key . ' (' . number_format((filesize($fullPath) ?: 0) / 1048576, 1) . ' MB)',
                ];
            }
        }
    }
    ksort($files);
    return $files;
}

function installer_decode_sql(string $raw, string $filename): array
{
    if (str_ends_with(strtolower($filename), '.gz')) {
        if (!function_exists('gzdecode')) {
            throw new RuntimeException('Gzip decompression is not available in this PHP build.');
        }
        $decoded = @gzdecode($raw);
        if ($decoded === false) {
            throw new RuntimeException('The gzipped SQL backup could not be decompressed.');
        }
        $raw = $decoded;
    }

    if (trim($raw) === '') {
        throw new RuntimeException('The SQL backup is empty or unreadable.');
    }

    // The source backup uses a MariaDB UCA-14 collation; MySQL uses UCA-9 names.
    $collationConversions = substr_count(strtolower($raw), 'utf8mb4_uca1400_ai_ci');
    if ($collationConversions > 0) {
        $raw = str_ireplace('utf8mb4_uca1400_ai_ci', 'utf8mb4_0900_ai_ci', $raw);
    }

    // MariaDB allows `date ... DEFAULT current_timestamp()`, which MySQL 8 rejects
    // with "Invalid default value". Convert it to MySQL 8's `DEFAULT (CURDATE())`.
    $raw = preg_replace(
        '/(\bdate\b(?:\s+NOT\s+NULL|\s+NULL)?\s+DEFAULT\s+)current_timestamp\s*\(\s*\)/i',
        '$1(CURDATE())',
        $raw
    ) ?? $raw;

    return [$raw, basename($filename), $collationConversions];
}

function installer_load_sql_source(array $localFiles): array
{
    $uploadError = $_FILES['backup']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError !== UPLOAD_ERR_NO_FILE) {
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Choose a valid SQL backup file.');
        }
        $file = $_FILES['backup'];
        if (!is_uploaded_file($file['tmp_name']) || (int) $file['size'] < 1 || (int) $file['size'] > 100 * 1024 * 1024) {
            throw new RuntimeException('The uploaded file is invalid or exceeds the 100 MB limit.');
        }
        $lowerName = strtolower((string) $file['name']);
        if (!str_ends_with($lowerName, '.sql') && !str_ends_with($lowerName, '.sql.gz')) {
            throw new RuntimeException('Upload a .sql or .sql.gz file.');
        }
        $sql = file_get_contents($file['tmp_name']);
        if ($sql === false) {
            throw new RuntimeException('The uploaded SQL backup could not be read.');
        }
        return installer_decode_sql($sql, (string) $file['name']);
    }

    $selectedLocal = (string) ($_POST['local_file'] ?? '');
    if ($selectedLocal !== '' && isset($localFiles[$selectedLocal])) {
        $path = $localFiles[$selectedLocal]['path'];
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('The local SQL backup could not be read.');
        }
        return installer_decode_sql($sql, basename($path));
    }

    throw new RuntimeException('Choose a SQL backup file to upload or select a local SQL file.');
}

function installer_flush_multi_query(mysqli $db, string $batch): void
{
    $db->multi_query($batch);
    do {
        if ($res = $db->store_result()) {
            $res->free();
        }
    } while ($db->more_results() && $db->next_result());
}

/**
 * Execute a full SQL dump in ~2 MB multi_query batches so large dumps do not
 * exceed MySQL's max_allowed_packet while remaining fast.
 */
function installer_execute_sql_dump(mysqli $db, string $sql, int $maxBatchBytes = 2097152): int
{
    $batch = '';
    $batchBytes = 0;
    $statements = 0;

    foreach (installer_sql_statements($sql) as $statement) {
        $piece = $statement . ";\n";
        $pieceBytes = strlen($piece);

        if ($batch !== '' && ($batchBytes + $pieceBytes) > $maxBatchBytes) {
            installer_flush_multi_query($db, $batch);
            $batch = '';
            $batchBytes = 0;
        }

        if ($pieceBytes > $maxBatchBytes) {
            installer_flush_multi_query($db, $piece);
        } else {
            $batch .= $piece;
            $batchBytes += $pieceBytes;
        }
        $statements++;
    }

    if ($batch !== '') {
        installer_flush_multi_query($db, $batch);
    }

    return $statements;
}

$error = '';
$result = '';
$completed = false;
$localFiles = installer_local_sql_files();

try {
    $db = installer_db();
    $db->set_charset(DB_CHARSET);
    $completed = installer_restore_complete($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals((string) $_SESSION['installer_csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('The form expired. Reload the page and try again.');
        }
        if (!hash_equals($installerToken, (string) ($_POST['installer_token'] ?? ''))) {
            http_response_code(403);
            throw new RuntimeException('Installer token is incorrect.');
        }
        $mode = (string) ($_POST['mode'] ?? '');
        if ($completed && $mode !== 'repair') {
            throw new RuntimeException('The full restore has already completed. Only protected schema repair is allowed.');
        }

        [$sql, $filename, $collationConversions] = installer_load_sql_source($localFiles);

        if ($mode === 'repair') {
            $existing = [];
            foreach ($db->query('SHOW TABLES')->fetch_all(MYSQLI_NUM) as $row) $existing[$row[0]] = true;
            $created = [];
            $db->query('SET FOREIGN_KEY_CHECKS=0');
            foreach (installer_sql_statements($sql) as $statement) {
                $tableDdl = installer_table_ddl($statement);
                if ($tableDdl === null) continue;
                [$table, $ddl] = $tableDdl;
                if (isset($existing[$table])) continue;
                $db->query($ddl);
                $existing[$table] = true;
                $created[] = $table;
            }
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            $idRepair = installer_fix_auto_increment_ids($db);
            $bannerSlugRepair = installer_repair_banner_slug($db);
            $result = $created
                ? 'Added missing tables: ' . implode(', ', $created) . '. No rows were imported.'
                : 'No missing tables were found. No rows were imported.';
            $result .= installer_id_repair_message($idRepair) . $bannerSlugRepair;
            if ($collationConversions > 0) $result .= ' Converted ' . $collationConversions . ' MariaDB collation references for MySQL.';
        } elseif ($mode === 'restore' && ($_POST['confirm_restore'] ?? '') === 'EMPTY AND RESTORE') {
            if (in_array(strtolower(DB_NAME), ['mysql', 'information_schema', 'performance_schema', 'sys'], true)) {
                throw new RuntimeException('Refusing to restore into a MySQL system database. Set DB_NAME to the application database.');
            }

            $createCount = 0;
            foreach (installer_sql_statements($sql) as $statement) {
                if (installer_table_ddl($statement) !== null) $createCount++;
            }
            if ($createCount === 0) {
                throw new RuntimeException('The uploaded backup contains no CREATE TABLE statements. Nothing was deleted.');
            }

            // A reverse proxy may time out before a large restore finishes.
            // Let PHP complete the import even if the browser connection closes.
            ignore_user_abort(true);
            set_time_limit(600);

            $db->query('SET FOREIGN_KEY_CHECKS=0');

            // Empty only DB_NAME: leave MySQL system databases and other schemas untouched.
            $views = [];
            $tables = [];
            foreach ($db->query('SHOW FULL TABLES')->fetch_all(MYSQLI_NUM) as $row) {
                $identifier = '`' . str_replace('`', '``', (string) $row[0]) . '`';
                if (strtoupper((string) ($row[1] ?? '')) === 'VIEW') $views[] = $identifier;
                else $tables[] = $identifier;
            }
            foreach ($views as $identifier) $db->query('DROP VIEW IF EXISTS ' . $identifier);
            foreach ($tables as $identifier) $db->query('DROP TABLE IF EXISTS ' . $identifier);

            // Execute statements in ~2 MB multi_query batches to avoid MySQL max_allowed_packet limits.
            $statementCount = installer_execute_sql_dump($db, $sql);
            $idRepair = installer_fix_auto_increment_ids($db);
            $bannerSlugRepair = installer_repair_banner_slug($db);
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            installer_mark_complete($db);
            $completed = true;
            $result = 'Backup ' . $filename . ' imported successfully (' . $statementCount . ' SQL statements) into database ' . DB_NAME . '. Disable the installer now.';
            $result .= installer_id_repair_message($idRepair) . $bannerSlugRepair;
            if ($collationConversions > 0) $result .= ' Converted ' . $collationConversions . ' MariaDB collation references for MySQL.';
        } else {
            throw new RuntimeException('Choose a valid action. Full restore requires typing EMPTY AND RESTORE.');
        }
    }
} catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
        try { $db->query('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $ignored) {}
    }
    $error = $e->getMessage();
}

$schemaStatus = [];
if (isset($db) && $db instanceof mysqli) {
    $schemaStatus = installer_schema_status($db);
}
$totalChecks = count($schemaStatus);
$readyChecks = 0;
foreach ($schemaStatus as $item) {
    if (!empty($item['ok'])) {
        $readyChecks++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>9sty Database Installer</title>
  <style>
    :root{
      --bg:#0b1120;--surface:#111827;--surface-2:#1e293b;--border:#293548;
      --text:#f1f5f9;--muted:#94a3b8;--accent:#6366f1;--accent-2:#818cf8;
      --ok:#10b981;--ok-bg:rgba(16,185,129,.12);
      --warn:#f59e0b;--warn-bg:rgba(245,158,11,.12);
      --err:#ef4444;--err-bg:rgba(239,68,68,.12);
    }
    *{box-sizing:border-box}
    body{margin:0;font:14px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text)}
    .topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .brand{display:flex;align-items:center;gap:12px}
    .brand-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#3b82f6);display:grid;place-items:center;font-weight:800;color:#fff;font-size:15px;letter-spacing:-.5px}
    .brand h1{margin:0;font-size:17px;font-weight:700}
    .brand p{margin:0;font-size:12px;color:var(--muted)}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--surface-2)}
    .pill.ok{color:var(--ok);background:var(--ok-bg);border-color:rgba(16,185,129,.3)}
    .pill.warn{color:var(--warn);background:var(--warn-bg);border-color:rgba(245,158,11,.3)}
    .pill.err{color:var(--err);background:var(--err-bg);border-color:rgba(239,68,68,.3)}
    .container{max-width:1240px;margin:24px auto;padding:0 20px 48px}
    .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px}
    .kpi{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px 18px}
    .kpi-label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
    .kpi-value{font-size:20px;font-weight:700;margin-top:4px;word-break:break-word}
    .kpi-sub{font-size:12px;color:var(--muted);margin-top:2px}
    .alert{padding:14px 16px;border-radius:10px;margin-bottom:16px;border:1px solid;white-space:pre-wrap}
    .alert.notice{background:var(--warn-bg);border-color:rgba(245,158,11,.35);color:#fde68a}
    .alert.error{background:var(--err-bg);border-color:rgba(239,68,68,.4);color:#fecaca}
    .alert.success{background:var(--ok-bg);border-color:rgba(16,185,129,.4);color:#a7f3d0}
    .grid{display:grid;grid-template-columns:1.05fr .95fr;gap:20px;align-items:start}
    @media (max-width:940px){.grid{grid-template-columns:1fr}}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:18px}
    .card-header{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
    .card-title{margin:0;font-size:16px;font-weight:700}
    .card-desc{margin:0 0 14px;color:var(--muted);font-size:13px}
    label{display:block;font-weight:600;font-size:13px;margin:14px 0 6px;color:#cbd5e1}
    input[type=password],input[type=text],input:not([type]),select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font:inherit;outline:none}
    input:focus,select:focus{border-color:var(--accent)}
    .dropzone{position:relative;border:1.5px dashed #3b4a63;border-radius:10px;padding:18px;text-align:center;background:rgba(15,23,42,.55);transition:border-color .15s,background .15s;cursor:pointer}
    .dropzone.dragover{border-color:var(--accent-2);background:rgba(99,102,241,.1)}
    .dropzone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
    .dropzone-title{font-weight:600;color:var(--text)}
    .dropzone-sub{font-size:12px;color:var(--muted);margin-top:4px}
    .file-meta{margin-top:10px;font-size:12px;color:var(--accent-2);font-weight:600}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:11px 16px;border-radius:9px;border:none;font:inherit;font-weight:600;cursor:pointer;margin-top:14px;transition:opacity .15s,transform .05s}
    .btn:disabled{opacity:.55;cursor:not-allowed}
    .btn-primary{background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff}
    .btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff}
    .progress-card{display:none;background:var(--surface-2);border:1px solid #3b4a63;border-radius:12px;padding:16px;margin-top:16px}
    .progress-card.active{display:block}
    .progress-top{display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:13px;margin-bottom:8px}
    .progress-track{height:10px;background:var(--bg);border-radius:999px;overflow:hidden;border:1px solid var(--border)}
    .progress-bar{height:100%;width:0%;background:linear-gradient(90deg,#6366f1,#38bdf8);transition:width .15s linear}
    .progress-bar.indeterminate{width:100%;background:linear-gradient(90deg,#6366f1,#10b981,#6366f1);background-size:200% 100%;animation:pulseSlide 1.4s linear infinite}
    @keyframes pulseSlide{0%{background-position:200% 0}100%{background-position:0 0}}
    .progress-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px;font-size:12px;color:var(--muted)}
    .progress-stats strong{display:block;color:var(--text);font-size:13px}
    .table-wrap{overflow-x:auto}
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--border);font-size:13px}
    th{color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
    code{background:var(--bg);padding:2px 6px;border-radius:5px;font-size:12px;color:#c7d2fe}
    .actions-split{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    @media (max-width:640px){.actions-split{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="brand-icon">9S</div>
      <div>
        <h1>9sty Database Installer</h1>
        <p>Protected backup restore, chunked SQL import &amp; schema repair</p>
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span class="pill">Schema repair v2</span>
      <span class="pill <?= $completed ? 'ok' : 'warn' ?>"><?= $completed ? 'Restore Locked' : 'Restore Unlocked' ?></span>
    </div>
  </header>

  <main class="container">
    <div class="kpis">
      <div class="kpi">
        <div class="kpi-label">Target Database</div>
        <div class="kpi-value"><?= htmlspecialchars(DB_NAME ?: 'Not set', ENT_QUOTES, 'UTF-8') ?></div>
        <div class="kpi-sub">Host: <?= htmlspecialchars(DB_HOST, ENT_QUOTES, 'UTF-8') ?>:<?= htmlspecialchars((string) DB_PORT, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Schema Health</div>
        <div class="kpi-value"><?= $readyChecks ?> / <?= $totalChecks ?> Ready</div>
        <div class="kpi-sub"><?= ($totalChecks > 0 && $readyChecks === $totalChecks) ? 'All tracked tables &amp; slug OK' : 'Some tables need repair' ?></div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Local SQL Dumps</div>
        <div class="kpi-value"><?= count($localFiles) ?> Found</div>
        <div class="kpi-sub">Supports .sql &amp; .sql.gz</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Upload Limit</div>
        <div class="kpi-value">100 MB</div>
        <div class="kpi-sub">2 MB MySQL batch chunks</div>
      </div>
    </div>

    <div class="alert notice">Use only while the site is private. A full restore permanently drops every table and view in <strong><?= htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') ?></strong>, then imports the backup. Other databases are not touched.</div>
    <?php if ($error !== ''): ?><div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($result !== ''): ?><div class="alert success"><?= htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>

    <div class="grid">
      <div>
        <form id="installer-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>">

          <section class="card">
            <div class="card-header">
              <h2 class="card-title">1. Credentials &amp; Backup Source</h2>
              <span class="pill">.sql / .sql.gz</span>
            </div>
            <label for="token">Installer token</label>
            <input id="token" type="password" name="installer_token" required autocomplete="off" placeholder="Enter INSTALLER_TOKEN">

            <?php if ($localFiles): ?>
              <label for="local_file">Select a local SQL file (recommended for large dumps)</label>
              <select id="local_file" name="local_file">
                <option value="">-- Use uploaded file below --</option>
                <?php foreach ($localFiles as $key => $info): ?>
                  <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>

            <label for="backup">Or upload SQL backup (max 100 MB)</label>
            <div class="dropzone" id="dropzone">
              <input id="backup" type="file" name="backup" accept=".sql,.gz,application/sql,application/gzip" <?= $localFiles ? '' : 'required' ?>>
              <div class="dropzone-title">Drop your .sql or .sql.gz file here, or click to browse</div>
              <div class="dropzone-sub">Live upload progress, speed &amp; remaining time will appear below</div>
              <div class="file-meta" id="file-meta"></div>
            </div>

            <div class="progress-card" id="progress-card" aria-live="polite">
              <div class="progress-top">
                <span id="progress-phase">Uploading backup...</span>
                <span id="progress-pct">0%</span>
              </div>
              <div class="progress-track">
                <div class="progress-bar" id="progress-bar"></div>
              </div>
              <div class="progress-stats">
                <div>Transferred<strong id="progress-bytes">0 MB / 0 MB</strong></div>
                <div>Speed<strong id="progress-speed">—</strong></div>
                <div>Remaining<strong id="progress-eta">Calculating...</strong></div>
              </div>
            </div>
          </section>

          <?php if ($completed): ?>
            <section class="card">
              <div class="card-header">
                <h2 class="card-title">2. Protected Schema Repair</h2>
                <span class="pill ok">Restore Locked</span>
              </div>
              <p class="card-desc">Full restore is complete and locked. You can still run schema repair to create missing tables, enable AUTO_INCREMENT IDs, and backfill banner slugs.</p>
              <button class="btn btn-primary" type="submit" name="mode" value="repair">Repair missing tables, all IDs &amp; banner slug</button>
            </section>
          <?php else: ?>
            <div class="actions-split">
              <section class="card">
                <h2 class="card-title">2a. Repair Schema Only</h2>
                <p class="card-desc">Creates missing tables and repairs IDs &amp; banner slugs without touching existing rows.</p>
                <button class="btn btn-primary" type="submit" name="mode" value="repair">Repair schema &amp; IDs</button>
              </section>

              <section class="card">
                <h2 class="card-title">2b. Full Database Restore</h2>
                <p class="card-desc">Drops all tables/views in <code><?= htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') ?></code> and imports the full dump.</p>
                <label for="confirm">Type <code>EMPTY AND RESTORE</code></label>
                <input id="confirm" name="confirm_restore" autocomplete="off" placeholder="EMPTY AND RESTORE">
                <button class="btn btn-danger" type="submit" name="mode" value="restore">Import full backup</button>
              </section>
            </div>
          <?php endif; ?>
        </form>
      </div>

      <section class="card">
        <div class="card-header">
          <h2 class="card-title">Database Schema Status</h2>
          <span class="pill <?= ($totalChecks > 0 && $readyChecks === $totalChecks) ? 'ok' : 'warn' ?>"><?= $readyChecks ?>/<?= $totalChecks ?> Ready</span>
        </div>
        <p class="card-desc">Live read-only check of the 15 integer ID tables and <code>9s_custom_banners.slug</code>.</p>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Table / Column</th><th>Definition</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($schemaStatus as $item): ?>
              <tr>
                <td><code><?= htmlspecialchars($item['table'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code></td>
                <td><?= htmlspecialchars($item['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td><span class="pill <?= $item['ok'] ? 'ok' : 'warn' ?>"><?= $item['ok'] ? 'READY' : 'NEEDS REPAIR' ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>

  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
  (function () {
    const form = document.getElementById('installer-form');
    if (!form) return;

    const fileInput = document.getElementById('backup');
    const dropzone = document.getElementById('dropzone');
    const fileMeta = document.getElementById('file-meta');
    const progressCard = document.getElementById('progress-card');
    const progressPhase = document.getElementById('progress-phase');
    const progressPct = document.getElementById('progress-pct');
    const progressBar = document.getElementById('progress-bar');
    const progressBytes = document.getElementById('progress-bytes');
    const progressSpeed = document.getElementById('progress-speed');
    const progressEta = document.getElementById('progress-eta');

    const fmtBytes = (b) => {
      if (b < 1024) return b + ' B';
      if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
      return (b / 1048576).toFixed(2) + ' MB';
    };

    const fmtDuration = (sec) => {
      if (!isFinite(sec) || sec < 0) return 'Calculating...';
      sec = Math.ceil(sec);
      if (sec < 60) return sec + 's left';
      const m = Math.floor(sec / 60);
      const s = sec % 60;
      return m + 'm ' + (s < 10 ? '0' : '') + s + 's left';
    };

    const updateFileLabel = () => {
      if (fileInput && fileInput.files && fileInput.files[0]) {
        const f = fileInput.files[0];
        fileMeta.textContent = 'Selected: ' + f.name + ' (' + fmtBytes(f.size) + ')';
      } else {
        fileMeta.textContent = '';
      }
    };

    if (fileInput) {
      fileInput.addEventListener('change', updateFileLabel);
    }
    if (dropzone) {
      ['dragenter', 'dragover'].forEach((ev) => {
        dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
      });
      ['dragleave', 'drop'].forEach((ev) => {
        dropzone.addEventListener(ev, () => dropzone.classList.remove('dragover'));
      });
    }

    let clickedMode = '';
    form.querySelectorAll('button[type="submit"][name="mode"]').forEach((btn) => {
      btn.addEventListener('click', () => { clickedMode = btn.value; });
    });

    form.addEventListener('submit', (e) => {
      if (typeof XMLHttpRequest === 'undefined' || typeof FormData === 'undefined') return;
      e.preventDefault();

      const fd = new FormData(form);
      if (clickedMode) fd.set('mode', clickedMode);

      const hasUpload = fileInput && fileInput.files && fileInput.files.length > 0;
      const buttons = form.querySelectorAll('button[type="submit"]');
      buttons.forEach((b) => { b.disabled = true; });

      progressCard.classList.add('active');
      progressBar.classList.remove('indeterminate');
      progressBar.style.width = hasUpload ? '0%' : '100%';
      progressPct.textContent = hasUpload ? '0%' : '100%';
      progressPhase.textContent = hasUpload ? 'Uploading SQL backup...' : 'Importing local SQL backup on server...';
      progressBytes.textContent = hasUpload ? '0 MB / ' + fmtBytes(fileInput.files[0].size) : 'Local file';
      progressSpeed.textContent = '—';
      progressEta.textContent = hasUpload ? 'Calculating...' : 'Running...';

      const startTime = Date.now();
      let serverTimer = null;

      const startServerPhase = () => {
        if (serverTimer) return;
        const serverStart = Date.now();
        progressPhase.textContent = 'Upload complete — Importing & repairing database...';
        progressPct.textContent = '100%';
        progressBar.style.width = '100%';
        progressBar.classList.add('indeterminate');
        progressEta.textContent = '0s elapsed';
        serverTimer = setInterval(() => {
          const elapsed = Math.floor((Date.now() - serverStart) / 1000);
          progressEta.textContent = elapsed + 's elapsed';
        }, 1000);
      };

      if (!hasUpload) {
        startServerPhase();
      }

      const xhr = new XMLHttpRequest();
      xhr.open('POST', window.location.href, true);

      xhr.upload.addEventListener('progress', (ev) => {
        if (!ev.lengthComputable || !hasUpload) return;
        const pct = Math.min(100, Math.round((ev.loaded / ev.total) * 100));
        const elapsedSec = Math.max(0.1, (Date.now() - startTime) / 1000);
        const bps = ev.loaded / elapsedSec;
        const remainingSec = bps > 0 ? (ev.total - ev.loaded) / bps : Infinity;

        progressBar.style.width = pct + '%';
        progressPct.textContent = pct + '%';
        progressBytes.textContent = fmtBytes(ev.loaded) + ' / ' + fmtBytes(ev.total);
        progressSpeed.textContent = fmtBytes(bps) + '/s';
        progressEta.textContent = pct >= 100 ? 'Finishing...' : fmtDuration(remainingSec);

        if (ev.loaded >= ev.total) {
          startServerPhase();
        }
      });

      xhr.addEventListener('load', () => {
        if (serverTimer) clearInterval(serverTimer);
        if (xhr.status >= 200 && xhr.status < 500 && xhr.responseText) {
          document.open();
          document.write(xhr.responseText);
          document.close();
        } else {
          buttons.forEach((b) => { b.disabled = false; });
          progressBar.classList.remove('indeterminate');
          progressPhase.textContent = 'Server returned HTTP ' + xhr.status + ' (import may still be finishing in background — reload to check Schema Status).';
        }
      });

      xhr.addEventListener('error', () => {
        if (serverTimer) clearInterval(serverTimer);
        buttons.forEach((b) => { b.disabled = false; });
        progressBar.classList.remove('indeterminate');
        progressPhase.textContent = 'Network error or proxy timeout. Reload the page to check Schema Status.';
      });

      xhr.send(fd);
    });
  })();
  </script>
</body>
</html>

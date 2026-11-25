<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/errors.php';
require_once dirname(__DIR__) . '/db.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

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
        '9s_adsense', '9s_custom_banners', '9s_users', 'ads_tracking',
        'jb_news_sources', 'jb_promo_clicks', 'news_categories', 'news_comments',
        'news_posts', 'news_settings', 'news_shortlinks', 'news_tracking',
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
        'ads_tracking',
        'jb_news_sources',
        'jb_promo_clicks',
        'news_categories',
        'news_comments',
        'news_posts',
        'news_settings',
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

        $db->query("ALTER TABLE `9s_custom_banners` MODIFY COLUMN `slug` VARCHAR(32) NOT NULL");
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

function installer_validate_upload(): array
{
    if (!isset($_FILES['backup']) || ($_FILES['backup']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a valid SQL backup file.');
    }
    $file = $_FILES['backup'];
    if (!is_uploaded_file($file['tmp_name']) || (int) $file['size'] < 1 || (int) $file['size'] > 100 * 1024 * 1024) {
        throw new RuntimeException('The uploaded file is invalid or exceeds the 100 MB limit.');
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql') {
        throw new RuntimeException('Upload a plain .sql file.');
    }
    $sql = file_get_contents($file['tmp_name']);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('The SQL backup is empty or unreadable.');
    }
    // The source backup uses a MariaDB UCA-14 collation; MySQL uses UCA-9 names.
    $collationConversions = substr_count(strtolower($sql), 'utf8mb4_uca1400_ai_ci');
    if ($collationConversions > 0) {
        $sql = str_ireplace('utf8mb4_uca1400_ai_ci', 'utf8mb4_0900_ai_ci', $sql);
    }
    return [$sql, basename($file['name']), $collationConversions];
}

$error = '';
$result = '';
$completed = false;

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

        [$sql, $filename, $collationConversions] = installer_validate_upload();

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

            // Send the original dump as one MySQL multi-statement request so
            // the server, not a PHP splitter, handles quoted SQL and comments.
            $db->multi_query($sql);
            do {
                if ($res = $db->store_result()) $res->free();
            } while ($db->more_results() && $db->next_result());
            $idRepair = installer_fix_auto_increment_ids($db);
            $bannerSlugRepair = installer_repair_banner_slug($db);
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            installer_mark_complete($db);
            $completed = true;
            $result = 'Backup ' . $filename . ' imported successfully into database ' . DB_NAME . '. Disable the installer now.';
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Private database installer</title>
  <style>
    body{font:16px/1.5 system-ui,sans-serif;max-width:760px;margin:40px auto;padding:0 18px;color:#172033}
    section{border:1px solid #ccd3df;border-radius:10px;padding:20px;margin:18px 0}
    label{display:block;font-weight:650;margin:12px 0 5px}
    input,button{font:inherit;padding:10px;max-width:100%}
    input[type=password],input[type=file]{width:95%}
    button{cursor:pointer;margin-top:14px}
    .notice{background:#fff4d6;padding:12px;border-radius:8px}
    .error{background:#ffe7e7;padding:12px;border-radius:8px;white-space:pre-wrap}
    .success{background:#e4f6e9;padding:12px;border-radius:8px;white-space:pre-wrap}
    .schema{overflow-x:auto}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #d9e0e8}
    .badge{font-weight:700}
    .ready{color:#16723b}
    .needs-fix{color:#a05a00}
    .version{display:inline-block;background:#e9e4ff;color:#4d32a6;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700}
  </style>
</head>
<body>
  <h1>Private database installer <span class="version">Schema repair v2</span></h1>
  <p class="notice">Use only while the site is private. A full restore permanently drops every table and view in the configured application database, then imports the backup. Other databases, including MySQL system databases, are not touched. Take a backup first if any current data matters.</p>
  <?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($result !== ''): ?><p class="success"><?= htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
  <section class="schema">
    <h2>Database schema status <span class="version">15 ID tables + banner slug</span></h2>
    <p>Green means ready. Orange means repair is needed. This status checks the current database without changing it.</p>
    <table>
      <thead><tr><th>Table / column</th><th>Definition</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($schemaStatus as $item): ?>
        <tr>
          <td><code><?= htmlspecialchars($item['table'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code></td>
          <td><?= htmlspecialchars($item['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td class="badge <?= $item['ok'] ? 'ready' : 'needs-fix' ?>"><?= $item['ok'] ? 'READY' : 'NEEDS REPAIR' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php if ($completed): ?>
    <p>Full restore is complete and remains locked. Upload the SQL schema and choose the repair action to fix missing tables, all listed integer IDs, and the banner slug.</p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>">
      <label for="token">Installer token</label>
      <input id="token" type="password" name="installer_token" required autocomplete="off">
      <label for="backup">SQL backup (.sql, maximum 100 MB)</label>
      <input id="backup" type="file" name="backup" accept=".sql,application/sql" required>
      <button type="submit" name="mode" value="repair">Repair missing tables, all IDs, and banner slug</button>
    </form>
  <?php else: ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>">
      <label for="token">Installer token</label>
      <input id="token" type="password" name="installer_token" required autocomplete="off">
      <label for="backup">SQL backup (.sql, maximum 100 MB)</label>
      <input id="backup" type="file" name="backup" accept=".sql,application/sql" required>
      <section>
        <h2>Repair missing tables</h2>
        <p>Creates only absent tables from the uploaded backup schema. Existing tables and rows are left alone; this does not import records.</p>
        <button type="submit" name="mode" value="repair">Repair missing tables, all IDs, and banner slug</button>
      </section>
      <section>
        <h2>Import the full backup</h2>
        <p>Empties the configured application database, then imports all tables and rows from the SQL dump.</p>
        <label for="confirm">Type EMPTY AND RESTORE to confirm</label>
        <input id="confirm" name="confirm_restore" autocomplete="off">
        <button type="submit" name="mode" value="restore">Import full backup</button>
      </section>
    </form>
  <?php endif; ?>
</body>
</html>

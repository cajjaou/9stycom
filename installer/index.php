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
        if ($completed) {
            throw new RuntimeException('The full restore has already completed. Disable and remove the installer.');
        }

        [$sql, $filename, $collationConversions] = installer_validate_upload();
        $mode = (string) ($_POST['mode'] ?? '');

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
            $result = $created
                ? 'Added missing tables: ' . implode(', ', $created) . '. No rows were imported.'
                : 'No missing tables were found. No rows were imported.';
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

            $statementNumber = 0;
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

            foreach (installer_sql_statements($sql) as $statement) {
                $statementNumber++;
                $db->multi_query($statement);
                do {
                    if ($res = $db->store_result()) $res->free();
                } while ($db->more_results() && $db->next_result());
            }
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            installer_mark_complete($db);
            $completed = true;
            $result = 'Backup ' . $filename . ' imported successfully into database ' . DB_NAME . '. Disable the installer now.';
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
  </style>
</head>
<body>
  <h1>Private database installer</h1>
  <p class="notice">Use only while the site is private. A full restore permanently drops every table and view in the configured application database, then imports the backup. Other databases, including MySQL system databases, are not touched. Take a backup first if any current data matters.</p>
  <?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($result !== ''): ?><p class="success"><?= htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($completed): ?>
    <p>Full restore is already complete. Set <code>INSTALLER_ENABLED=0</code> and redeploy.</p>
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
        <button type="submit" name="mode" value="repair">Create missing tables</button>
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

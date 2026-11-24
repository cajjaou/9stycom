# VPS deployment notes

## Requirements

- Apache with `mod_rewrite` and `AllowOverride All`, or equivalent rewrite and access rules in the virtual host.
- PHP 8+ with PDO MySQL, cURL, Fileinfo, and the extensions used by the site enabled.
- MySQL/MariaDB. Use a dedicated database account with access only to this site's database.

## Configuration

Set these variables in the PHP-FPM pool or the web service environment (do not put them in a public `.env` file):

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `JANABIO_API_KEY`, `JANABIO_API_SECRET` (or configure the values in the existing admin settings)

The database account needs schema changes while the current application performs automatic migrations at startup. For production hardening, run migrations deliberately and then remove DDL privileges from the runtime account.

## Before opening the site

1. Set the virtual host document root to this project directory and enable HTTPS.
2. Configure PHP with `display_errors=Off` and server-side error logging. Keep log files outside the document root.
3. Make `uploads/` and `cache/` writable by the PHP-FPM user only; do not make the whole project writable by the web server.
4. Verify the web server enforces the private-directory and upload-script rules. The supplied `.htaccess` rules apply to Apache; Nginx needs equivalent location rules in its server configuration.
5. Rotate any database or JanaBio credentials that were previously stored in source files, then set the new values in the service environment.
6. Back up the database and uploads, and keep the backup outside the web root.

Do not deploy `debug.log` or local backups. Keep the `cache/` directory because the product feed uses it at runtime.

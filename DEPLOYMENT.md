# VPS deployment notes

## Requirements

- Apache with `mod_rewrite` and `AllowOverride All`, or equivalent rewrite and access rules in the virtual host.
- PHP 8+ with PDO MySQL, cURL, Fileinfo, and the extensions used by the site enabled.
- MySQL/MariaDB. Use a dedicated database account with access only to this site's database.

## Coolify / Docker deployment

This repository includes a `Dockerfile` that installs the PHP extensions required by the app, including `pdo_mysql`. For a Coolify Git application, set the build strategy to **Dockerfile**, Dockerfile path to `/Dockerfile`, base directory to `/`, and exposed port to `80`. Add the database and app environment variables to the PHP application resource, then redeploy. The MySQL service's internal hostname and credentials belong in the PHP app's environment; do not use `localhost` when MySQL runs in another container.

## CDN subdomain in Coolify

Keep the PHP site, MySQL, and CDN resources in the same Coolify project and on the same deployment server. A project is just their grouping; the CDN should be a separate Nginx application resource. Create that resource from this repository using build strategy **Dockerfile**, base directory `/`, Dockerfile path `/cdn/Dockerfile`, and exposed port `80`. Assign `https://cdn.9sty.com` to the Nginx resource only. Point DNS record `cdn` (A) to the VPS public IP; Coolify's proxy routes the hostname to its resource and provisions HTTPS.

Create one host directory for uploads, for example `/srv/9sty/uploads`. Mount that same host directory into the PHP resource at `/var/www/site-uploads` and into the CDN resource at `/srv/cdn/uploads`. Both resources must run on the same server; local bind mounts are not shared across servers. Make the directory writable by PHP's `www-data` user (UID 33) and readable by Nginx. Copy existing image files into this directory before switching, or old database image filenames will point to missing files.

Set these variables on the PHP application resource:

- `UPLOAD_DIR=/var/www/site-uploads`
- `CDN_BASE_URL=https://cdn.9sty.com`

Post images and other uploaded media are then saved in the mounted directory and served as `https://cdn.9sty.com/uploads/<filename>`. The Nginx config only serves files under `/uploads/` and rejects script extensions.

`app.9sty.com` does not need its own Coolify project. If it should show exactly the same site, it can be an additional domain on the PHP resource. The current admin panel is at `/dashbarod`; assigning `app.9sty.com` alone will not make that path the subdomain's homepage. Use a separate application resource in the same project only if `app.9sty.com` is meant to run a distinct app or admin entry point.

## Configuration

Set these variables in the PHP-FPM pool or the web service environment (do not put them in a public `.env` file):

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `DB_PORT` (optional; defaults to `3306`), `DB_CHARSET` (optional; defaults to `utf8mb4`)
- `UPLOAD_DIR` (optional; defaults to the app's local `uploads/` directory), `CDN_BASE_URL` (optional; defaults to `https://cdn.9sty.com`)
- `JANABIO_API_KEY`, `JANABIO_API_SECRET` (or configure the values in the existing admin settings)
- `APP_DEBUG` (set to `1` only while the site is private for troubleshooting; remove or set to `0` before making it public)
- `INSTALLER_ENABLED=1` and `INSTALLER_TOKEN` (temporary only; required to use `/installer/`)

The database account needs schema changes while the current application performs automatic migrations at startup. For production hardening, run migrations deliberately and then remove DDL privileges from the runtime account.

## Temporary database installer

Keep the domain private. Set `APP_DEBUG=1`, `INSTALLER_ENABLED=1`, and `INSTALLER_TOKEN` to a random value with at least 32 characters, then redeploy. Open `/installer/`, enter the token, and upload the SQL backup (up to 100 MB). **Create missing tables** imports schema only and preserves existing rows; **Import full backup** drops every table and view inside the configured application database, then imports both schema and data. It refuses MySQL system database names and asks for the exact confirmation `EMPTY AND RESTORE`. MariaDB's `utf8mb4_uca1400_ai_ci` is translated to MySQL's `utf8mb4_0900_ai_ci` during import. The full restore writes a completion marker and disables further installer actions. After success, set `INSTALLER_ENABLED=0` (or remove the token) and redeploy. The `.sql` backup is excluded from Git and Docker build context.

## Before opening the site

1. Set the virtual host document root to this project directory and enable HTTPS.
2. Configure PHP with `display_errors=Off` and server-side error logging. Keep log files outside the document root.
3. Make `uploads/` and `cache/` writable by the PHP-FPM user only; do not make the whole project writable by the web server.
4. Verify the web server enforces the private-directory and upload-script rules. The supplied `.htaccess` rules apply to Apache; Nginx needs equivalent location rules in its server configuration.
5. Rotate any database or JanaBio credentials that were previously stored in source files, then set the new values in the service environment.
6. Back up the database and uploads, and keep the backup outside the web root.

Do not deploy `debug.log` or local backups. Keep the `cache/` directory because the product feed uses it at runtime.

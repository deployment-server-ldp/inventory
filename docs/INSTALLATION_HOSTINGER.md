# Installation & Hostinger Deployment Guide

SPIMS runs on any standard PHP + MySQL host. It needs **no** Node.js, Docker, Redis, cron job or
background worker. All CSS/JS libraries are already bundled in `public/vendor`.

## 1. Requirements

| Item | Requirement |
|---|---|
| PHP | 8.2, 8.3 or 8.4 (Hostinger: hPanel → Advanced → PHP Configuration) |
| PHP extensions | pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, gd (with WebP), zip, bcmath (optional) |
| Database | MySQL 8 or MariaDB 10.4+ (InnoDB, utf8mb4) |
| Disk | ~150 MB for code + space for part images |

All of these are available on Hostinger Premium / Business / Cloud shared plans.

---

## 2. Fresh installation on a generic server (with SSH)

```bash
git clone <your-repo-url> spims && cd spims        # or upload & extract the release ZIP
composer install --no-dev --optimize-autoloader     # skip if the ZIP already contains vendor/
cp .env.example .env
php artisan key:generate
# edit .env → DB_*, APP_URL, APP_TIMEZONE (see §4)
php artisan migrate --force
php artisan db:seed --force                         # roles, permissions, M-1…M-20, operations, units, categories
php artisan app:create-admin                        # interactive — creates the first Super Admin
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
Point the web server's document root at the `public/` directory.

---

## 3. Hostinger shared hosting — step by step

### 3.1 Create the database
1. hPanel → **Databases → MySQL Databases**.
2. Create a database and user (e.g. `u123456789_spims` / `u123456789_spims`) with a strong password.
3. Note the host — on Hostinger it is usually `localhost` (sometimes a `srvXXX` hostname shown in hPanel).

### 3.2 Set PHP version
hPanel → **Advanced → PHP Configuration** → choose **PHP 8.2 or newer**. On the *PHP extensions*
tab make sure `gd`, `fileinfo`, `zip`, `pdo_mysql`, `mbstring` are ticked.

### 3.3 Upload the code

You need the `vendor/` folder. Either:
* **With SSH (recommended):** hPanel → **Advanced → SSH Access** → enable, then connect and run
  `composer install --no-dev --optimize-autoloader` inside the project (Composer is pre-installed on Hostinger), **or**
* **Without SSH:** on your own PC run `bash deploy/build-release.sh`; it produces `spims-release-YYYYMMDD.zip`
  that already contains `vendor/`. Upload that ZIP with hPanel **File Manager** and extract it.

### 3.4 Choose a document-root layout

Laravel must only expose its `public/` folder. On Hostinger the web root is fixed to `public_html`, so use **one** of:

**Option A — app outside `public_html` (most secure, recommended)**
```
/home/u123456789/domains/your-domain.com/
├── spims/            ← whole project here (app, vendor, storage, .env …)
└── public_html/      ← contents of spims/public/ copied here
```
1. Upload/extract the project to `domains/your-domain.com/spims`.
2. Copy **everything inside** `spims/public/` (including the hidden `.htaccess`, `css/`, `js/`, `vendor/`, `favicon.svg`, `index.php`) into `public_html/`.
3. Edit `public_html/index.php` and change the three paths to point one level up into `spims`:
   ```php
   if (file_exists($maintenance = __DIR__.'/../spims/storage/framework/maintenance.php')) { require $maintenance; }
   require __DIR__.'/../spims/vendor/autoload.php';
   $app = require_once __DIR__.'/../spims/bootstrap/app.php';
   ```
   With SSH you can instead replace `public_html` by a symlink:
   `rm -rf public_html && ln -s spims/public public_html`.
4. After every future update, re-copy `spims/public/css`, `spims/public/js` and `spims/public/vendor` if they changed (not needed with the symlink).

**Option B — whole project inside `public_html`**
1. Upload/extract the project directly into `public_html/`.
2. Copy `deploy/public_html.htaccess` to `public_html/.htaccess`.
   It rewrites every request to `public/` and **blocks** `.env`, `storage/`, `vendor/` and other application files.
3. Verify: `https://your-domain.com/.env` and `https://your-domain.com/storage/logs/laravel.log` must return **403/404**.

### 3.5 Configure `.env`
Copy `.env.example` to `.env` (File Manager → *Copy*, then *Edit*) and set:

```ini
APP_NAME="SPIMS - Spare Parts Manufacturing & Inventory"
APP_ENV=production
APP_DEBUG=false                 # NEVER true in production
APP_URL=https://your-domain.com
APP_TIMEZONE=Asia/Karachi       # your business timezone

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_spims
DB_USERNAME=u123456789_spims
DB_PASSWORD=********

SESSION_SECURE_COOKIE=true      # requires HTTPS (enable free SSL in hPanel → Security → SSL)
SPIMS_SETUP_TOKEN=              # only for the web setup, see §3.7
SPIMS_IMAGE_MAX_KB=4096
```
Generate `APP_KEY`: with SSH `php artisan key:generate`. Without SSH, generate a key locally
(`php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`) and paste it as `APP_KEY=base64:...`.

### 3.6 Create tables and base data
With SSH:
```bash
cd ~/domains/your-domain.com/spims
php artisan migrate --force
php artisan db:seed --force
```
Without SSH: create the schema on your own PC (`php artisan migrate && php artisan db:seed` against a local MySQL),
export it with `mysqldump --no-tablespaces local_db > spims-schema.sql`, and import that file through
hPanel → **Databases → phpMyAdmin → Import**. (SSH is included free on Hostinger plans that support Laravel and is simpler.)

### 3.7 Create the first Super Admin (secure)
Credentials are never hard-coded. Choose one:

* **SSH:** `php artisan app:create-admin` — prompts for name, username and password (password is typed hidden, never stored in shell history).
* **Web (no SSH):**
  1. Put a long random value in `.env`: `SPIMS_SETUP_TOKEN=Xq8…(32+ chars)`.
  2. Open `https://your-domain.com/setup`, enter that token and the admin details.
  3. The page only works while the users table is empty; afterwards it returns 404.
  4. **Remove** `SPIMS_SETUP_TOKEN` from `.env` (the System Health page warns until you do).

Then sign in, open **Admin → Users** and create the other accounts (CNC User, Import Inventory User, Combined User …).

### 3.8 Storage permissions
Folders that must be writable by PHP (Hostinger's default 755 for folders / 644 for files is correct):
```
storage/            (and all sub-folders: app/private/parts, framework/{cache,sessions,views}, logs)
bootstrap/cache/
```
With SSH: `chmod -R 775 storage bootstrap/cache`. Part images are stored in `storage/app/private/parts`
and served only to signed-in users through the application — **no `storage:link` symlink is required**.

### 3.9 Optimise
```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
Re-run after every `.env` change (or run `php artisan optimize:clear`).

### 3.10 Verify
* `https://your-domain.com/health` → `{"status":"ok"}`
* Sign in → **Admin → System Health**: every check green, "Stock ledger reconciliation" OK.

---

## 4. Updating to a new version
```bash
php artisan down
git pull            # or upload the new release ZIP over the old files (keep .env and storage/)
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # adds any new permissions, keeps your edits
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```
Always take a backup first (see `BACKUP_RESTORE.md`).

---

## 5. Sample / test data (never on the live database)
```bash
php artisan db:seed --class=DemoDataSeeder
```
Creates demo operators, machinery models, suppliers, 10 CNC parts, 12 imported products (with generated images),
six weeks of production, completions, IN/OUT movements, two assemblies and one demo user per role
(random passwords printed once). Use it on a staging copy only. It refuses to run in `APP_ENV=production`
unless you explicitly confirm.

---

## 6. Troubleshooting
| Symptom | Fix |
|---|---|
| 500 error, blank page | Check `storage/logs/laravel-YYYY-MM-DD.log` (or Admin → System Health → Error log). Most often `.env` DB values or permissions. |
| "No application encryption key" | Set `APP_KEY` (§3.5). |
| CSS missing / links contain `/public/` | Option A: copy `public/*` again. Option B: ensure `APP_URL` is correct and run `php artisan config:cache`. |
| Images do not upload | PHP `upload_max_filesize` / `post_max_size` ≥ 8M (hPanel → PHP Configuration), `gd` enabled, `storage/` writable. |
| Session expires immediately | `SESSION_SECURE_COOKIE=true` requires HTTPS; enable SSL or set it to `false` for HTTP testing only. |
| Changes to `.env` ignored | `php artisan config:clear` (config is cached). |

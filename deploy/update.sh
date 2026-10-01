#!/usr/bin/env bash
# =============================================================================
# SPIMS — safe in-place update (SSH). Run INSIDE the existing app folder:
#     cd ~/domains/<your-domain>/public_html/<subdomain-folder>   # your app folder
#     bash deploy/update.sh
#
# It NEVER deletes the folder, .env, storage/ (part images, logs) or any data.
#   1. backs up the database + .env into storage/app/backups/
#   2. maintenance mode on
#   3. pulls the new code (git) — or uses files you already uploaded/extracted
#   4. composer install (if composer is available)
#   5. runs ONLY new migrations (destructive commands are blocked in production)
#   6. adds new permissions, rebuilds caches, maintenance mode off
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."
APP_DIR="$(pwd)"

[ -f .env ] || { echo "ERROR: .env not found in $APP_DIR — refusing to continue (is this the live app folder?)"; exit 1; }
[ -f artisan ] || { echo "ERROR: artisan not found — run this from the SPIMS folder"; exit 1; }

env_val() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="storage/app/backups"
mkdir -p "$BACKUP_DIR"

echo "==> 1/6 Backup"
cp .env "$BACKUP_DIR/env-$STAMP"
if command -v mysqldump >/dev/null 2>&1; then
    MYSQL_PWD="$(env_val DB_PASSWORD)" mysqldump --single-transaction --no-tablespaces \
        -h "$(env_val DB_HOST)" -P "$(env_val DB_PORT)" -u "$(env_val DB_USERNAME)" "$(env_val DB_DATABASE)" \
        | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"
    echo "    database → $BACKUP_DIR/db-$STAMP.sql.gz"
else
    echo "    mysqldump not available — export the database in phpMyAdmin before continuing!"
    read -r -p "    Continue without a database backup? [y/N] " ok; [ "$ok" = "y" ] || exit 1
fi
# keep the 10 most recent backups of each kind
ls -1t "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
ls -1t "$BACKUP_DIR"/env-* 2>/dev/null | tail -n +11 | xargs -r rm -f

echo "==> 2/6 Maintenance mode"
php artisan down --retry=30 || true
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> 3/6 Code"
if [ -d .git ]; then
    git pull --ff-only
else
    echo "    no git repository — using the files you uploaded/extracted into this folder"
fi

echo "==> 4/6 Dependencies"
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    [ -f vendor/autoload.php ] || { echo "ERROR: vendor/ missing and composer not available — upload the release ZIP"; exit 1; }
    echo "    composer not found — using the uploaded vendor/ folder"
fi

echo "==> 5/6 Database (new migrations only)"
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force

echo "==> 6/6 Caches"
mkdir -p storage/app/private/parts storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan stock:verify || echo "WARNING: stock ledger mismatch reported above — check Admin → System Health"

php artisan up
trap - EXIT
echo "Update complete: version $(cat VERSION 2>/dev/null || echo '?'). Backup kept in $BACKUP_DIR."

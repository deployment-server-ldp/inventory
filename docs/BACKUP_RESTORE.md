# Backup & Restore

Two things hold all business data:

1. **The MySQL database** — every part, production entry, ledger transaction, user and audit log.
2. **`storage/app/private/parts/`** — the part images.

`.env` should also be kept (securely, it contains the DB password and `APP_KEY`). Without the original
`APP_KEY` encrypted sessions are lost (harmless) but nothing else in the database depends on it.

## 1. Automatic backups on Hostinger
hPanel → **Files → Backups**. Hostinger keeps weekly (Premium) or daily (Business/Cloud) backups of files and
databases. Use *Database backups* to download or restore a specific day. Treat these as a safety net and still
keep your own off-site copies.

## 2. Manual backup (SSH)
```bash
cd ~/domains/your-domain.com/spims
STAMP=$(date +%Y%m%d-%H%M)
mkdir -p ~/backups
# consistent InnoDB snapshot without locking users out
mysqldump --single-transaction --routines --triggers --no-tablespaces \
  -h localhost -u DB_USER -p DB_NAME | gzip > ~/backups/spims-db-$STAMP.sql.gz
tar -czf ~/backups/spims-images-$STAMP.tar.gz -C storage/app/private parts
cp .env ~/backups/spims-env-$STAMP
```
Download the files from `~/backups` (File Manager or SFTP) to an off-site location. Recommended: daily DB,
weekly images, and before every update.

## 3. Manual backup (no SSH)
* Database: hPanel → **Databases → phpMyAdmin** → select the database → **Export** → *Quick*, format SQL → Go.
* Images: File Manager → right-click `spims/storage/app/private/parts` → **Compress** → download the archive.

## 4. Restore
1. Put the site in maintenance mode: `php artisan down` (or rename `public_html/index.php` temporarily).
2. Restore the database into an **empty** database:
   ```bash
   gunzip < spims-db-YYYYmmdd-HHMM.sql.gz | mysql -h localhost -u DB_USER -p DB_NAME
   ```
   or phpMyAdmin → **Import** the `.sql` file.
3. Restore images:
   ```bash
   tar -xzf spims-images-YYYYmmdd-HHMM.tar.gz -C storage/app/private
   ```
4. Restore `.env` if the server is new; then
   `php artisan optimize:clear && php artisan migrate --force` (applies any newer migrations) and `php artisan up`.
5. Verify integrity: `php artisan stock:verify` (or Admin → System Health). It must report that every balance
   matches its ledger.

## 5. Test your backups
At least quarterly, restore the latest backup into a separate staging database and run `php artisan stock:verify`
against it. A backup that has never been restored is not a backup.

## 6. What never to do
* Do not "fix" stock by editing `spare_parts.current_stock` in phpMyAdmin — use **Stock Adjustments** in the
  application so the change is in the ledger and audit log. `stock:verify` will flag any manual edit.
* Do not delete rows from ledger or production tables; use reversals / cancellations.

# Subdomain deployment & safe updates (Hostinger)

> **Golden rule — never delete the app folder.** Install once into the subdomain folder; every later change is
> uploaded/pulled **into the same folder** and then applied. The folder holds things that exist only on the server:
>
> | Path | What it is | If deleted |
> |---|---|---|
> | `.env` | DB password, `APP_KEY`, settings | Site stops working |
> | `storage/app/private/parts/` | **All part / product images** | Images lost permanently |
> | `storage/app/backups/` | Automatic backups made by `deploy/update.sh` | Backups lost |
> | `storage/logs/` | Error logs | History lost |
> | Database (MySQL) | All parts, stock, production, users, audit log | Everything lost |
>
> Urdu: *Subdomain wala folder kabhi delete na karein. Har update usi folder mein upload/pull karein aur phir
> "Apply updates" chalayein. `.env` aur `storage` folder ko kabhi replace ya delete na karein.*

---

## 1. One-time setup of the subdomain

1. hPanel → **Domains → Subdomains** → create e.g. `inventory.your-domain.com`.
   Note the folder hPanel shows (typically `domains/your-domain.com/public_html/inventory`). This is your
   **app folder** for life.
2. hPanel → **Databases → MySQL Databases** → create an empty database + user.
3. Put the code into the app folder (pick one):
   * **Git + SSH (best for frequent changes):** delete only hPanel's placeholder file (`default.php` / `index.html`)
     if present, then
     ```bash
     cd ~/domains/your-domain.com/public_html/inventory
     git clone https://github.com/<owner>/inventory.git .
     composer install --no-dev --optimize-autoloader
     ```
   * **ZIP (no SSH):** run `bash deploy/build-release.sh` on a PC, upload the ZIP into the app folder with
     File Manager and **Extract** there.
4. The project's root **`.htaccess`** (included) serves the site from `public/` and blocks `.env`, `storage/`,
   `vendor/` etc. — so the whole project can live directly in the subdomain folder, and
   `your-domain.com/inventory/.env` is also blocked. No document-root change is needed.
5. Create `.env` from `.env.example` (File Manager → Copy → Edit): `APP_URL=https://inventory.your-domain.com`,
   `APP_ENV=production`, `APP_DEBUG=false`, DB details, `APP_KEY` (see INSTALLATION_HOSTINGER.md §3.5).
6. Database: `php artisan migrate --force && php artisan db:seed --force` (SSH) **or** import
   `database/sql/spims_install.sql` in phpMyAdmin.
7. First admin: `php artisan app:create-admin` (SSH) **or** `SPIMS_SETUP_TOKEN` + `https://inventory.your-domain.com/setup`
   (then remove the token).
8. hPanel → **Security → SSL** → enable SSL for the subdomain.

---

## 2. Applying changes later (the folder stays)

### A. With SSH + Git (recommended)
```bash
cd ~/domains/your-domain.com/public_html/inventory
bash deploy/update.sh
```
The script: checks it is in the live folder (`.env` must exist) → backs up DB + `.env` to `storage/app/backups/`
(keeps the last 10) → maintenance mode → `git pull` → `composer install` → **only new** migrations →
new permissions → rebuilds caches → `stock:verify` → site back up. It never deletes the folder, `.env`,
images or data.

> hPanel **Advanced → Git** auto-deploy also works for pulling code into the same folder, but it does not run
> Composer or migrations: afterwards open **Admin → System Health → Apply updates** (and run
> `composer install` over SSH whenever `composer.lock` changed).

### B. Without SSH (ZIP upload)
1. **Backup:** phpMyAdmin → Export (SQL). Optionally compress & download `storage/app/private/parts`.
2. Build the new release ZIP (`bash deploy/build-release.sh`) and upload it **into the existing app folder**.
3. File Manager → **Extract** into the same folder → when asked, **overwrite / replace existing files**.
   The ZIP contains no `.env` and no images, so your settings and pictures are untouched.
   **Do not** delete the folder first and **do not** delete files that are not in the ZIP.
4. Sign in as Super Admin → **Admin → System Health** → check the new *Version* number → click **Apply updates**.
   It runs only the new database changes, syncs permissions and clears caches.

### What never to do
| Don't | Why |
|---|---|
| Delete / rename the app folder to "re-install" | Loses images, `.env`, backups |
| Upload a fresh copy and overwrite `.env` | Breaks DB connection and `APP_KEY` |
| Re-import `spims_install.sql` into the live database | It **drops all tables** (only for first install) |
| Run `migrate:fresh`, `migrate:reset`, `db:wipe` | Blocked automatically in `APP_ENV=production` — they would erase all data |
| Edit stock numbers in phpMyAdmin | Ledger mismatch; use Stock Adjustments |

---

## 3. Checking an update worked
* **Admin → System Health**: *Version* shows the new number, *Application updates* says "Database up to date",
  all checks green, ledger reconciliation OK.
* **Admin → Activity Log**: entry `system.updated` with the version and migrations applied.
* If something fails: the database and images are unchanged; check **System Health → Error log**, fix, and run the
  update again. To roll back, restore the backup from `storage/app/backups/` (see BACKUP_RESTORE.md).

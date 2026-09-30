# Deployment Checklist

Tick every item before go-live.

## Server & configuration
- [ ] PHP ≥ 8.2 with pdo_mysql, gd (WebP), fileinfo, zip, mbstring, xml
- [ ] SSL certificate active; site opens on `https://`
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL` (https), `APP_TIMEZONE`
- [ ] `.env`: `APP_KEY` generated and backed up securely
- [ ] `.env`: `SESSION_SECURE_COOKIE=true`
- [ ] `.env`: DB credentials use a dedicated DB user with a strong password
- [ ] Only `public/` is web-accessible — `https://domain/.env`, `/storage/logs/…`, `/vendor/…` return 403/404
- [ ] `storage/` and `bootstrap/cache/` writable
- [ ] PHP `upload_max_filesize` and `post_max_size` ≥ `SPIMS_IMAGE_MAX_KB`

## Database & data
- [ ] `php artisan migrate --force` completed
- [ ] `php artisan db:seed --force` completed (roles, permissions, M-1…M-20, operations, units, categories)
- [ ] **No demo data** in the live database (`DemoDataSeeder` NOT run; no `demo_*` users)
- [ ] First Super Admin created (`app:create-admin` or `/setup`)
- [ ] `SPIMS_SETUP_TOKEN` removed from `.env` afterwards
- [ ] Machines, operators, operations, categories, units, machinery models and suppliers reviewed in **Settings**
- [ ] Opening stock entered per part (part creation) — or via adjustments with reason "Opening balance migration"
- [ ] Part images uploaded for every part (mandatory)

## Users & security
- [ ] Individual accounts created for every person (no shared logins)
- [ ] Roles reviewed in **Admin → Roles & Permissions** (who may approve completions, reverse, adjust)
- [ ] At least two Super Admin accounts exist (so one can reset the other)
- [ ] Temporary passwords changed on first login (enforced automatically)

## Operations
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `/health` returns `{"status":"ok"}`
- [ ] **Admin → System Health**: all checks green, ledger reconciliation OK
- [ ] Backup schedule configured (Hostinger + own off-site copies) and one restore tested
- [ ] Users trained with `docs/USER_GUIDE.md`

## Smoke test after go-live
- [ ] Create a test CNC part with image → production entry → completion → approve → stock increases by accepted qty only
- [ ] Imported IN 10 → OUT 3 → balance 7 → OUT 20 refused
- [ ] Reverse the test transactions (or adjust with reason "go-live test") and deactivate the test parts
- [ ] Check the activity log shows all of the above with your user name

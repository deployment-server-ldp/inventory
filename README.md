# SPIMS — Spare Parts Manufacturing & Inventory Management System

Production-ready Laravel application for a cigarette-machinery spare-parts business that
**manufactures parts on CNC machines** and **imports parts (pneumatic, electrical, sensors…) from Dubai**.
Two independent inventories, one consolidated dashboard.

| Module | What it does |
|---|---|
| **CNC Manufacturing** | Daily production entries per machine / part / operation / operator, running-job tracking, operation progress (WIP), machine history & monthly sheets, QC completion workflow — the *only* way production enters CNC stock |
| **CNC Inventory** | Finished-goods stock, ledger (opening, receipts, issues, adjustments, reversals), stock issue |
| **Imported Inventory** | Product master with images, Inventory IN / OUT, reversals, stock & ledger, dashboard |
| **Machine Assemblies** | Bill of imported parts per machine being assembled; issues create normal OUT transactions (no double deduction) |
| **Overall Dashboard** | KPIs, alerts, charts and recent activity for both streams, global filters, click-through |
| **Reports Center** | 16 reports, search/sort/paginate, Excel / CSV / PDF export respecting filters and permissions |
| **Admin** | Users, roles & granular permissions (server-enforced), activity/audit log, system health, error log, master data settings |

## Stack
Laravel 12 · PHP ≥ 8.2 · MySQL / MariaDB · Blade + Bootstrap 5 · Chart.js · Tom Select (all vendored — **no Node build**) ·
PhpSpreadsheet · DomPDF. Runs on Hostinger shared hosting: no Redis, no queue worker, no cron.

## Quick start (local)
```bash
composer install
cp .env.example .env && php artisan key:generate      # set DB_* and APP_ENV=local, APP_DEBUG=true
php artisan migrate --seed                             # schema + roles/permissions + base master data
php artisan app:create-admin                           # first Super Admin (interactive)
# No SSH? Import database/sql/spims_install.sql in phpMyAdmin instead of migrate --seed.
php artisan db:seed --class=DemoDataSeeder             # OPTIONAL sample data + demo users (local/staging only)
php artisan serve
```

## Documentation
| Document | Contents |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Business workflow, database design & relationships, stock-accounting rules, permissions & navigation, consistency risks, assumptions |
| [docs/INSTALLATION_HOSTINGER.md](docs/INSTALLATION_HOSTINGER.md) | Fresh install, Hostinger step-by-step (DB, PHP, document root, `.env`, permissions, first admin), updates, troubleshooting |
| [docs/SUBDOMAIN_DEPLOY_AND_UPDATES.md](docs/SUBDOMAIN_DEPLOY_AND_UPDATES.md) | Subdomain install and **safe updates without ever deleting the app folder** (SSH script or ZIP + "Apply updates" button) |
| [docs/DEPLOYMENT_CHECKLIST.md](docs/DEPLOYMENT_CHECKLIST.md) | Go-live checklist |
| [docs/BACKUP_RESTORE.md](docs/BACKUP_RESTORE.md) | Backup & restore of database and images |
| [docs/USER_GUIDE.md](docs/USER_GUIDE.md) | Guide for Super Admin, CNC User, Import Inventory User, Combined User |
| [docs/TESTING.md](docs/TESTING.md) | Test suite and mapping of the 12 acceptance scenarios |

## Key guarantees
* Stock changes only through `App\Services\StockService` — row-locked, atomic, never negative, ledger row with balance for every movement.
* Intermediate CNC operations never inflate stock; only QC-approved completions of final-operation output do.
* CNC and imported ledgers are separate tables; posting a part to the wrong ledger is refused.
* Double submissions are idempotent; nothing is deleted — corrections are reversals/adjustments with reason & user.
* `php artisan stock:verify` reconciles every balance against its ledger.

## Useful commands
```bash
php artisan app:create-admin     # create a Super Admin
php artisan stock:verify         # ledger reconciliation (read-only)
php artisan test                 # full test suite (needs the spims_test database — see docs/TESTING.md)
bash deploy/build-release.sh     # ZIP with vendor/ for hosts without Composer/SSH
bash deploy/update.sh            # safe in-place update on the server (backup, pull, new migrations only)
```

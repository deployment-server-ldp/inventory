# Testing

Tests run against a dedicated MySQL/MariaDB database (the business rules use MySQL row locks and date functions).

```bash
mysql -u root -e "CREATE DATABASE spims_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON spims_test.* TO 'spims'@'localhost';"
composer install            # dev dependencies included
php artisan test
```
`phpunit.xml` sets `DB_DATABASE=spims_test`; credentials come from `.env`. **Never point tests at the live database** —
every test refreshes the schema.

## Acceptance scenarios → tests

| # | Scenario | Test |
|---|---|---|
| 1 | CNC part with mandatory image, selected in production entry | `CncWorkflowTest::test_cnc_part_requires_an_image_and_can_be_selected_in_production_entry` |
| 2 | Multiple operations without double-counting (100/80/60 → 60 eligible, 0 stock) | `CncWorkflowTest::test_multiple_operations_do_not_double_count_stock` |
| 3 | Monthly production by machine + export (xlsx/csv/pdf) | `CncWorkflowTest::test_monthly_production_sheet_per_machine_and_export` |
| 4 | Completion approval adds exactly the accepted quantity | `CncWorkflowTest::test_approved_completion_adds_exact_accepted_quantity_to_cnc_stock` |
| 5–7 | Imported product with image, IN 100, OUT 25 → 75, OUT 80 rejected | `ImportedWorkflowTest::test_receive_100_issue_25_then_reject_80` |
| 8 | Assembly consumption deducts once (assembly page + OUT form), reversal restores | `ImportedWorkflowTest::test_machine_assembly_consumption_deducts_stock_once` |
| 9 | CNC user blocked from import pages/admin (GET **and** POST) | `PermissionTest::test_cnc_user_cannot_access_import_pages_or_admin` |
| 10 | Combined user accesses both modules | `PermissionTest::test_combined_user_can_access_both_operational_modules` |
| 11 | Stock changes & reversals in the audit log (with before/after values) | `AuditAndDashboardTest::test_stock_changes_and_reversals_appear_in_audit_log` |
| 12 | Dashboard totals equal the ledger | `AuditAndDashboardTest::test_dashboard_totals_match_ledger` |

Additional coverage: first-admin setup token & one-time use, login/logout/failed-login logging, inactive users,
forced password change, security headers, authenticated image serving, idempotent (double) submissions, decimal vs
whole-number units, reversal rules (no double reversal, no negative stock), running jobs crossing midnight, inactive
machines, CNC/imported separation, granular per-user permissions, role editing, quick-create JSON, and a smoke test
that renders ~130 pages/exports with demo data (`PageSmokeTest`).

## Concurrency check (manual)
Row locking was verified with three separate PHP processes issuing 60 units each from a stock of 100 at the same
instant: exactly one succeeded (balance 40), two were refused, and `php artisan stock:verify` reported OK.

## Ledger reconciliation
`php artisan stock:verify` (also on Admin → System Health) recomputes every balance from its ledger and flags any
difference or any ledger row that references a part of the other inventory.

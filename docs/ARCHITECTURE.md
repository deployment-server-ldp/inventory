# Architecture — Spare Parts Manufacturing & Inventory Management System (SPIMS)

This document is the design baseline for the application. It records the business workflow,
the database design, the stock-accounting rules, permissions/navigation, and the consistency
risks that the implementation was built to prevent. Every assumption that required a business
decision is listed in **§9 Assumptions & configurable decisions**.

---

## 1. Business workflow

```
                         ┌───────────────────────────┐
                         │      Main Dashboard       │
                         │ overall stock, production │
                         │         & alerts          │
                         └─────────────┬─────────────┘
              ┌────────────────────────┴───────────────────────┐
┌─────────────▼─────────────┐                   ┌───────────────▼──────────────┐
│     CNC Manufacturing     │                   │      Imported Inventory      │
│ production entries,       │                   │ Dubai imports, IN / OUT,     │
│ machine history,          │                   │ machine assembly consumption │
│ completion → finished stk │                   │                              │
└───────────────────────────┘                   └──────────────────────────────┘
┌───────────────────────────┐                   ┌──────────────────────────────┐
│   Reports & Analytics     │                   │         Admin Panel          │
│   Excel / CSV / PDF       │                   │ users, roles, settings, logs │
└───────────────────────────┘                   └──────────────────────────────┘
```

### 1.1 CNC stream (locally manufactured parts)

1. **Part master** — a CNC part is registered with SKU, mandatory primary image, category, unit,
   compatible machinery models, minimum stock and its **final operation** (e.g. a part that
   needs 3 operations has final operation = "3rd Operation").
2. **Production entry** — operators/supervisors record work: date, CNC machine, part, target
   machinery model, operation, start/end time, quantity, operator, remarks.
   * An entry may be opened as **Running** (start time only, no end time). This is what drives
     the "machines currently running" / "parts in production" figures on the live dashboard.
     When the job stops the user clicks **Finish** and records end time and quantity.
   * Any number of entries per day / machine / part / operation are allowed (partial production,
     shifts, multiple operators — one entry per operator keeps operator totals additive).
3. **Operation progress** — quantities are tracked per operation. They are *work progress*, not
   stock. Example: 1st op 100, 2nd op 80, 3rd op 60 → WIP view shows those three figures;
   **only 60 are eligible for completion** (if 3rd is the final operation).
4. **Completion / quality acceptance** — a user submits a completion for a part (inspected
   qty = accepted + rejected). The quantity inspected cannot exceed the part's
   *awaiting-completion pool* (final-operation output − quantities already submitted).
   An authorised approver (`cnc.completion.approve`) approves it; **only on approval** is the
   accepted quantity posted to the CNC stock ledger as a *production receipt*.
5. **Finished-goods stock** — CNC stock is changed only by: opening stock, approved
   completions, stock issues (dispatch/consumption), adjustments (with mandatory reason) and
   reversals.

### 1.2 Imported stream (Dubai / other suppliers)

1. **Product master** — SKU, mandatory image, category (Pneumatic, Electrical, …), brand,
   model/part number, spec, unit, supplier, minimum stock, unit cost & currency.
2. **Inventory IN** — receipts increase stock; every IN gets a unique reference number
   (`IMP-IN-2026-000001`) and a permanent ledger row.
3. **Inventory OUT** — issues decrease stock; the available stock is shown on the form and
   re-validated on the server under a row lock. Stock can never become negative.
4. **Machine assembly** — a project (machine being assembled) lists the imported parts it
   needs (planned quantities). Issuing a part to an assembly *creates a normal OUT
   transaction* tagged with the assembly. Assembly "actual" quantities are **computed from
   those OUT transactions**, never stored separately, so an issue can never be deducted twice
   whether it is recorded from the assembly page or the standard OUT page.
5. **Corrections** — no transaction is ever deleted. An IN/OUT is corrected by a **reversal**
   (opposite movement, reason, user, date) or by a **stock adjustment**.

---

## 2. Technology & runtime

| Concern          | Choice                                                                 |
|------------------|------------------------------------------------------------------------|
| Framework        | Laravel 12 (PHP ≥ 8.2), Blade, Eloquent                                |
| Database         | MySQL 8 / MariaDB 10.4+ (InnoDB, utf8mb4)                              |
| UI               | Bootstrap 5.3, Bootstrap Icons, Chart.js 4, Tom Select — **vendored in `public/vendor`**, no Node build, no CDN |
| Exports          | CSV (native), XLSX (PhpSpreadsheet), PDF (DomPDF)                     |
| Sessions / cache | `database` driver — no Redis                                           |
| Queue            | `sync` — no background worker required                                 |
| Images           | Private `local` disk (`storage/app/private/parts`), served via an authenticated controller; re-encoded with GD and thumbnailed on upload |

Nothing requires Node.js, Docker, Redis, cron or a daemon in production.

---

## 3. Database design

### 3.1 Entity relationships

```
roles 1─* users            roles *─* permissions (role_permissions)
users *─* permissions (user_permissions — extra per-user grants)

part_categories 1─* spare_parts *─1 units
suppliers       1─* spare_parts
operations      1─* spare_parts (final_operation_id, CNC only)
spare_parts *─* machinery_models (spare_part_machinery_model — compatibility)
spare_parts 1─* spare_part_images

machines 1─* cnc_production_records *─1 spare_parts
operators 1─* cnc_production_records *─1 operations
machinery_models 1─* cnc_production_records

spare_parts 1─* cnc_production_completions 1─0..1 cnc_inventory_transactions (receipt)
spare_parts 1─* cnc_inventory_transactions        (CNC ledger)
spare_parts 1─* imported_inventory_transactions   (Imported ledger)
stock_adjustments 1─1 {cnc|imported}_inventory_transactions
machine_assemblies 1─* machine_assembly_items *─1 spare_parts
machine_assemblies 1─* imported_inventory_transactions (OUT tagged with assembly)
*_inventory_transactions 1─0..1 *_inventory_transactions (reversal_of_id)

activity_logs *─1 users (nullable; polymorphic subject)
document_sequences (reference-number counters) · app_settings (key/value)
```

### 3.2 Tables

| Table | Purpose / key columns |
|---|---|
| `users` | name, username (unique), email, password (bcrypt), role_id, is_active, last_login_at |
| `roles` | name (slug, unique), display_name, is_system |
| `permissions` | name (unique, e.g. `imported.out.create`), group, display_name |
| `role_permissions`, `user_permissions` | pivot tables |
| `machines` | code (unique, M-1…M-20), name, status (active/maintenance/inactive), description |
| `operators` | employee_code (unique), name, contact, is_active |
| `part_categories` | name, scope (cnc/imported/both), is_active |
| `units` | name, symbol, allows_decimal, is_active |
| `operations` | name, sequence (unique), is_active — 1st, 2nd, 3rd, extensible |
| `machinery_models` | name, manufacturer, model_code — cigarette machinery (e.g. Protos, MK8) |
| `suppliers` | name, country, contact details |
| `spare_parts` | **inventory_type** (cnc/imported), sku (unique), name, category, unit, specification, description, brand, part_number, supplier, final_operation_id, min_stock, opening_stock, **current_stock**, unit_cost, currency, is_active |
| `spare_part_images` | path, thumb_path, is_primary, mime, size |
| `cnc_production_records` | reference_no, date, machine, part (+ name/SKU snapshot), machinery model, operation (+ name/sequence snapshot), **is_final_operation** snapshot, start/end time, duration, quantity, operator, status (running/completed/cancelled), cancel reason |
| `cnc_production_completions` | reference_no, date, part, inspected/accepted/rejected qty, status (pending/approved/rejected/reversed), submitted_by, approved_by, reasons |
| `cnc_inventory_transactions` | CNC ledger: reference_no, date, part (+ snapshots), type (opening/production_receipt/issue/adjustment_in/adjustment_out/reversal), quantity_in, quantity_out, **balance_after**, completion_id, adjustment_id, reversal_of_id, issued_to, purpose, idempotency_key |
| `imported_inventory_transactions` | Imported ledger: reference_no, date, part (+ SKU/name/category/unit snapshots), type (opening/in/out/adjustment_in/adjustment_out/reversal), quantity_in/out, balance_after, supplier, document reference, unit cost, currency, machinery model/purpose, collected_by, department, machine_assembly_id, reversal links, idempotency_key |
| `stock_adjustments` | reference_no, inventory_type, part, direction, quantity, **reason (mandatory)** |
| `machine_assemblies` | reference_no, name, machinery model, status, dates |
| `machine_assembly_items` | assembly, imported part, planned_quantity (actual = Σ OUT transactions) |
| `activity_logs` | user, action, subject, description, old/new values (JSON), IP, user agent |
| `document_sequences` | per prefix/year counter for gap-free reference numbers |
| `app_settings` | company name, currency, image size limit … |

Indexes cover every date/part/machine/operator/type filter used by dashboards and reports
(e.g. `(production_date, machine_id)`, `(spare_part_id, transaction_date)`, `(type, transaction_date)`).

### 3.3 Historical integrity

* Transactions store **snapshots** (part name, SKU, category, unit, operation name) so that
  renaming a master record never rewrites history.
* Master data is **deactivated, never deleted**. There is no delete action for any master
  record, part, user or transaction.
* Users are deactivated (cannot log in) but remain linked to their transactions.

---

## 4. Stock accounting rules

1. **One ledger per stream.** `cnc_inventory_transactions` and `imported_inventory_transactions`
   are separate tables; the posting service refuses a part whose `inventory_type` does not
   match the ledger — CNC and imported stock cannot mix.
2. **Balance maintenance.** `spare_parts.current_stock` is maintained by a single service
   (`App\Services\StockService`). Every posting runs inside a DB transaction that
   `SELECT … FOR UPDATE`-locks the part row, computes the new balance, rejects it if negative,
   writes the ledger row with `balance_after`, and updates `current_stock`.
   Consequently `current_stock == opening + Σ in − Σ out` at all times; `php artisan stock:verify`
   and the System Health page recompute this from the ledger and flag any difference.
3. **No negative stock** — validated in the form request (friendly message) and again under
   the row lock (authoritative, protects against two users issuing simultaneously).
4. **Idempotency.** Each stock form carries a one-time `idempotency_key` (UUID). The key has
   a UNIQUE index in the ledger tables; a double click / resubmit returns the already-saved
   transaction instead of posting twice. Submit buttons are also disabled client-side.
5. **CNC completion pool.**
   `awaiting_completion(part) = Σ qty of completed, non-cancelled production records whose
   operation was the part's final operation − Σ inspected qty of pending/approved completions`.
   Completions cannot exceed the pool; editing/cancelling production records is refused if it
   would make the pool negative. Intermediate operations never touch stock.
6. **Reversals.** A reversal posts the opposite quantity, links `reversal_of_id` to the
   original (unique — a transaction can be reversed only once), marks the original as reversed,
   requires a reason and is logged. Reversing a receipt is refused if that stock has already
   been issued (would go negative).
7. **Adjustments** require a reason, create a `stock_adjustments` row plus a ledger row, and
   are restricted to `*.stock.adjust` permissions.
8. **Reference numbers** come from `document_sequences` under a row lock, so they are unique
   and sequential per prefix and year (`CNC-PRD`, `CNC-CMP`, `CNC-TXN`, `IMP-IN`, `IMP-OUT`,
   `IMP-REV`, `ADJ`, `ASM`).

---

## 5. Roles, permissions & navigation

Authorisation is enforced on the **server** through route middleware (`can:<permission>`) and
Gate checks inside controllers; menus are only hidden as a convenience.
Super Admin bypasses all checks (`Gate::before`).

| Permission group | Super Admin | CNC User | Import User | Combined |
|---|:-:|:-:|:-:|:-:|
| Overall dashboard | ✔ | ✔ | ✔ | ✔ |
| CNC dashboard / production view & create / finish | ✔ | ✔ | – | ✔ |
| CNC production edit & cancel | ✔ | ✔ | – | ✔ |
| CNC completion submit | ✔ | ✔ | – | ✔ |
| CNC completion approve / reverse | ✔ | – | – | – |
| CNC inventory view / issue | ✔ | ✔ | – | ✔ |
| CNC part master manage | ✔ | ✔ | – | ✔ |
| Imported dashboard / stock view / IN / OUT | ✔ | – | ✔ | ✔ |
| Imported product master manage | ✔ | – | ✔ | ✔ |
| Imported reversal | ✔ | – | – | – |
| Assemblies view / manage & issue | ✔ | – | ✔ | ✔ |
| Stock adjustments (CNC / Imported) | ✔ | – | – | – |
| Reports CNC / Imported | ✔ | CNC | Imported | both |
| Settings (machines, operators, masters, suppliers) | ✔ | – | – | – |
| Users, roles, audit log, system health | ✔ | – | – | – |

The Super Admin may edit any role's permission set, create new roles, and grant extra
permissions to an individual user.

**Navigation (collapsible sidebar):** Dashboard · CNC (Dashboard, Production Entries, New
Entry, Operation Progress, Machines, Completions, CNC Stock, CNC Parts) · Imported (Dashboard,
Stock, Inventory IN, Inventory OUT, Transactions, Assemblies, Products) · Stock Adjustments ·
Reports · Settings · Admin (Users, Roles, Activity Log, System Health).

---

## 6. Application structure

```
app/
  Console/Commands/     CreateAdmin, VerifyStock
  Http/Controllers/     Auth, Dashboard, Cnc/*, Imported/*, Admin/*, Settings (generic master CRUD), Reports, Search, Media
  Http/Middleware/      EnsureUserIsActive
  Models/               Eloquent models
  Reports/              one class per report (columns + query) + ReportRegistry
  Services/             StockService, CncProductionService, ImageService, ReferenceGenerator,
                        ActivityLogger, DateRange, ExportService, MasterDataRegistry
  Support/              helpers (qty formatting)
database/migrations, database/seeders (production seed vs DemoDataSeeder)
resources/views/        layouts, components, cnc, imported, admin, settings, reports
tests/Feature/          acceptance scenarios
docs/                   architecture, installation (Hostinger), backup, user guides
```

---

## 7. Dashboard layouts

* **Overall** — KPI row (CNC SKUs, imported SKUs, CNC stock, imported stock, combined units,
  CNC production today/month, imported IN/OUT today/month, active machines), alert panel
  (low/out-of-stock per stream), charts (production trend, IN vs OUT trend, category stock),
  tables (top manufactured parts, most issued imported parts, machine-wise production,
  consumption by machinery model, recent stock movements, recent production).
  Global filters: date range, inventory type, category, machine, part. Every KPI links to the
  filtered detail page.
* **CNC** — machines active/running/idle, today/month/YTD production, running jobs, completed
  operations, charts by machine/operator/part and daily/weekly/monthly trend.
* **Imported** — unique products, units, stock value (only priced items, labelled), received /
  issued today, monthly IN/OUT, low/out of stock, category distribution, most issued, by
  machinery model, trend.

---

## 8. Consistency risks identified and how they are handled

| Risk | Mitigation |
|---|---|
| Summing all operations into stock (100+80+60=240) | Only approved completions post stock; pool limited to final-operation output |
| Completing the same output twice | Pool subtracts pending + approved completions; computed under the part lock |
| Editing a production record after its output was completed | Edit/cancel re-checks the pool under lock |
| Two users issuing the last units simultaneously | `SELECT … FOR UPDATE` on the part row; balance checked inside the lock |
| Double-click / browser resubmission | Unique idempotency key + disabled submit button |
| Assembly issue recorded twice (assembly page + OUT page) | Assembly actuals are derived from OUT transactions — one source of truth |
| CNC part issued from imported screens (or vice versa) | Type check in StockService + type-filtered lookups + validation |
| History changing after a master rename | Snapshots on every transaction |
| Deleting history | No delete routes; deactivation only; reversal/adjustment for corrections |
| Dashboard total ≠ ledger | Both read the same tables; `stock:verify` + health page reconcile ledger vs balance |
| Reversing a receipt already consumed | Reversal posts through the same negative-stock guard |

---

## 9. Assumptions & configurable decisions

1. **Final operation per part** decides which production output is "finished". Configurable per
   part; defaults to the highest-sequence active operation. Changing it affects future entries
   only (each entry snapshots `is_final_operation`).
2. **One operator per production entry.** Multiple operators on the same job are recorded as
   separate entries with their own quantities, so operator totals add up correctly.
3. **Rejected quantities** at completion are recorded for quality reporting and consume the
   pool (they were produced, just not accepted); they never enter stock.
4. **Reversing an approved completion** removes the accepted quantity from stock (if still
   available) and returns the inspected quantity to the awaiting-completion pool.
5. **Combined stock units** on the overall dashboard are a plain sum of quantities across
   different units, explicitly labelled as such; each stream's balance is always shown separately.
6. **Stock value** is calculated only from products that have a unit cost, per currency, and is
   labelled "priced items only".
7. **Quantities** support up to 3 decimals (e.g. metres); units flagged `allows_decimal = false`
   (pieces, sets) must be whole numbers.
8. **Completion approval** — users with `cnc.completion.approve` may approve at submission time
   ("submit & approve"); others create a pending request.
9. **Timezone** is set by `APP_TIMEZONE` (default `Asia/Karachi`).

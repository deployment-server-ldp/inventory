# User Guide

The menu on the left only shows what your role allows; the server also blocks everything else.
Click ☰ (top-left) to collapse the menu. The search box at the top finds parts (name, SKU, part number,
category), machines and any reference number (e.g. `IMP-OUT-2026-000012`, `CNC-PRD-2026-000301`).

**Common rules for everyone**
* Every save gets a unique reference number and is recorded with your name and time.
* Nothing is ever deleted. Mistakes are corrected by *cancel*, *reverse* or *stock adjustment* — always with a reason.
* Clicking **Save** twice cannot create two transactions.
* Stock can never go below zero; the system tells you how much is available.
* Date filters: choose *Today, This week, This month, Year to date…* or *Custom range*; tables, charts and exports all use the same range.
* Every KPI card and most chart bars are clickable and open the matching filtered list.

---

## 1. CNC User

### Record production (CNC → New Production Entry)
1. Date defaults to today (cannot be in the future).
2. Select the **CNC machine**, the **Operation** (1st, 2nd, 3rd…) and the **Part** — type any part of the name/SKU; the
   part image, category, final operation and finished stock appear on the right.
3. Optional: target **machinery model** the part is for.
4. Select the **operator**, enter **start time**.
5. If the job is finished, enter **end time** and **quantity**. If the machine is still running, leave end time empty —
   the entry appears as *Running* on the dashboards. Later open it and use **Finish this job**.
6. **Save & add another** keeps the same machine selected for the next entry.

One entry = one part on one machine for one operation. Several shifts/operators on the same job = several entries.

### Understand operation progress (CNC → Operation Progress)
Shows, per part, how many pieces passed each operation. Example: 1st 100 · 2nd 80 · 3rd 60.
These are **work in progress, not stock**. Only the final operation (here 60) can become finished stock, and only after QC.

### Submit a completion (CNC → Completions / QC → Record completion)
Choose the part — "Awaiting completion" shows how many finished pieces are waiting. Enter **accepted** and
**rejected** quantities (total cannot exceed the waiting quantity) and submit. A quality approver then approves it;
only then does stock increase.

### Machines (CNC → CNC Machines)
Live tiles (running / idle / maintenance). Click a machine for its history: today, month, selected range, operation-,
part- and operator-wise totals, run time and all records. **Monthly sheet** gives a date-by-date table for the month
with Excel/CSV/PDF export and print.

### CNC stock
*CNC Stock* lists finished parts; *Ledger* shows every movement with opening/closing balances.
**Issue stock** records parts dispatched to a customer or used (who received them, for which machine, purpose).

### Corrections
* Wrong production entry → open it → **Edit** or **Cancel entry** (reason required). The system refuses if that output
  was already submitted for completion — ask QC to reject/reverse the completion first.

---

## 2. Import Inventory User

### Receive parts (Imported → Inventory IN)
1. Date (default today), search the **product** (image, category, stock shown automatically).
2. If the product does not exist and you have permission, click **New product** — a pop-up creates it (image is
   mandatory) and selects it without losing what you already typed.
3. Enter **quantity received**, supplier or source, PO/invoice/shipment reference, unit cost and currency (optional).
4. **Save** — stock increases immediately; you get a reference like `IMP-IN-2026-000031`.

### Issue parts (Imported → Inventory OUT)
1. Search the product (only items in stock are listed); **Available stock** is shown.
2. Enter quantity, the **machine / machinery model** it is for, the **person collecting**, department.
3. If the parts are for a machine being assembled, choose the **Machine assembly** — it will count in that assembly.
4. **Save** — stock decreases. If you ask for more than is available, the system refuses and tells you the available quantity.

### Machine assemblies (Imported → Machine Assemblies)
1. **New assembly**: machine/project name, reference (auto if blank), machinery model, dates.
2. Add each required imported part with its **planned quantity**.
3. The table shows planned, issued, remaining, stock available and shortages.
4. Issue directly from a row (quantity + collected by) or via the OUT form with the assembly selected — both create one
   normal OUT transaction, so stock is deducted exactly once.
5. Set status to *Completed* when done; closed assemblies cannot receive more parts.

### Stock, transactions, dashboard
*Imported Stock* (filters: category, low/out of stock; value shown only where cost is known),
*Transactions* (all IN/OUT with filters and export), *Imported Dashboard* (today/month IN & OUT, low-stock, category
distribution, most issued parts, parts per machinery model, trends).

---

## 3. Combined User
Has both the CNC User and Import Inventory User functions above, plus both dashboards and both report groups.
CNC stock and imported stock always remain separate; the main dashboard shows them side by side.

---

## 4. Super Admin

Everything above, plus:

### Quality approval (CNC → Completions / QC)
Pending completions show a yellow banner. Open one, adjust accepted/rejected if needed (they must add up to the
inspected quantity) and **Approve & add to stock**, or **Reject** with a reason (quantity returns to the waiting pool).
An approved completion can be **Reversed** (reason required) only while its stock is still available.
When recording a completion yourself you may tick **Approve now**.

### Reversals & adjustments
* Imported IN/OUT: open the transaction → **Reverse transaction** (reason). Reversing a receipt that was already issued is refused.
* CNC issue: *CNC Stock → Ledger* → reverse on the row.
* **Stock Adjustments** (Analysis menu): increase/decrease with a mandatory reason, for physical count differences, damage, loss.

### Settings
CNC machines (M-1…M-20, add more, set *Under maintenance*), operators, operations (add a 4th/5th operation with its
sequence), part categories (CNC / imported / both), units (allow decimals for metres/kg), machinery models, suppliers,
company name/address (printed on PDF reports). Records are **deactivated**, never deleted.

### Part / product master
*CNC Parts Master* and *Products Master*: create/edit with mandatory primary image and extra images, set minimum
stock, final operation (CNC), supplier/cost/currency (imported), compatible machinery models. On a part page you can
replace the primary image, add images, or choose another primary.

### Users & roles
* **Users → New user**: name, username, role, temporary password (user must change it at first login), optional extra
  permissions on top of the role.
* **Reset password** generates a temporary password and signs the user out everywhere.
* **Deactivate** blocks login immediately; the user's history remains.
* **Roles & Permissions**: change what each role may do, or create new roles.

### Audit & system
* **Activity Log**: logins/logouts/failed logins, production changes (with before/after values), IN/OUT, completions,
  reversals, adjustments, master data, user and permission changes, report exports. Filter by date, user, module, reference.
* **System Health**: environment checks, stock-ledger reconciliation, application error log.

---

## 5. Reports Center
Choose a report, set filters (period, machine, part, operator, category, machinery model, assembly…), search, sort by
clicking column headers, and **Export → Excel / CSV / PDF**. Exports contain exactly the filtered data and only
reports your role may see.

Available: Daily & monthly CNC production · Machine-wise · Operator-wise · Part-wise · CNC stock · CNC ledger ·
Imported stock · Daily IN · Daily OUT · Imported ledger · Machine-wise consumption · Assembly consumption ·
Stock valuation · Category-wise stock · Low / out-of-stock.

### Labels you will see
| Term | Meaning |
|---|---|
| Operation output / all operations | Sum of quantities of every operation — work done, **not** stock |
| Finished (final op) | Output of each part's final operation |
| QC accepted | Quantity approved into CNC stock |
| Awaiting completion | Finished output not yet submitted for QC |
| Combined units* | CNC + imported quantities added together regardless of unit — indicative only |
| Stock value (priced items) | Only products that have a unit cost, per currency |

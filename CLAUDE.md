# YANGS – Bar Management and POS System

## Project summary

A POS and business management system for YANGS, a small bar. It covers open-tab table ordering,
payments, inventory, expenses, staff scheduling and payroll, and financial reporting for the owner.
Built for real daily use, not a demo. The full spec is in `docs/YANGS_SPEC.md`. Progress is tracked in `docs/PROGRESS.md`.

## Stack

- Backend: latest stable Laravel (MVC), Laravel authentication, RBAC
- Frontend: Blade with Tailwind CSS
- Database: PostgreSQL
- API: versioned REST under `/api/v1`, designed so a future mobile app can use the same backend
- Dev machine: Windows with PowerShell. Write commands for PowerShell, not bash.

## Standard workflow (every step)

1. **Superpowers**, in this order:
   - `brainstorming`: only if the step has real design ambiguity
   - `writing-plans`
   - `test-driven-development`
   - `subagent-driven-development` or `executing-plans`
   - `verification-before-completion`
   - `requesting-code-review`
2. **Ponytail at `full`**: write only what the step needs, but never cut validation, authorization,
   error handling, security or accessibility. Run `/ponytail-review` before the user commits.
3. **Caveman**: use it only for chat replies (short, no filler). Never use it in code, comments, docs, UI text or
   commit messages.
4. **UI work**:
   - Read `design-system/YANGS/MASTER.md` (created in Step 6) and use the `frontend-design` skill.
   - Touch targets must be at least 48px.
   - Test in portrait and landscape tablet orientations.
5. **Graphify**:
   - Before changing an existing module, look it up with `graphify query "<question>"` (or `/graphify query`).
   - Rebuild the graph at each phase checkpoint:
     - Full build: `/graphify .` (CLI: `graphify extract .`)
     - Code-only refresh between checkpoints: `graphify update .`
     - Forced full re-scan (e.g. after deleting files): add `--force`
6. **Git**:
   - Do not run `git commit`.
   - At the end of each step, give the user:
     - a summary
     - the list of changed files
     - test results
     - one suggested one-line Conventional Commit message

## Business rules (non-negotiable)

### Payments
- Never mark an order or session Paid unless the recorded payments cover the total.
- Payment status changes only when a payment is actually recorded, inside a DB transaction with
  row locking (`lockForUpdate`).

### Order history
- Order items snapshot the product name, unit price and unit cost at the time of ordering.
- Later price or cost changes never alter history.

### No hard deletes
- No hard deletes on financial, inventory, payroll or audit records.
- Use soft deletes, voids, reversal entries or archival instead.
- Corrections are new records, never edits to history.
- Soft-delete convention for business records (products, categories, suppliers, employees, expenses and similar):
  - Model: `use App\Models\Concerns\HasAuditedSoftDeletes;` (wraps `SoftDeletes`).
  - Migration: `$table->softDeletesTz();`
  - `delete()` archives the record and logs `<model>.archived`. `restore()` logs `<model>.restored`.
    `forceDelete()` throws.
  - Call `delete()` and `restore()` inside the service's DB transaction.
  - Never use query-builder `delete()` or `forceDelete()` on these models. They skip model events.
- Append-only records (activity logs, inventory movements, payments, refunds, voids, price history) are never
  soft-deleted. Their model refuses update and delete, and a `reject_append_only_change()` trigger backs that up in
  the database.

### Audit trail usage
- Write entries with `App\Services\Audit\ActivityLogger::log($action, $subject, $old, $new)`.
  Inject it, or call `app(ActivityLogger::class)`.
- Name actions `<module>.<verb>`, for example `product.price_changed`.
- Log only changed fields, inside the same DB transaction as the change.
- Never log passwords, PINs or tokens.

### Inventory
- Inventory movements are an append-only ledger.
- A stock change and the order that caused it commit in the same DB transaction.

### Accounting separation
- Keep these four separate: sales revenue, inventory purchases, COGS and operating expenses.
- Inventory purchases are not operating expenses.
- COGS comes from cost snapshots at sale time.
- Never double count.

### Audit trail
- Every important action writes an activity log entry with the user, action, affected record, old and new values, and time.

### Money, currency and time
- Store money as `decimal(12,2)`, never float. Do money math with bcmath.
- Currency: PHP. Timezone: `Asia/Manila`.

### Data and seeders
- No fake or hardcoded dashboard numbers. Every figure comes from real records.
- Seeders may add sample data only in local/dev.
- Production seeds only roles, permissions, payment methods, categories and settings.

### Code structure
- Business logic goes in service classes, not controllers.
- Validation uses Form Requests.
- Authorization uses Policies and permissions.
- UI uses reusable Blade components and layouts.
- API output uses API Resources.

### Packages
- Allowed without asking:
  - Laravel first-party packages
  - `spatie/laravel-permission` (RBAC)
  - `barryvdh/laravel-dompdf` (PDF)
  - `maatwebsite/excel` (Excel)
  - Chart.js (npm)
- Any other package needs the user's approval first.

## Test rule

Every service with money or stock logic has feature tests before work moves on. This covers payments, split and
partial payments, voids and refunds, order totals, discounts, stock-in and stock-out, conversion units,
COGS, profit calculations and payroll. The tests must pass before the step is reported done.

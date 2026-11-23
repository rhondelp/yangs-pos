# Expense Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expenses with fixed categories, an optional private receipt file, void-only corrections, filtered totals next to a separate "Inventory purchases" total, on screens and the API.

**Architecture:** `App\Services\Finance\ExpenseService` is the only writer of `expenses` (record, void) and computes the totals; web and API controllers share it. An expense row may change once, only to be voided, enforced by the model and a PostgreSQL trigger. Receipt files sit on the private `local` disk and are streamed by a permission-checked controller action.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Blade + Tailwind + Alpine, PHPUnit feature tests, `Storage::fake('local')` in tests.

**Spec:** `docs/superpowers/specs/2026-11-22-expense-management-design.md` (binding), `CLAUDE.md`, `design-system/YANGS/MASTER.md`.

## Global Constraints

- Money `decimal(12,2)`, bcmath only; amount > 0 and ≤ `9999999999.99`; regex `/^\d{1,10}(\.\d{1,2})?$/`.
- Seeded categories, in order: Electricity, Water, Rent, Employee-related, Maintenance, Transportation, Other. No category screen.
- No edits, no deletes. Void once, reason 1–300 chars, `expenses.manage`. No supervisor PIN.
- Expense date ≤ today's business date (`BusinessDate::for(now())`, cutoff 06:00, `Asia/Manila`).
- Attachments: `jpg, jpeg, png, webp, pdf`, max 5 MB (`max:5120`), stored on disk `local` under `expenses/YYYY/MM/` with a random name.
- `expenses.view`: list, page, totals, receipt file. `expenses.manage`: record, void.
- Logs: `expense.recorded`, `expense.voided` via `ActivityLogger::log()` inside the transaction.
- Inventory purchases = Σ `stock_receipts.total_cost` by `business_date` in the range; never added to the expense total; category filter ignored.
- No new packages. No `git commit`.

## Review Focus

1. A failed save after the file was stored leaves no orphan file. Test in Task 2.
2. Two people voiding the same expense at once: one succeeds, the other gets "This expense is already voided." (row lock). Test in Task 2 (sequential second void; the lock is in the code).
3. A receipt URL guessed by id for an expense the user cannot see returns 403, never the file; a voided expense's receipt is still viewable by `expenses.view`. Test in Task 3.
4. An expense dated today at 01:00 (before the 06:00 cutoff) is accepted: "today" is the business date. Test in Task 2.
5. A file renamed to `.pdf` that is really an executable is refused (MIME check, not extension only). Test in Task 2.

---

### Task 1: Schema, categories, model guard

**Files:**
- Create: `database/migrations/2026_11_22_200000_create_expenses_tables.php`, `app/Models/ExpenseCategory.php`, `app/Models/Expense.php`, `database/seeders/ExpenseCategorySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (production list, after `CategorySeeder`)
- Test: `tests/Feature/Finance/ExpenseSchemaTest.php`

**Interfaces:**
- Produces:
  - `ExpenseCategorySeeder::NAMES` (the seven names); idempotent by lower-case name.
  - `ExpenseCategory` (`name`, `is_active`; `scopeActive`), `Expense` (`category()`, `recordedBy()`, `voidedBy()` belongsTo; casts `amount` `decimal:2`, `expense_date` `date`, `voided_at` `datetime`; `isVoided(): bool`).
  - `Expense` refuses `delete()` and any update once `voided_at` was already set, or that changes a column other than `voided_at`, `voided_by`, `void_reason`, `updated_at` (LogicException).
  - Trigger function `expenses_void_once()`: `DELETE` rejected; `UPDATE` rejected when `OLD.voided_at IS NOT NULL` or any other column differs.
  - Check constraints: `amount > 0`; void columns all null or all set.

- [ ] **Step 1: Write failing tests:** `test_the_seeder_adds_the_seven_categories_once` (run twice → 7 rows, order by id equals `NAMES`); `test_an_expense_can_be_voided_once_and_never_edited_or_deleted` (forceCreate, then `update(['amount' => '1'])` throws LogicException; `delete()` throws; void via `forceFill([...])->save()` works; a second void save throws); `test_the_database_refuses_edits_deletes_and_bad_rows` (raw `DB::update` of `amount` → QueryException; raw delete → QueryException; insert with `amount = 0` → QueryException; insert with only `voided_at` set → QueryException).
- [ ] **Step 2: Run** `php artisan test --filter=ExpenseSchemaTest` → FAIL (no table).
- [ ] **Step 3: Implement** migration, models, seeder; add `ExpenseCategorySeeder` to `DatabaseSeeder`.
- [ ] **Step 4: Run** → PASS.

### Task 2: ExpenseService (record, void, totals, list)

**Files:**
- Create: `app/Services/Finance/ExpenseService.php`
- Test: `tests/Feature/Finance/ExpenseServiceTest.php`

**Interfaces:**
- Consumes: Task 1 models.
- Produces:
  - `record(User $by, array $input, ?UploadedFile $attachment = null): Expense` — `$input`: `expense_category_id`, `description`, `amount`, `expense_date`. `AuthorizationException` without `expenses.manage`; `ValidationException` keys as the input names plus `attachment`.
  - `void(Expense $expense, User $by, ?string $reason): Expense` — locks the row; errors on `reason`.
  - `totals(array $filters): array{expenses: string, inventory_purchases: string}`
  - `list(array $filters): LengthAwarePaginator` — 30 per page, `expense_date` desc then id desc, `withQueryString()`, loads `category`, `recordedBy`, `voidedBy`.
  - `$filters`: `array{from: string, to: string, category_id: ?int, voided: bool}`.

- [ ] **Step 1: Write failing tests** (owner; `Storage::fake('local')`; a Cashier user):
  - `test_record_saves_logs_and_stores_the_receipt_privately`: returns the expense; file exists on fake `local` under `expenses/2026/11/`; `attachment_name` is the client name; log `expense.recorded` new values `category, description, amount, expense_date, attachment: true`.
  - `test_record_validation`: amount `0`, `-1`, `1.234`, `10000000000`; description empty and 201 chars; unknown and inactive category; date `tomorrow`; attachment `.txt`, a 5,121 KB image, and a file named `evil.pdf` with `application/x-msdownload` content (Review Focus 5) — each refused on its key.
  - `test_an_expense_dated_today_before_the_cutoff_is_accepted` (Review Focus 4): `travelTo('2026-11-22 01:00')`, `expense_date = 2026-11-21` accepted, `2026-11-22` refused.
  - `test_a_failed_save_leaves_no_file` (Review Focus 1): make the insert fail (e.g. `DB::statement` trigger or listen `Expense::creating` to throw) → `Storage::disk('local')->allFiles()` empty.
  - `test_void_once_with_reason_and_logged`: reason required; voided fields set; `expense.voided` logged with reason; second void → `reason` error "This expense is already voided." (Review Focus 2).
  - `test_recording_and_voiding_need_expenses_manage`: Cashier → `AuthorizationException`.
  - `test_voided_expenses_are_excluded_from_totals`: 1,250.50 + 300 + voided 999 → `expenses = '1550.50'`.
  - `test_totals_follow_dates_and_category`; `test_inventory_purchases_sum_stock_in_by_business_date_and_ignore_category`: two stock-ins in range, one outside → their sum; with `category_id` set it is unchanged; it never appears in `expenses`.
- [ ] **Step 2: Run** `php artisan test --filter=ExpenseServiceTest` → FAIL.
- [ ] **Step 3: Implement.** Validate with `Validator::make` inside the service (MIME rule `mimes:jpg,jpeg,png,webp,pdf` + `max:5120`). Store the file before the transaction; wrap the transaction in try/catch that deletes the stored path and rethrows. Category must be `active()`. Date rule: `date_format:Y-m-d|before_or_equal:`.BusinessDate::for(now()).
- [ ] **Step 4: Run** → PASS.

### Task 3: Web screens, routes, navigation, receipt download

**Files:**
- Create: `app/Http/Controllers/Web/ExpenseController.php`, `app/Http/Requests/Finance/ExpenseFilterRequest.php`, `app/Http/Requests/Finance/RecordExpenseRequest.php`, `app/Http/Requests/Finance/VoidExpenseRequest.php`, `resources/views/expenses/index.blade.php`, `resources/views/expenses/create.blade.php`, `resources/views/expenses/show.blade.php`
- Modify: `routes/web.php`, `app/Support/Navigation.php` (after Stock-in: "Expenses", icon `banknotes`, `Permission::ExpensesView`)
- Test: `tests/Feature/Finance/ExpenseWebTest.php`

**Interfaces:**
- Consumes: `ExpenseService` (Task 2).
- Produces:
  - `ExpenseFilterRequest::filters(): array{from, to, category_id, voided}` — defaults: `from` = first day of the business date's month, `to` = business date; `to` `after_or_equal:from`; `category_id` `exists:expense_categories,id`.
  - Routes: `expenses.index` GET `/expenses`, `expenses.show` GET `/expenses/{expense}`, `expenses.attachment` GET `/expenses/{expense}/attachment` (all `can:expenses.view`); `expenses.create` GET `/expenses/create` (`?from={id}` prefills from a voided expense), `expenses.store` POST `/expenses`, `expenses.void` POST `/expenses/{expense}/void` (all `can:expenses.manage`). `/expenses/create` registered before `/expenses/{expense}`.
  - `ExpenseController::attachment(Expense): StreamedResponse` — 404 when no file; `Storage::disk('local')->response($path, $attachment_name, ['X-Content-Type-Options' => 'nosniff'])`.

- [ ] **Step 1: Write failing tests:**
  - `test_the_list_shows_both_totals_and_filters` (tiles "Expenses" and "Inventory purchases" with the right amounts; voided hidden by default and shown struck with `voided=1`).
  - `test_record_form_records_and_redirects_to_the_expense`; `test_record_again_prefills_from_a_voided_expense` (category, description, amount, date filled; refused prefill from a non-voided one is ignored).
  - `test_void_dialog_voids_with_reason`.
  - `test_receipt_access_is_permission_gated` (Review Focus 3): owner 200 with the file body; Cashier 403; a user with only `expenses.view` 200; voided expense receipt still 200; no file → 404.
  - `test_pages_need_their_permissions`: Cashier 403 on index, create, store, void.
- [ ] **Step 2: Run** `php artisan test --filter=ExpenseWebTest` → FAIL.
- [ ] **Step 3: Implement** requests, controller, routes, navigation.
- [ ] **Step 4: Views** per spec "Screens": filter bar (from, to, category, show voided), two `x-ui.stat-card` tiles ("Expenses"; "Inventory purchases" with hint "Stock-in, not an expense"), table with voided rows `line-through` + `x-ui.status-badge status="voided"`; create form with `enctype="multipart/form-data"`, `accept="image/*,application/pdf"`, button disabled while sending; show page with image preview (`<img>` from the attachment route) or PDF link, void `x-ui.modal` with reason, "Record again" for voided. Touch targets ≥ 48 px.
- [ ] **Step 5: Run** → PASS; `php artisan test --filter=Finance` → all pass.

### Task 4: API

**Files:**
- Create: `app/Http/Controllers/Api/V1/ExpenseController.php`, `app/Http/Resources/V1/ExpenseResource.php`, `app/Http/Resources/V1/ExpenseCategoryResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Finance/ExpenseApiTest.php`

**Interfaces:**
- Consumes: Task 2 service, Task 3 requests.
- Produces: `GET expenses` (`meta.totals.expenses`, `meta.totals.inventory_purchases`), `GET expenses/{expense}`, `POST expenses` (multipart, 201), `POST expenses/{expense}/void`, `GET expenses/{expense}/attachment`, `GET expense-categories`; names `api.v1.expenses.*`, `api.v1.expense-categories.index`. Resource fields: `id, category_id, category, description, amount, expense_date, has_attachment, attachment_name, recorded_by, recorded_at, voided, voided_at, voided_by, void_reason`.

- [ ] **Step 1: Write failing tests:** list with filters and totals (voided excluded); record multipart with a receipt → 201; void → `voided: true`; second void 422; attachment 200 for owner and 403 for Cashier; invalid filters 422; categories list returns the seven.
- [ ] **Step 2: Run** `php artisan test --filter=ExpenseApiTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS; `php artisan test` → all pass.

### Task 5: Verify, screens, docs

- [ ] **Step 1:** `php artisan test` all pass; `npm run build`; `vendor/bin/pint --test` on changed PHP files.
- [ ] **Step 2:** Screen check at 768×1024 and 1024×768 on the throwaway `yangs_test` database (same method as Step 16): `/expenses`, `/expenses/create`, an expense page with an image receipt, the void dialog. Every control ≥ 48 px, no horizontal scroll. Screenshots to `docs/screenshots/step-17/`.
- [ ] **Step 3:** `graphify update .`
- [ ] **Step 4:** `docs/PROGRESS.md` Step 17 entry; tick Phase 4 "Expense tracking".
- [ ] **Step 5:** Final review (fresh reviewer), fix Important findings test-first, `/ponytail-review`; report summary, files, tests, one commit line.

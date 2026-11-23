# YANGS – Development Progress

Phases from `docs/YANGS_SPEC.md` section 15. Check an item only when it is implemented, tested and verified.

## Phase 1 – Foundation (checkpoint 2026-11-18)
- [x] Laravel setup
- [x] PostgreSQL configuration
- [x] Authentication
- [x] User roles and permissions
- [x] Database architecture (design in `docs/ARCHITECTURE.md`; tables are built per module, see below)
- [x] Base UI layout

### Done
- **Setup:** Laravel 13.43 on PHP 8.4 with PostgreSQL 18. Databases: `yangs_pos` for the app, `yangs_test` for tests.
  - Timezone is `Asia/Manila` for both PHP and the DB session. Currency config is `PHP`.
  - Eloquent strict mode is on outside production. `/up` is the health check.
  - Tests can never touch the dev database: there is a driver/name guard and a separate config-cache path.
- **Architecture:** `docs/ARCHITECTURE.md` holds the ERD for all 20 modules, the key decisions and the 46-step roadmap.
- **Authentication:**
  - Web: Breeze Blade, rewritten for username login. No registration and no e-mail reset.
  - API: Sanctum tokens (`/api/v1/auth/login|logout|me`), expiring after 30 days.
  - Login is rate-limited on both.
  - A deactivated user cannot sign in, gets signed out mid-session, and their tokens stop working.
- **RBAC:**
  - `spatie/laravel-permission` with 41 permissions defined in `App\Enums\Permission`.
  - Seeded roles: Owner (locked, every permission), Cashier (POS defaults), plus empty Supervisor, Bartender, Staff and Inventory Manager.
  - Permission matrix editor and user management, on web and API.
  - Only an Owner can grant the Owner role or edit an Owner's account. At least one active Owner always remains.
  - Every signed-in route needs a `can:` permission, enforced by `RouteGuardTest`.
- **Audit:**
  - `activity_logs` is append-only twice over: the model refuses changes and a DB trigger backs it up.
  - `ActivityLogger` writes entries; login, logout and failed logins are logged automatically.
  - Viewer with filters on web, plus `GET /api/v1/activity-logs`.
  - `HasAuditedSoftDeletes` trait for business records: archive and restore are logged, and `forceDelete` is refused.
- **UI:**
  - Dark design system in `design-system/YANGS/MASTER.md`, mapped to Tailwind 4 tokens.
  - Management layout (collapsible sidebar) and POS layout (full screen).
  - 16 Blade UI components.
  - Permission-aware navigation and empty-state dashboards.
  - 48px touch targets, checked at 768×1024, 1024×768 and 1366×768 (`docs/screenshots/step-6/`).
- **Tests:** 81 feature tests, 380 assertions, all passing.

### Deviated from the plan
- **Step numbering:** the owner's steps (1 setup, 2 architecture, 3 setup delta, 4 auth and RBAC, 5 audit, 6 design system) replace the step list in `ARCHITECTURE.md` §5.
- **Auth starter:** Breeze (owner's choice) replaced the Fortify idea. Its Tailwind 3 tooling was reverted to Tailwind 4, and the package was removed after install.
- **Login field:** username login instead of e-mail.
- **Permission names:** kebab-case, following the owner's examples. `ARCHITECTURE.md` §3.12 is updated to match.
- **Seeded roles:** Supervisor is seeded empty, not with the permissions the design proposed.
- **Audit log timing:** built in Step 4, ahead of plan, because the audit rule applied to auth actions.
- **Logger API:** `ActivityLogger` is an injected service, not a static call.
- **Soft deletes:** who archived a record goes in the activity log, not a `deleted_by` column.
- **Alpine.js:** approved and installed. axios was removed, since the POS will use `fetch`.
- **Database architecture:** tables are created with each module, not all in Phase 1. The ERD is the contract.
- **Design system:** the generator's draft (slate and green, glassmorphism, Fira Code) was replaced with warm oak and amber, flat layers, and Barlow. Reasons are in `MASTER.md`.

### Known gaps
- **Login hardening:** no per-IP limit across usernames, and web and API count limits differently. Audit items 1–2; fix before production.
- **Cookie-based API calls:** closed in Phase 2. `RejectDeactivatedApiUsers` also stops a deactivated user's browser session on `/api/v1`.
- **Supervisor PIN approval:** built in Step 12 for voids, refunds and corrections. Item cancels and discounts still need the permission itself (no PIN path).
- **Settings table and service:** built in Step 11 for receipt details and the table-after-payment rule. VAT and business-day cutoff settings are not added yet.
- **Modal and confirm dialog:** the modal is used on the transaction page since Step 12; the confirm dialog is still unused.
- **Still open with the owner:**
  - hosting (local PC or cloud);
  - 6-decimal unit costs;
  - Senior/PWD discount.
- **Owner account in dev:** not seeded yet. Set `OWNER_PASSWORD` in `.env`, then run `php artisan db:seed --class=OwnerSeeder`.

## Phase 2 – Core POS (checkpoint 2026-11-19)
- [x] Table management
- [x] Product and category management
- [x] Customer table sessions
- [x] Order creation
- [x] Open tabs
- [x] Consolidated billing
- [x] Payment processing
- [x] Receipt generation

### Step 11 – Billing, payments and receipts (2026-11-19)
- **Spec and plan:** `docs/superpowers/specs/2026-11-18-billing-payments-design.md`, `docs/superpowers/plans/2026-11-18-billing-payments.md`.
- **Bill:** subtotal, bill discount (amount or percent, with a reason, `orders.discount`), total, paid, balance.
  - Opening the bill page sets the tab to `billing` and the table to Awaiting payment. A new order reopens the tab.
- **Payments:** `PaymentService::record` runs in one transaction with the tab row locked.
  - Cash gives change. GCash and other methods take a reference. Split and partial payments are allowed; overpaying is not.
  - Each attempt carries an idempotency key, so a double tap or a resend records one payment.
  - When payments cover the total, the tab closes, totals are frozen, `RCP-YYYYMMDD-NNNN` is assigned, orders become paid and the table becomes Available (or Cleaning, by setting).
  - A bill discounted to zero closes with `settleFreeBill`.
  - After any payment, items can no longer be cancelled or discounted (no refunds yet).
- **Receipts:** `/receipts/{session}` prints on 80 mm (`?paper=58` for 58 mm). Business details and footer come from Settings. The first view logs `receipt.printed`; later views log `receipt.reprinted`.
- **Owner pages:** Settings (`/settings`) and Payment methods (`/payment-methods`), both `settings.manage`.
- **API:** `GET sessions/{id}/bill`, `POST sessions/{id}/payments`, `POST|DELETE sessions/{id}/discount`, `POST sessions/{id}/settle`, `GET sessions/{id}/receipt`, `GET payment-methods`.
- **Checked:** 768×1024 and 1024×768 with Playwright: split GCash and cash, change, double tap, receipt (`docs/screenshots/step-11/`).
- **Tests:** 248 feature tests, all passing. A final review fixed: the bill screen now always sends the amount on its button (a stale screen could charge a balance another tablet raised), a discount can no longer make the total equal what is paid, the discount cannot be removed after a payment, and the receipt page size is valid CSS (it printed on Letter before).
- **Not built (out of scope):** refunds and payment voids, cashier shifts and drawer counts, VAT lines, supervisor PIN, printer integration.

### Step 12 – Voids, refunds and corrections (2026-11-19)
- **Spec and plan:** `docs/superpowers/specs/2026-11-19-voids-refunds-corrections-design.md`, `docs/superpowers/plans/2026-11-19-voids-refunds-corrections.md`.
- **Voids** (`orders.void`): an item quantity or a whole order, on a live tab that already took money (before that, cancel). Stored in append-only `order_voids`; the order item row never changes. The bill subtracts voided units and their share of the line discount. A void may not take the bill below what is paid; if it lands exactly on it, the tab closes. `StockHook::itemVoided` is the Phase 3 seam.
- **Refunds** (`payments.refund`): closed tabs only, against one payment, never more than what is left of it. Method (defaults to the payment's corrected method), reference when the method needs one, reason, idempotency key. Orders become `partially_refunded` or `refunded`. Append-only `refunds`.
- **Corrections** (`corrections.create`): fix a payment's method or reference with an append-only `payment_corrections` row; the latest wins on the bill, receipt and transaction page. A cash payment that gave change cannot change method.
- **Approval PIN:** optional setting `require_approval_pin`. Approvers (`approvals.grant`) set a 4–6 digit PIN on their profile; voids, refunds and corrections then need their username and PIN. 5 failed tries a minute per approver; failures are logged.
- **Screens:** void buttons on the bill screen (grouped by order); `/transactions` (closed tabs per business day; own tabs only without `orders.view-all`) and `/transactions/{id}` with Refund and Correct dialogs. Cashiers reach Transactions from the POS header.
- **API:** `POST order-items/{id}/void`, `POST orders/{id}/void`, `POST payments/{id}/refunds`, `POST payments/{id}/corrections`; each returns the record and the new bill.
- **Net sales inputs:** bill `voided` and `refunded`, with `business_date` on every void and refund, for Phase 4 reports.
- **Checked:** 768×1024 and 1024×768 with Playwright (`docs/screenshots/step-12/`).
- **Tests:** 294 feature tests, all passing. The final review fixed: the order screen now shows items net of voids, refunds and corrections respect view-own, PINs are never flashed into the session, voids carry an idempotency key, and a line with voids can no longer change quantity.
- **Not built:** cash-drawer effect of refunds (no shifts yet), item-level refunds, voiding a void.

### Step 13 – Transaction history and POS end-to-end tests (2026-11-19)
- **Plan:** `docs/superpowers/plans/2026-11-19-transaction-history-and-e2e.md`.
- **History filters** on `/transactions`: business day, table, cashier (only for `orders.view-all`), payment status (paid, partly refunded, refunded) and method (the corrected method wins). Filters never widen what a view-own user sees.
- **End-to-end API test** `tests/Feature/EndToEnd/OpenTabWorkflowTest.php` walks YANGS_SPEC §4B steps 1–12 and checks every §4C order field, the price snapshot after a price change, the partial-payment rule, change, receipt and the audit trail.
- **Concurrency tests** `tests/Feature/Concurrency/ConcurrencyTest.php` start five real PHP processes (`tests/Concurrency/worker.php`) against the test database at the same instant: double payment with different keys, the same payment resent five times, the same void resent five times, and five opens of one table.
- **Browser test** `tests/Browser/open_tab_flow.py` (Python Playwright, see `tests/Browser/README.md`) runs the tablet flow in portrait and landscape. Screenshots: `docs/screenshots/step-13/`.
- **Bugs found and fixed:**
  - The API order record lacked the table, the cashier and the order total (§4C). `OrderResource` now has `table`, `cashier` and `total`.
  - A resent payment or void that raced the first one got "This tab is closed." when the first one had closed the tab, so the tablet showed an error for money that was recorded. The replay check now runs before the live-tab check, under the same lock.
- **Tests:** 302 feature tests, all passing.
- **YANGS_SPEC §4 still not implemented:** nothing in §4A–§4D is missing. Related owner decisions in `ARCHITECTURE.md` that are not built yet: PDF receipts (§1; receipts are on screen and browser print), move / merge / split tabs (§1), counter (walk-in) sales (§1), VAT (§1, §3.11), cashier shifts and drawer counts (§1), supervisor PIN for item cancels and discounts (§3.10; only voids, refunds and corrections use it). Orders keep `status: open` after their tab is paid; the paid state is `payment_status` (§4C lists both).

### Phase 2 checkpoint (2026-11-19)
- **Audit:** a whole-repo audit of the Phase 2 code found 10 issues. Fixed (each with a test that failed first, `tests/Feature/Checkpoint/Phase2AuditTest.php`):
  1. Staff could not cancel a wrong item or a mistaken tab from the screens (API only). The order screen now has **Cancel** per item (before any payment, `orders.cancel-item`) and **Cancel tab** when nothing is left on it (`pos.cancel-session`), both with a reason dialog.
  2. The receipt showed item discounts twice under the bill discount's reason. The "Discount" line is now the bill discount only.
  3. A typo in cash received could record any change for ever. Change of ₱1,000 or more is refused.
  4. After a failed refund, other payments' dialogs were prefilled with its amount and reason. Old input now fills only the dialog it came from (`x-ui.input` / `x-ui.select` `:old`).
  5. A supervisor PIN could be set without the current password. It now needs it.
- **Module map:** `docs/MODULES.md` covers Phases 1–2.
- **Graph:** full rebuild (`/graphify .`) at this checkpoint.
- **Tests:** 307 feature tests, all passing; the browser test passes in portrait and landscape.

### Deviated from the plan (Phase 2)
- **Steps:** the owner's steps 7–13 (tables, catalogue, open tab, POS screen, billing, voids/refunds/corrections, history and tests) replace the `ARCHITECTURE.md` §5 list.
- **Cancel vs void:** before any payment, items are cancelled (the line is marked cancelled); after a payment, units are voided as separate append-only records. ARCHITECTURE §3.3 had one "void" for both.
- **Discounts:** the bill discount is stored on the tab (type, value, reason) rather than in a `discounts` table; removing it is refused once money is taken.
- **Business date of a tab:** set when the tab opens (history groups by it), not at close.
- **Orders keep `status: open`** after the tab is paid; the paid state is `payment_status`.
- **Shared reference counter:** `reference_counters (prefix, business_date)` numbers both orders (`ORD`) and receipts (`RCP`).

### Known gaps (Phase 2)
- **From the checkpoint audit, not fixed:**
  - an idle tablet waking on the bill screen re-presents the bill (`GET bill` changes state), flipping a reopened table back to Awaiting payment;
  - raising an item's quantity does not reopen a `billing` tab;
  - "Correct method" is offered on cash payments that gave change (always refused);
  - payment-status filter options are listed twice (view and request);
  - approver usernames can be told apart by response time.
- **Deferred from the step reviews:** a 4-digit PIN can be guessed over a long night at 5 tries a minute, and 5 wrong tries lock that approver out for a minute; numeric JSON PINs are rejected; the "Voided" amount differs from the total's drop under a percent bill discount; a replayed payment gives no "already recorded" message; the cashier filter lists all users; race tests assume the `yangs_test` name (no `--parallel`).
- **Not built (owner decisions in `ARCHITECTURE.md`):** PDF receipts, tab move / merge / split, counter sales, VAT, cashier shifts and drawer counts, PIN path for cancels and discounts.
- **Still open from Phase 1:** login hardening (per-IP limit), before production.

## Phase 3 – Inventory
- [x] Suppliers
- [x] Stock-in
- [x] Stock-out
- [x] Inventory movements
- [x] Low-stock monitoring
- [ ] Product cost tracking

### Step 14 – Suppliers, inventory items and stock-in (2026-11-19)
- **Spec and plan:** `docs/superpowers/specs/2026-11-19-inventory-stock-in-design.md`, `docs/superpowers/plans/2026-11-19-inventory-stock-in.md`.
- **Owner decisions:** no product/supply type on items (cups and ice are ordinary items; non-sold supplies are Phase 4 expenses). ARCHITECTURE names kept: `inventory_units`, `recipe_components`.
- **Data:** suppliers and inventory items (audited soft deletes); units per item (case = 24 bottles; the base unit is implicit); recipes per product or product option (several items make a cocktail); stock receipts and lines (unit and factor snapshots, base quantity, line cost, cost per base unit); the append-only `inventory_movements` ledger. Quantities 14,3, unit costs 14,6, money 12,2.
- **Stock-in:** `InventoryService::receive` is the only writer of the ledger, `on_hand` and `avg_unit_cost`. One transaction, items locked by id, one `receipt` movement per line with the balance after, weighted-average cost per ARCHITECTURE §3.4, reference `SR-YYYYMMDD-NNNN`, logged `stock.received`. Purchases are inventory, never operating expenses.
- **Guards:** the base unit is fixed once an item has stock, units or recipes; numbers too small or too large for the ledger columns are refused with a message; API times with any offset are stored as the same moment.
- **Screens:** Inventory (list with stock status and value; item page with details, units, movement history), Stock-in (list, new stock-in with a line editor and previews, receipt page), Suppliers, and a Recipe card on the product page. Checked at 768×1024 and 1024×768 (`docs/screenshots/step-14/`), all targets 48 px or more.
- **API:** suppliers, inventory items (units, movements), stock receipts, product recipe.
- **Tests:** 342 feature tests, all passing (`tests/Feature/Inventory/*`: case-to-bottle conversion, average cost math, ledger sum = cached quantity, bounds, permissions, screens and API).
- **Not built yet:** receipt void, deduction on sale with the cost snapshot, void/cancel returns, manual stock-out, low-stock notifications, inventory reports.
- **Known gaps (deferred minors):** the recipe card loses edits on a validation error and drops archived ingredients on re-save; a role with `inventory.stock-in` but not `inventory.view` cannot open the receipt it just made; the stock-in error summary's `lines` anchor and per-line `aria-invalid`; the line preview accepts "1440." which the server refuses; a recipe save race, restoring an archived item or supplier whose name is now taken, and the API list hiding archived items.

### Step 15 – Stock-out and automatic sale deduction (2026-11-19)
- **Spec and plan:** `docs/superpowers/specs/2026-11-19-stock-out-and-sale-deduction-design.md`, `docs/superpowers/plans/2026-11-19-stock-out-and-sale-deduction.md`.
- **Owner decisions:**
  - cancel and void ask **"Put back in stock?"**: yes writes a `void_return` at the sale cost; no is an inventory loss, stored as `returned_to_stock = false`;
  - **refunds never touch stock**;
  - new setting **`allow_negative_stock`**, off by default. Off refuses a sale beyond what is on hand; on allows it, warns staff and logs `stock.went_negative` once per crossing. ARCHITECTURE §1 and §3.3 are updated.
- **Sale deduction:** a product with "Track inventory" on and a recipe for the way it is sold deducts in the order's transaction.
  - Lock order: session, then order item, then inventory items by ascending id.
  - One `sale` movement per line and component.
  - The cost snapshot (`order_items.unit_cost`) is the recipe at the current average cost.
  - `order_items.stock_components` keeps what one unit took, so returns are exact even after a recipe changes.
  - Quantity raises deduct the extra units.
  - Products without tracking or without a recipe keep their manual cost.
- **Manual stock-out** (`inventory.adjust`):
  - reasons: damaged, expired, missing, internal use, other (a note is required);
  - valued at the average cost and logged `stock.adjusted`;
  - never below zero;
  - on the item page and `POST /api/v1/inventory-items/{id}/stock-outs`.
- **Screens:**
  - "Put back in stock" in the cancel and void dialogs, on by default, shown only for lines that tracked stock;
  - stock warnings in the POS toast;
  - "Allow selling below zero" on Settings.
  - Checked at 768×1024 and 1024×768 (`docs/screenshots/step-15/`).
- **Races proven:**
  - five tablets selling the last bottle, and five quantity raises for it, give one sale;
  - the raise race was shown to oversell when the row lock is removed;
  - a whole-order void locks all its stock in one ascending pass.
- **Tests:** 363 feature tests, all passing.
- **Known gaps (deferred):**
  - the COGS snapshot (2 dp per unit) and the sale movement total can differ by a cent;
  - an archived item still used in a recipe blocks its product at zero stock;
  - repeated below-zero warnings for two lines of the same item;
  - stock-out has no idempotency key for API retries.

### Step 16 – Inventory monitoring and reports (2026-11-22)
- **Spec and plan:** `docs/superpowers/specs/2026-11-20-inventory-monitoring-design.md`, `docs/superpowers/plans/2026-11-22-inventory-monitoring.md`.
- **Owner decisions:**
  - inventory items take an optional **category** from the existing product categories (no second list);
  - the crossing check lives in `InventoryService`, the only ledger writer;
  - the "unusual" flag is **stored** on the movement, so a later threshold change never rewrites history;
  - notifications wait for Phase 6, which listens to the new events; product cost tracking is the next step.
- **Data:** `inventory_items.category_id` (nullable, indexed); `inventory_movements.is_unusual` (default false). New setting `unusual_adjustment_amount` (₱1,000.00 by default; blank or 0 turns it off).
- **Stock levels (`/inventory`):**
  - search by name or SKU (`%` and `_` match literally), category, status (needs attention, low, out, below zero) and "Show archived" (archived items are now hidden by default);
  - summary tiles: stock value, items, low, out, below zero. Each row is worth max(on hand, 0) × average cost, rounded per row, and the total is the sum of the rows. The summary covers the filtered rows.
- **Movements (`/inventory/movements`):**
  - every movement across items, newest first, 50 a page; filters: item (archived items included), type, business date from/to, recorded by;
  - the **Adjustments** tab (`?type=adjustment`) shows reason, note and an "Unusual" badge;
  - rows link to the item and to the stock-in; a sale shows its order reference, linked to the paid tab for users who see every tab.
- **Events (no listeners yet, sent only after commit):**
  - `UnusualAdjustmentRecorded(movement)` for a stock-out worth at least the threshold;
  - `StockLevelCrossed(item, from, to, movement)` when a sale or stock-out makes an item worse (in stock, then low, then out, then below zero), once per crossing, using the running balance.
- **API:** `GET /api/v1/inventory/valuation` (items with `value` plus `summary`), `GET /api/v1/inventory-movements`, and `search`, `category_id`, `status`, `archived` on `GET /api/v1/inventory-items`. All need `inventory.view`.
- **Screens:** checked at 768×1024 and 1024×768 (`docs/screenshots/step-16/`); every control is 48 px or more and no page scrolls sideways.
- **Tests:** 406 feature tests, all passing (`InventoryLevelsTest`, `MovementHistoryTest`, `StockAlertsTest`).
- **Review fixes:**
  - the per-item movements API no longer fails on sale rows;
  - a non-numeric `category_id` is refused, not a server error;
  - archived items never count as needing attention;
  - the filter bar ignores a failed New item form.
- **Known gaps (deferred):**
  - `search=0` is treated as empty;
  - an invalid category filter (only by editing the URL) shows its error in the New item card;
  - a stock-in void fires no `StockLevelCrossed` (the spec limits crossings to sales and stock-outs);
  - archived categories have no "(archived)" marker in the API or the stock table.

### Phase 3 checkpoint (2026-11-22)
- **Inventory consistency check:** new read-only command `php artisan inventory:check` sums every item's movements (archived items too) and compares the sum with the cached `on_hand`. It prints the items that differ, with both figures, and exits with code 1. Result on the local database: all 5 items match. Tests: `tests/Feature/Inventory/InventoryConsistencyCheckTest.php`.
- **Audit:** an audit of the Phase 3 code (inventory services, stock paths in orders and voids, controllers, requests, views) found 4 issues. Fixed (test in `tests/Feature/Checkpoint/Phase3AuditTest.php`, failed first):
  1. Archiving an item still used in a recipe made every sale of that product fail with "Only 0 … left". An item in a recipe of a product in use can no longer be archived; the item page says which product to change first.
- **Module map:** `docs/MODULES.md` covers Phases 1–3.
- **Graph:** the code graph is refreshed (`graphify update .`). The full rebuild (`graphify extract .`) stopped twice: the doc pass (Gemini backend) returned no nodes for 5 docs, and graphify refused to overwrite the complete graph with a smaller one. Run `/graphify .` in the assistant to rebuild the doc nodes.
- **Screens:** Step 16 pages checked at 768×1024 and 1024×768 (`docs/screenshots/step-16/`).
- **Tests:** 410 feature tests, all passing.

### Known gaps (Phase 3)
- **Not built:** product cost tracking (Phase 3 checklist; the owner moved it to its own step, option A: current recipe cost and margin).
- **From the checkpoint audit, not fixed (each needs an owner decision):**
  - a wrong stock-in cannot be corrected: `receipt_void` exists in the ledger but has no screen or service. A typo in a line cost stays in the average cost, and later COGS, for good. A quantity typo can only be taken out by a stock-out at the average cost;
  - stock-in and stock-out over the API have no idempotency key, so a retried request records the delivery or the loss twice (the web forms disable the button while sending);
  - `StockHook` only forwards calls to `InventoryService` (about 40 lines that could go).
- **Carried from the steps:** see the known gaps under Steps 14–16.

## Phase 4 – Financial Management
- [x] Sales monitoring
- [x] Expense tracking
- [x] Cost calculations
- [x] Profit and loss reporting

### Step 17 – Expense management (2026-11-22)
- **Spec and plan:** `docs/superpowers/specs/2026-11-22-expense-management-design.md`, `docs/superpowers/plans/2026-11-22-expense-management.md`.
- **Owner decisions:**
  - expenses are **void only**: never edited or deleted; a mistake is voided with a reason and recorded again ("Record again" copies the voided expense into a new form);
  - seven fixed categories: Electricity, Water, Rent, Employee-related, Maintenance, Transportation, Other (no "Supplies (non-stock)"; such costs go under Other). No category screen yet;
  - the void is stored on the expense row (`voided_at`, `voided_by`, `void_reason`), as in the ARCHITECTURE ERD. No supervisor PIN.
- **Data:** `expense_categories` (seeded in production by `ExpenseCategorySeeder`) and `expenses` (amount `decimal(12,2)` > 0, expense date, optional receipt). The trigger `expenses_void_once()` refuses deletes and every update except one void; the `Expense` model refuses the same.
- **Service:** `App\Services\Finance\ExpenseService`:
  - `record` checks the category is in use, the amount, and that the date is not after today's business date (cutoff 06:00). It keeps the receipt on the private disk under `expenses/YYYY/MM/` (JPG, PNG, WebP or PDF, checked by content, up to 5 MB), removes the file if the save fails, and logs `expense.recorded`;
  - `void` locks the row, refuses a second void and logs `expense.voided`;
  - `totals` gives the expense total (voided excluded) and, separately, **Inventory purchases** (stock-in totals by business date, same range, category ignored).
- **Screens:** Expenses in the menu (`expenses.view`), with these parts:
  - the list has date range (this month by default), category and "Show voided" filters, and two tiles: Expenses and Inventory purchases ("Stock-in, not an expense"). Voided rows are struck through;
  - the record form (`expenses.manage`) has camera-ready file input;
  - the expense page shows the receipt preview and the void dialog, and "Record again" on a voided expense.
  - Receipts are served only through `/expenses/{id}/attachment` after the permission check, with `nosniff`.
  - Checked at 768×1024 and 1024×768 (`docs/screenshots/step-17/`); every control is 48 px or more.
- **API:** `GET/POST expenses` (list with `meta.totals`; multipart record), `GET expenses/{id}`, `POST expenses/{id}/void`, `GET expenses/{id}/attachment`, `GET expense-categories`.
- **Review fixes:**
  - an upload the server refused now says so and names the 5 MB limit;
  - a receipt that could not be written stops the expense instead of saving it without its file;
  - the file name must also end in jpg, jpeg, png, webp or pdf;
  - the API accepts the amount as a JSON number;
  - an end date given alone starts the range at the first of its month (it gave an empty list before);
  - the API receipt route uses the web `attachment` action, so both send the same headers.
- **Deployment note:** PHP's `upload_max_filesize` must be at least 6M (this machine has 2M, which refuses camera photos over 2 MB); `post_max_size` at least 8M.
- **Tests:** 439 feature tests, all passing (`tests/Feature/Finance/*`).
- **Known gaps (deferred):**
  - after two people void the same expense at once, the slower one sees it voided but not the "already voided" message;
  - receipts are served inline without a `Content-Security-Policy: sandbox` header;
  - `POST /api/v1/expenses` has no idempotency key (as for stock-in and stock-out);
  - HEIC photos (iPhone library) are refused: they are not in the allowed list; not tested on a device.

### Step 18 – Financial calculations and profit and loss (2026-11-23)
- **Spec and plan:** `docs/superpowers/specs/2026-11-23-profit-and-loss-design.md`, `docs/superpowers/plans/2026-11-23-profit-and-loss.md`.
- **Owner decisions:**
  - inventory losses are their own line and come off net profit ("the best you can do");
  - refunds lower sales only, on the day they are given; COGS stays (the drinks were served);
  - voids are not subtracted again: the tab's frozen gross already leaves them out.
- **Service:** `App\Services\Finance\FinancialSummaryService` (read-only SQL aggregates, no new tables):
  - `summary($from, $to)`: gross sales, discounts, refunds, net sales, COGS, gross profit, operating expenses, inventory losses, net profit, inventory purchases (shown, never counted) and the number of items sold without a cost (counted as ₱0.00);
  - `breakdown($from, $to, $by)`: day, week (ISO, Monday start), month, year, product (one row per option), category, cashier (who closed the tab) and payment method (latest corrected method, minus refunds by refund method). Every breakdown ends with rows that reach the statement's net sales;
  - only closed tabs are sales; each figure is dated by its own source's business date.
- **Found while planning:** a tracked item cancelled before payment without going back to stock (poured) was in no figure. Its cost is now an inventory loss, dated by its order's business date.
- **Screens:** Profit & loss in the menu (`finance.view`; Cashier has no access) with From / To / "Show by" filters, the statement (net profit large, losses in red with a minus sign), an inventory purchases tile, a warning when items were sold without a cost, and the breakdown table with its reconciling rows.
- **API:** `GET finance/summary` and `GET finance/breakdown?by=…`, both with `from` / `to` (default this month; an end date alone starts on the first of its month; at most 5 years).
- **Review fixes:**
  - a tab's business date is now set again when it closes (ARCHITECTURE §3.7). Before, a tab left open past the 06:00 cutoff stayed on the day it was opened, so its sale landed on, and changed, an earlier day. The Transactions date filter also uses this date now;
  - week labels start on Monday whatever the locale;
  - a start date after today, or a range over 5 years given by its start date alone, now says so on that field instead of returning zeros; an unknown "Show by" names the choices.
- **Tests:** 460 feature tests, all passing. A two-day fixture built through the real services (line and bill discounts, a cancelled item, a poured cancel, a cancelled tab, an open tab, a void back to stock, a wasted void, a refund on the next day, an item with no cost, a stock-in, a stock-out, a voided expense, a corrected payment) checks every figure against hand-calculated numbers (`tests/Feature/Finance/Financial*Test.php`, `FinanceApiTest`, `FinanceWebTest`).
- **Known gaps (deferred):**
  - payroll cost joins operating costs in Phase 5; VAT is not built;
  - every stock adjustment is treated as a stock-out (`shortcut:` comment in the service): a future "found" adjustment must change the sign;
  - product cost tracking (current recipe cost and margin) is still its own step; margins here are from sale-time costs;
  - no charts or export yet (Phase 6).

## Phase 5 – Employee Management
- [ ] Employee records
- [ ] Scheduling
- [ ] Attendance
- [ ] Salary and payroll

## Phase 6 – Dashboard and Reporting
- [ ] Owner dashboard
- [ ] Cashier dashboard
- [ ] Business analytics
- [ ] Reports
- [ ] Export functionality

## Phase 7 – API and Integration
- [ ] Complete REST API
- [ ] API authentication
- [ ] API documentation
- [ ] API testing
- [ ] Prepare the backend for future mobile application integration

## Phase 8 – Testing and Deployment
- [ ] End-to-end testing
- [ ] POS transaction testing
- [ ] Inventory consistency testing
- [ ] Permission testing
- [ ] Tablet responsiveness testing
- [ ] Performance optimization
- [ ] Production deployment preparation

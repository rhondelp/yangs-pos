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
- [ ] Suppliers
- [ ] Stock-in
- [ ] Stock-out
- [ ] Inventory movements
- [ ] Low-stock monitoring
- [ ] Product cost tracking

## Phase 4 – Financial Management
- [ ] Sales monitoring
- [ ] Expense tracking
- [ ] Cost calculations
- [ ] Profit and loss reporting

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

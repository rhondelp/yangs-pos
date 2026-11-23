# YANGS – Module Map (Phases 1–3)

Built from the Graphify graph refreshed at the Phase 3 checkpoint (2026-11-22): 2,806 nodes, 6,903 edges, 256 communities, in `graphify-out/`. The most connected nodes are `User` (147 edges), `TestCase` (102), `TableSession` (91), `Product` (74), `InventoryItem` (73) and `InventoryService` (54). Rebuild the graph at each phase checkpoint. Ask it questions with `graphify query "<question>"`.

## Platform and configuration
- **Stack:** Laravel 13 on PHP 8.4 with PostgreSQL 18.
- **Entry points:**
  - `bootstrap/app.php` sets up routing, appends `LogoutDeactivatedUsers` to the web group, and makes `api/*` return JSON errors.
  - `AppServiceProvider` turns on Eloquent strict mode outside production. It also registers the `login` rate limiter, the Sanctum rule that rejects tokens of deactivated users, and the default paginator view.
- **Config:** `config/app.php` holds `Asia/Manila` and `currency = PHP`. `config/database.php` sets the PostgreSQL session timezone. `config/auth.php` holds the owner seed block.
- **Tests:** `phpunit.xml` points tests at `yangs_test` with their own config-cache path.
- Graph communities: *App Bootstrap*, *App Service Provider*, *Composer Config*, *Frontend Build Config*, *Logging Config*.

## Authentication
- **Web:** Breeze-derived username login. The pieces are `LoginRequest` (rate limit, inactive users fail like a wrong password), `AuthenticatedSessionController` and `PasswordController` (self-service password change, audited).
- **API:** Sanctum token login in `Api\V1\AuthController` (`login`, `logout`, `me`).
- **Deactivated users:** blocked twice. The `LogoutDeactivatedUsers` middleware signs them out of web sessions, and a Sanctum callback rejects their tokens.
- **Login logging:** `LogAuthenticationEvents` writes `auth.login`, `auth.logout` and `auth.login_failed` for the web. The API controller logs its own.
- **Removed on purpose:** registration, e-mail reset and verification.
- Graph communities: *Login and Form Requests*, *Auth and Dashboard Controllers*, *Deactivated User Middleware*.

## Roles and permissions (RBAC)
- **Permissions:** `App\Enums\Permission` is the single catalogue, 41 `module.action` names. It is the most connected node in the graph.
- **Seeding:** `RolesAndPermissionsSeeder` copies the catalogue into spatie's tables. It re-grants Owner every permission and gives Cashier its POS defaults only on first seed.
- **Roles:** `RoleName` lists the six seeded roles.
- **Matrix editor:** `RolePermissionService` changes each role's permissions behind both the web editor and the API. The Owner role cannot be edited, and every change is logged.
- **Route guard:** `RouteGuardTest` fails if any signed-in route lacks a `can:` permission.
- Graph communities: *Permission Catalogue*, *Role Permission Matrix*, *Role and Owner Seeders*.

## User management
- **Service:** `UserService` creates and updates accounts with one role each. It refuses to let you deactivate yourself or leave zero active Owners, locking the other owners' rows while it checks. Deactivating someone deletes their tokens and sessions. It logs `user.created`, `user.updated`, `user.deactivated` and `user.activated`, without password fields.
- **Validation:** `SaveUserRequest` checks lowercase unique usernames and limits which roles a user may hand out.
- **Policy:** `UserPolicy` stops a non-owner from editing an Owner account.
- **Endpoints:** web `UserController`, and `Api\V1\UserController` with `UserResource`.
- **Owner account:** `OwnerSeeder` creates the first owner from `OWNER_*` env values. It never resets an existing account.
- Graph communities: *User and Audit Core*, *Web User Management*.

## Audit trail
- **Table:** `activity_logs` is append-only twice over. The `ActivityLog` model refuses update and delete, and the `reject_append_only_change()` trigger backs that up in the database.
- **Logger:** `ActivityLogger::log($action, $subject, $old, $new)` is the only writer. It snapshots the user's name and IP and is meant to run inside the caller's transaction.
- **Viewer:** a `filter` scope (user, action, inclusive Manila dates) is shared by the web viewer (`/activity-logs`) and the API (`GET /api/v1/activity-logs`). Both need `logs.view`.
- **Soft deletes:** the `HasAuditedSoftDeletes` trait is the convention for business records. It logs archive and restore and refuses `forceDelete`.
- Graph communities: *Activity Log Model*, *Activity Log API*, *Append-Only Audit Design*.

## UI: design system, layouts, dashboards
- **Design system:** `design-system/YANGS/MASTER.md` defines the dark bar-top palette, the Barlow type families and the 48px touch rule. The tokens live in `resources/css/app.css` (`@theme`).
- **Layouts:**
  - `x-layouts.app`: collapsible sidebar and top bar for management pages.
  - `x-layouts.pos`: full-screen tablet layout.
  - `x-layouts.guest`: sign-in.
- **Components:** 16 anonymous `x-ui.*` components (button, input, select, table, status badge, stat card, modal, confirm dialog and others).
- **Navigation:** `App\Support\Navigation` lists sidebar items and filters them by permission.
- **Dashboards:** `DashboardController` routes the owner to empty-state stat cards plus real recent activity, the cashier to the POS layout, and roles without a dashboard to a clear message.
- **Pages on the kit:** profile, users, roles and activity-log pages all use these components.
- Graph communities: *Design System Docs*, *UI Rules and Kit Docs*, *Permission-Aware Navigation*, and many single-file view communities. Blade includes are mostly invisible to the code extractor, which is why the views show up isolated.

## Tables and floor (Phase 2)
- **Model:** `DiningTable` with a unique label, capacity, sort order and `is_active`. `TableStatus` (Available, Occupied, Awaiting payment, Reserved, Cleaning) decides which moves people may make by hand and which only the tab services make.
- **Services:** `DiningTableService` (create, edit, take out of use; refused while a tab is open) and `TableStatusService` (every status change under a row lock, logged as `table.status_changed`).
- **Screens:** the cashier floor (`/floor`, filter by status) and table management (`/tables`). A table with a tab opens its order screen; an Awaiting-payment table opens its bill.

## Catalogue (Phase 2)
- **Models:** `Category`, `Product` (drink or food, price, cost, availability, image) and `ProductVariant` (options such as "Bucket of 6"), all with audited soft deletes. `ProductPriceHistory` is append-only.
- **Service:** `ProductService` and `CategoryService`. A price or cost change writes price history and `product.price_changed`.
- **Margins:** `HasMargin` gives amount and percent for products and variants.

## Open tabs and orders (Phase 2)
- **Tab:** `TableSession` (`open` → `billing` → `closed`, or `cancelled`), one live tab per table, enforced by a partial unique index and the table row lock in `TableSessionService::open`.
- **Orders:** `OrderService` adds rounds with name, price and cost snapshots, numbers them `ORD-YYYYMMDD-NNNN` through the shared `ReferenceGenerator`, raises quantities, applies line discounts, and cancels items with a reason before any payment. A new round reopens a `billing` tab.
- **Locks:** `LocksLiveSession` (`lockSession`, `ensureLive`) is the one way services lock a tab: the session row first, then the item.
- **Running bill:** `RunningBill::calculate` is the single source of every total. Gross, discounts, voids, subtotal, bill discount, total, paid, refunded and balance all come from the stored lines, so screens, receipts and reports agree.
- **Stock seam:** `StockHook` (`deductForOrder`, `quantityAdded`, `itemCancelled`, `itemVoided`, `lockStockOf`) delegates to `InventoryService` since Phase 3 Step 15.

## Billing, payments and receipts (Phase 2)
- **Bill:** `BillService` presents the bill (table → Awaiting payment) and sets or removes the bill discount (amount or percent, with a reason; not after money was taken).
- **Payments:** `PaymentService::record` runs in one transaction with the tab locked. Cash gives change (capped below ₱1,000); other methods take a reference. Split and partial payments are allowed. An idempotency key makes resends safe, even when they race. The final payment closes the tab (`closeTab`): frozen totals, `RCP-YYYYMMDD-NNNN`, orders Paid, table Available or Cleaning.
- **Receipts:** `ReceiptService` (data from settings and the bill; `receipt.printed` / `receipt.reprinted`), printable at 80 or 58 mm.
- **Owner settings:** `SettingService` (business details, receipt footer, table-after-payment, approval PIN) and `PaymentMethodService`.

## Voids, refunds and corrections (Phase 2)
- **Append-only records:** `OrderVoid`, `Refund`, `PaymentCorrection`, each with model guard and database trigger.
- **Services:** `VoidService` (paid-into live tabs; may close the tab), `RefundService` (closed tabs, per payment, idempotent), `PaymentCorrectionService` (method or reference; the latest wins).
- **Approval:** `ApprovalService` checks the optional supervisor PIN (`require_approval_pin`) before the action's transaction, rate-limited per approver.
- **History:** `TransactionController` lists closed tabs per business day with filters (table, cashier, payment status, corrected method). `TableSession::visibleTo` keeps view-own users to tabs they opened or closed.

## Inventory (Phase 3)
- **Items and units:** `InventoryItem` (base unit, low-stock level, optional product `Category`, audited soft deletes) and `InventoryUnit` (e.g. case = 24 bottles). `InventoryItemService` creates, edits and archives them. It never sets stock or cost. The base unit is fixed once the item has stock, units or recipes, and an item in a recipe of a product in use cannot be archived.
- **Recipes:** `RecipeComponent` rows per product or product option. `RecipeService` replaces them as a set and logs `product.recipe_changed`.
- **Suppliers:** `Supplier` with audited soft deletes (`SupplierService`).
- **Ledger:** `InventoryService` is the only writer of `inventory_movements` (append-only: `receipt`, `receipt_void`, `sale`, `void_return`, `adjustment`) and of `on_hand` / `avg_unit_cost`. It locks items in ascending id. Its jobs:
  - stock-in (`receive`): weighted average cost, `SR-YYYYMMDD-NNNN`;
  - sale deduction (`deductForOrder`, `deductForQuantity`): cost snapshot on the order item, `allow_negative_stock`;
  - returns (`returnForLine`): at the sale cost;
  - manual stock-out (`stockOut`): at the average cost, flagged `is_unusual` at or above `unusual_adjustment_amount`.
- **Events (no listeners until Phase 6, after commit only):** `StockLevelCrossed` when a sale or stock-out makes an item worse; `UnusualAdjustmentRecorded` for a large stock-out.
- **Reports:** `InventoryReportService` (read-only) gives the stock levels with valuation (max(on hand, 0) × average cost, rounded per row) and the filtered movement history. Web and API share it.
- **Consistency:** `php artisan inventory:check` confirms every item's `on_hand` equals the sum of its movements (exit code 1 and a table when not).
- **Screens:** Inventory (stock levels, Movements, Adjustments tabs; item page with units, stock-out and history), Stock-in (list, line editor, receipt), Suppliers, the recipe card on the product page.

## Finance (Phase 4)
- **Expenses:** `ExpenseCategory` (seven fixed categories, seeded in production) and `Expense` (void only: never edited or deleted; the `expenses_void_once()` trigger backs this up). `ExpenseService` records them (receipt on the private disk), voids them once, and totals them beside inventory purchases.
- **Profit and loss:** `FinancialSummaryService` (read-only, SQL aggregates, no tables of its own) gives the statement and its breakdowns. Each figure has one source and is dated by that source's own business date (ARCHITECTURE §3.6):
  - net sales = gross − discounts − refunds, from closed tabs (voids are already out of gross) and refunds by refund date;
  - COGS from order item cost snapshots × (quantity − voided); refunds never change it;
  - operating expenses from `expenses` only; inventory losses (wasted voids, poured items cancelled before payment, stock-outs) are their own line; inventory purchases are shown, never counted.
  - Breakdowns: day, ISO week, month, year, product (and option) with margin, category, cashier (who closed the tab), payment method (latest corrected method, minus refunds by refund method). Each ends with rows that reach the same net sales.
- **Screens:** Expenses (list, record, expense page with receipt and void) and Profit & loss (`/finance`, `finance.view`).

## API v1
- **Routes:** `routes/api.php` puts everything under `/api/v1` behind `auth:sanctum` and the same `can:` permissions as the web.
- **Output:** API Resources for users, roles, logs, tables, catalogue, sessions, orders, bills, payments, receipts, voids, refunds and corrections.
- **Shared code:** controllers reuse the web Form Requests and services, so both paths enforce the same rules.
- **Endpoints today:** auth, users, roles, activity logs, tables, categories, products, menu, sessions (open, show, cancel, bill, payments, discount, settle, receipt), orders and order items (add, edit, cancel, void), payment methods, refunds and corrections; suppliers, inventory items (units, movements, stock-outs, filters), stock receipts, product recipes, `inventory/valuation` and `inventory-movements`; expenses (list with totals, record, void, receipt), expense categories, `finance/summary` and `finance/breakdown`. The tablet screens use the same endpoints with the browser session.

## Tests
460 feature tests on PostgreSQL, plus a browser test.
- **Phase 1:** environment, auth, RBAC, users, audit and UI, as before.
- **Phase 2:** tables, catalogue, tabs and orders, the running bill, billing and payments, receipts, voids, refunds, corrections, approval PIN, transaction filters.
- **Phase 3:** stock-in and average cost, sale deduction and returns, stock-out, negative-stock setting, valuation, filters, unusual flag, crossing events, the consistency command (`tests/Feature/Inventory/*`), and the checkpoint fix (`tests/Feature/Checkpoint/Phase3AuditTest.php`). Concurrency covers five tablets selling the last bottle.
- **Phase 4:** expenses, and the P&L against a hand-calculated two-day fixture (`tests/Feature/Finance/*`).
- **End to end:** `tests/Feature/EndToEnd/OpenTabWorkflowTest.php` walks YANGS_SPEC §4B steps 1–12 over the API.
- **Races:** `tests/Feature/Concurrency/ConcurrencyTest.php` starts five real PHP processes at once (double payment, resent payment and void, double table open).
- **Browser:** `tests/Browser/open_tab_flow.py` (Python Playwright) runs the tablet flow in portrait and landscape; see its README.

## Documentation and plans
- `docs/YANGS_SPEC.md`: the authority.
- `docs/ARCHITECTURE.md`: the ERD for all 20 modules, design decisions and roadmap.
- `CLAUDE.md`: non-negotiable rules and workflow.
- `docs/PROGRESS.md`: phase checklist and checkpoint notes.
- `docs/superpowers/specs/*` and `docs/superpowers/plans/*`: one design and one plan per step.

## Not built yet (designed in `docs/ARCHITECTURE.md`)
- **Phase 3:** product cost tracking (current recipe cost and margin) and stock-in voids.
- **Phase 4:** charts and export of the P&L (Phase 6), payroll cost in the P&L (Phase 5).
- **Phase 5:** employees, schedules, attendance and payroll.
- **Later:** cashier shifts, PDF receipts, tab move / merge / split, counter sales, VAT and notifications.

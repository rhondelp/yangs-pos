# YANGS – Module Map (Phases 1–2)

Built from the Graphify graph rebuilt at the Phase 2 checkpoint (2026-11-19): 2,200 nodes, 5,229 edges, 218 communities, in `graphify-out/`. The most connected nodes are `User` (161 edges), `TableSession` (109), `TestCase` (102), `Product` (73) and `ActivityLogger` (67). Rebuild the graph at each phase checkpoint. Ask it questions with `graphify query "<question>"`.

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
- **Stock seam:** `StockHook` (`deductForOrder`, `quantityAdded`, `itemCancelled`, `itemVoided`) is empty until Phase 3.

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

## API v1
- **Routes:** `routes/api.php` puts everything under `/api/v1` behind `auth:sanctum` and the same `can:` permissions as the web.
- **Output:** API Resources for users, roles, logs, tables, catalogue, sessions, orders, bills, payments, receipts, voids, refunds and corrections.
- **Shared code:** controllers reuse the web Form Requests and services, so both paths enforce the same rules.
- **Endpoints today:** auth, users, roles, activity logs, tables, categories, products, menu, sessions (open, show, cancel, bill, payments, discount, settle, receipt), orders and order items (add, edit, cancel, void), payment methods, refunds and corrections. The tablet screens use the same endpoints with the browser session.

## Tests
307 feature tests on PostgreSQL, plus a browser test.
- **Phase 1:** environment, auth, RBAC, users, audit and UI, as before.
- **Phase 2:** tables, catalogue, tabs and orders, the running bill, billing and payments, receipts, voids, refunds, corrections, approval PIN, transaction filters.
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
- **Phase 3:** suppliers, stock-in and stock-out, inventory movements, costing (fills `StockHook`).
- **Phase 4:** sales monitoring, expenses and P&L (reads voids and refunds by business date).
- **Phase 5:** employees, schedules, attendance and payroll.
- **Later:** cashier shifts, PDF receipts, tab move / merge / split, counter sales, VAT and notifications.

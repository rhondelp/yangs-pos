# Graph Report - yangs-pos  (2026-11-19)

## Corpus Check
- 332 files · ~88,620 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 21 file(s) not represented in the graph (top: (none) 17, .example 1, .xml 1)

## Summary
- 2200 nodes · 5229 edges · 218 communities (74 shown, 144 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 397 edges (avg confidence: 0.86)
- Token cost: 254,569 input · 0 output

## Community Hubs (Navigation)
- Product Catalogue
- Shared Test Base
- Payment Service Tests
- Order Service Tests
- Billing Core Services
- Category Management
- Web Routes and Controllers
- Permission Catalogue
- Composer Config
- API Routes and Controllers
- API Billing Endpoints
- Void and Approval Tests
- Append-Only Money Records
- Billing HTTP and Audit Tests
- Frontend Build and POS JS
- Query Scopes and Transactions
- Seeders and Settings Tests
- Bill Screen and Resource
- User Management
- User Model and E2E Tests
- Table Session Tests
- Validation Errors
- Refund Service Tests
- Product Price History
- Table Session API
- Audited Soft Deletes
- Payment Model and Corrections API
- Database Migrations
- Order Item Model
- Order Model
- Payment Methods
- Table Status Service
- Correction API Responses
- Order Item API
- Form Requests
- Catalogue Schema and Margins
- Voids Refunds Corrections Design
- User Management Tests
- Session Status and Concurrency
- Table Status Rules
- App Service Provider
- Running Bill and Tab Close
- Open Tab Design
- Role Permission Tests
- Product Web Tests
- Role Name
- Schema
- Payments Design (Session-Level, Close Only
- Open Tab Flow
- Payment Correction Service Test
- Open Tab Api Test
- Payment Method Controller
- Sub
- For
- Changes From Ui Ux Pro
- Consolidated Bill, Payments And Receipts
- Table Web Test
- Table Controller
- Navigation Test
- Minimal Activity Logs Table And
- Migration
- Yangs Architecture And Database Design
- Activity Log Test
- Payment Status
- Yangs Bar Management And Pos
- Append-Only Tables With Rejecting Trigger
- Component Test
- Auth Api Test
- Activity Log Viewer Test
- Transaction Web Test
- Order Schema Test
- Seeder Test
- Update Settings Request
- Dining Table
- Log Authentication Events
- Money Test
- Manila, Bcmath)
- Permission Enum (Module.Action Catalogue)
- Stock Hook (Phase 3 Seam)
- Stock Deducted On Send Round
- Billing, Payments And Receipts Implementation
- Payment Method Test
- Concurrency Test
- Order Schema Test
- Pos Screen Test
- Password Controller
- Calculate
- Laravel-Permission (Roles Only, No Gate::Before)
- Categories And Products Implementation Plan
- Pos Ordering Ui Implementation Plan
- Table Management Implementation Plan
- Authentication Test
- Receipt Test
- Role Permission Service
- Table Filter Request
- Round
- Foundation Setup Delta Plan
- User Service (Create, Update, Deactivation)
- Ui Work Rules (Master.Md, 48Px
- Step 13 Transaction History And
- Dashboard Test
- Product Type
- Transaction Filter Request
- Login Request
- Record Payment Request
- Add Order Request
- 0001 01 01 000002 Create
- Close Tab
- Cashier Module
- Order Item Status
- Login Request
- Update Approval Pin Request
- Apply Discount Request
- 0001 01 01 000001 Create
- 2026 11 18 155107 Add
- 2026 11 18 161812 Add
- 2026 11 18 220143 Add
- 2026 11 19 100400 Add
- Step 12 Voids, Refunds And
- Activity Log Filter Scope (User,
- Yangs Bar Management And Pos
- Environment Test
- Route Guard Test
- Correct Payment Request
- Void Item Request
- Void Order Request
- Cancel Session Request
- Open Session Request
- 2026 11 18 155422 Create
- 2026 11 18 155845 Create
- 2026 11 18 171504 Create
- 2026 11 18 171506 Create
- 2026 11 18 171507 Create
- 2026 11 18 174621 Create
- 2026 11 18 202354 Create
- 2026 11 18 202810 Create
- 2026 11 18 202811 Create
- 2026 11 18 220142 Create
- 2026 11 19 100100 Create
- 2026 11 19 100200 Create
- 2026 11 19 100300 Create
- Update Order Item Request
- Show.Blade
- Edit.Blade
- Example Test
- Health Check Test
- Edit.Blade
- Index.Blade
- Bill.Blade
- Create.Blade
- Edit.Blade
- Create.Blade
- Edit.Blade
- Show.Blade
- Create.Blade
- Edit.Blade
- Guest Layout (X-Layouts.Guest)
- System Activity Logs And Audit
- Employee Management And Payroll
- Tailwind Css
- Robots.Txt Allow All Crawlers

## God Nodes (most connected - your core abstractions)
1. `User` - 161 edges
2. `TableSession` - 109 edges
3. `TestCase` - 102 edges
4. `Product` - 73 edges
5. `ActivityLogger` - 67 edges
6. `Controller` - 64 edges
7. `Permission` - 62 edges
8. `Category` - 55 edges
9. `DiningTable` - 54 edges
10. `Payment` - 44 edges

## Surprising Connections (you probably didn't know these)
- `Authentication and RBAC Plan` --conceptually_related_to--> `Allowed Packages List`  [INFERRED]
  docs/superpowers/plans/2026-11-18-auth-rbac.md → CLAUDE.md
- `{closure#1}()` --calls--> `Product`  [EXTRACTED]
  tests/Feature/Catalog/CatalogSchemaTest.php → app/Models/Product.php
- `Management Layout (x-layouts.app)` --conceptually_related_to--> `RBAC with spatie/laravel-permission (roles only, no Gate::before)`  [INFERRED]
  design-system/YANGS/MASTER.md → docs/ARCHITECTURE.md
- `Foundation Setup Delta Plan` --references--> `Local Setup on Windows (PHP 8.4, PostgreSQL 18, Node 24)`  [INFERRED]
  docs/superpowers/plans/2026-11-18-foundation-setup-delta.md → README.md
- `SANCTUM_STATEFUL_DOMAINS for LAN Tablets` --conceptually_related_to--> `Alpine.js + fetch to /api/v1 for POS`  [INFERRED]
  README.md → docs/ARCHITECTURE.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Phase 1 implementation plan series following the standard workflow** — claude_standard_workflow, docs_superpowers_plans_2026_11_18_laravel_postgres_setup_plan, docs_superpowers_plans_2026_11_18_foundation_setup_delta_plan, docs_superpowers_plans_2026_11_18_auth_rbac_plan, docs_superpowers_plans_2026_11_18_audit_foundation_plan, docs_superpowers_plans_2026_11_18_design_system_layout_plan [INFERRED 0.85]
- **YANGS Blade layouts** — design_system_yangs_master_management_layout, design_system_yangs_master_pos_layout, design_system_yangs_master_guest_layout [EXTRACTED 1.00]
- **Core Business Flow** — docs_yangs_spec_pos_module, docs_yangs_spec_inventory_management, docs_yangs_spec_financial_management [EXTRACTED 0.95]
- **Technology Stack** — docs_yangs_spec_laravel, docs_yangs_spec_postgresql, docs_yangs_spec_tailwind_css, docs_yangs_spec_rest_api [EXTRACTED 1.00]
- **Two-layer append-only audit log (Eloquent guard + DB trigger + logger)** — docs_superpowers_plans_2026_11_18_audit_foundation_activitylog_append_only_guard, docs_superpowers_plans_2026_11_18_audit_foundation_activity_logs_trigger, docs_superpowers_plans_2026_11_18_audit_foundation_activitylogger_service, claude_append_only_records [INFERRED 0.85]
- **Permission-driven access control (catalogue, roles, matrix, route guard, nav filter)** — docs_superpowers_plans_2026_11_18_auth_rbac_permission_enum, docs_superpowers_plans_2026_11_18_auth_rbac_seeded_roles, docs_superpowers_plans_2026_11_18_auth_rbac_rolepermissionservice, docs_superpowers_plans_2026_11_18_auth_rbac_route_guard_test, docs_superpowers_plans_2026_11_18_design_system_layout_navigation [INFERRED 0.85]
- **Payment recording and tab close flow** — docs_modules_paymentservice, docs_modules_lockslivesession, docs_modules_runningbill, docs_progress_idempotency_key, docs_modules_tablestatusservice [INFERRED 0.85]
- **Append-only corrections gated by supervisor PIN** — docs_modules_voidservice, docs_modules_refundservice, docs_modules_paymentcorrectionservice, docs_modules_approvalservice, docs_architecture_append_only_tables [INFERRED 0.85]
- **Inventory costing design (send-time deduction, WAC, recipes, precision)** — docs_architecture_stock_deduction_on_send, docs_architecture_weighted_average_cost, docs_architecture_recipe_components, docs_architecture_money_precision, docs_architecture_stockservice [EXTRACTED 1.00]
- **Append-only correction services gated by optional approval PIN** — docs_superpowers_plans_2026_11_19_voids_refunds_corrections_voidservice, docs_superpowers_plans_2026_11_19_voids_refunds_corrections_refundservice, docs_superpowers_plans_2026_11_19_voids_refunds_corrections_paymentcorrectionservice, docs_superpowers_plans_2026_11_19_voids_refunds_corrections_approvalservice, docs_superpowers_plans_2026_11_19_voids_refunds_corrections_appendonly [EXTRACTED 1.00]
- **Open-tab lifecycle: open, order, bill, pay, close** — docs_superpowers_plans_2026_11_18_open_tab_tablesessionservice, docs_superpowers_plans_2026_11_18_open_tab_orderservice, docs_superpowers_plans_2026_11_18_open_tab_runningbill, docs_superpowers_plans_2026_11_18_billing_payments_billservice, docs_superpowers_plans_2026_11_18_billing_payments_paymentservice, docs_superpowers_plans_2026_11_18_tables_tablestatusservice [INFERRED 0.85]
- **Race-safety via DB locks, atomic counters and unique indexes** — docs_superpowers_plans_2026_11_18_open_tab_orderreferencegenerator, docs_superpowers_specs_2026_11_18_open_tab_design_one_open_tab_index, docs_superpowers_specs_2026_11_18_billing_payments_design_payment_idempotency, docs_superpowers_plans_2026_11_19_transaction_history_and_e2e_concurrency_tests [INFERRED 0.85]

## Communities (218 total, 144 thin omitted)

### Community 0 - "Product Catalogue"
Cohesion: 0.06
Nodes (9): ProductController, ProductController, ProductFilterRequest, SaveProductRequest, ProductResource, Product, {closure#4}(), CatalogApiTest (+1 more)

### Community 1 - "Shared Test Base"
Cohesion: 0.12
Nodes (12): ActivityLog, Category, ProductService, OrderService, TableSessionService, DiningTableService, PaymentMethodSeeder, RolesAndPermissionsSeeder (+4 more)

### Community 2 - "Payment Service Tests"
Cohesion: 0.06
Nodes (29): {closure#1}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}() (+21 more)

### Community 3 - "Order Service Tests"
Cohesion: 0.08
Nodes (28): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+20 more)

### Community 4 - "Billing Core Services"
Cohesion: 0.09
Nodes (17): ActivityLogger, BillService, PaymentService, ReceiptService, ApprovalService, PaymentCorrectionService, RefundService, VoidService (+9 more)

### Community 5 - "Category Management"
Cohesion: 0.06
Nodes (14): CategoryController, CategoryController, LogoutDeactivatedUsers, RejectDeactivatedApiUsers, {closure#1}(), {closure#1}(), SaveCategoryRequest, {closure#1}() (+6 more)

### Community 6 - "Web Routes and Controllers"
Cohesion: 0.07
Nodes (9): AuthenticatedSessionController, ProfileController, DashboardController, FloorController, {closure#1}(), RoleController, TableController, TransactionController (+1 more)

### Community 7 - "Permission Catalogue"
Cohesion: 0.04
Nodes (46): Permission, ApprovalsGrant, AttendanceManage, CashDrawerManageOwn, CashDrawerViewAll, CorrectionsCreate, DashboardCashier, DashboardOwner (+38 more)

### Community 8 - "Composer Config"
Cohesion: 0.04
Nodes (48): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+40 more)

### Community 9 - "API Routes and Controllers"
Cohesion: 0.07
Nodes (11): ActivityLogController, AuthController, {closure#2}(), MenuController, PaymentMethodController, RoleController, Controller, ActivityLogController (+3 more)

### Community 10 - "API Billing Endpoints"
Cohesion: 0.08
Nodes (10): ReceiptController, ActivityLogResource, MenuCategoryResource, OrderVoidResource, PaymentCorrectionResource, PaymentMethodResource, ReceiptResource, RefundResource (+2 more)

### Community 11 - "Void and Approval Tests"
Cohesion: 0.09
Nodes (11): ApprovalServiceTest, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+3 more)

### Community 12 - "Append-Only Money Records"
Cohesion: 0.10
Nodes (5): AppendOnly, OrderVoid, PaymentCorrection, ProductPriceHistory, Refund

### Community 13 - "Billing HTTP and Audit Tests"
Cohesion: 0.11
Nodes (3): BillingHttpTest, Phase2AuditTest, FixPassTest

### Community 14 - "Frontend Build and POS JS"
Cohesion: 0.08
Nodes (30): dependencies, alpinejs@^3.17.5, devDependencies, concurrently@^9.0.1, laravel-vite-plugin@^2.0.0, tailwindcss@^4.0.0, @tailwindcss/forms@^0.5.2, @tailwindcss/vite@^4.0.0 (+22 more)

### Community 15 - "Query Scopes and Transactions"
Cohesion: 0.10
Nodes (15): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#1}(), {closure#2}() (+7 more)

### Community 16 - "Seeders and Settings Tests"
Cohesion: 0.08
Nodes (7): CategorySeeder, DatabaseSeeder, OwnerSeeder, SampleTableSeeder, SettingsSeeder, SettingsTest, TableSeederTest

### Community 17 - "Bill Screen and Resource"
Cohesion: 0.14
Nodes (6): BillController, BillController, BillResource, PaymentResource, TableSession, {closure#1}()

### Community 18 - "User Management"
Cohesion: 0.11
Nodes (6): UserController, UserController, SaveUserRequest, UserResource, {closure#2}(), UserService

### Community 19 - "User Model and E2E Tests"
Cohesion: 0.09
Nodes (4): User, UserFactory, OpenTabWorkflowTest, TableApiTest

### Community 20 - "Table Session Tests"
Cohesion: 0.13
Nodes (9): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

### Community 22 - "Refund Service Tests"
Cohesion: 0.17
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+2 more)

### Community 23 - "Product Price History"
Cohesion: 0.15
Nodes (13): {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}() (+5 more)

### Community 24 - "Table Session API"
Cohesion: 0.13
Nodes (7): TableSessionController, {closure#2}(), {closure#3}(), {closure#6}(), {closure#7}(), PosController, TableSessionResource

### Community 25 - "Audited Soft Deletes"
Cohesion: 0.14
Nodes (7): {closure#1}(), {closure#2}(), {closure#3}(), HasAuditedSoftDeletes, AuditedSoftDeletesTest, {closure#1}(), SupplyItem

### Community 26 - "Payment Model and Corrections API"
Cohesion: 0.13
Nodes (4): Payment, {closure#1}(), {closure#1}(), CorrectionApiTest

### Community 27 - "Database Migrations"
Cohesion: 0.15
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+2 more)

### Community 28 - "Order Item Model"
Cohesion: 0.16
Nodes (7): OrderItem, {closure#3}(), {closure#3}(), {closure#4}(), {closure#5}(), itemCancelled(), quantityAdded()

### Community 29 - "Order Model"
Cohesion: 0.12
Nodes (5): OrderStatus, Cancelled, Open, Order, deductForOrder()

### Community 30 - "Payment Methods"
Cohesion: 0.18
Nodes (4): PaymentMethod, {closure#1}(), PaymentMethodService, TransactionFilterTest

### Community 31 - "Table Status Service"
Cohesion: 0.25
Nodes (3): {closure#1}(), {closure#2}(), TableStatusServiceTest

### Community 32 - "Correction API Responses"
Cohesion: 0.21
Nodes (3): CorrectionController, OrderController, OrderResource

### Community 33 - "Order Item API"
Cohesion: 0.17
Nodes (4): OrderItemController, CancelOrderItemRequest, UpdateOrderItemRequest, OrderItemResource

### Community 35 - "Catalogue Schema and Margins"
Cohesion: 0.14
Nodes (4): HasMargin, ProductVariant, CatalogSchemaTest, {closure#1}()

### Community 36 - "Voids Refunds Corrections Design"
Cohesion: 0.26
Nodes (15): RunningBill::calculate (gross, discounts, total, cancelled), TransactionFilterRequest (date, table, cashier, status, method), TableSession::visibleTo (view-own vs view-all), Voids, Refunds and Corrections Implementation Plan, ApprovalService (approve, setPin), Api V1 CorrectionController (void, refund, correction routes), PaymentCorrectionService::correct, RefundService::refund (+7 more)

### Community 38 - "Session Status and Concurrency"
Cohesion: 0.13
Nodes (5): SessionStatus, Billing, Cancelled, Closed, Open

### Community 39 - "Table Status Rules"
Cohesion: 0.18
Nodes (8): {closure#1}(), TableStatus, Available, AwaitingPayment, Cleaning, Occupied, Reserved, {closure#1}()

### Community 41 - "Running Bill and Tab Close"
Cohesion: 0.18
Nodes (6): {closure#1}(), {closure#3}(), {closure#2}(), {closure#6}(), {closure#2}(), {closure#1}()

### Community 42 - "Open Tab Design"
Cohesion: 0.23
Nodes (14): Open Tab (Table Sessions and Orders) Implementation Plan, BusinessDate::for (Manila date minus 06:00 cutoff), Open-tab API (sessions, orders, order-items routes), OrderService (addOrder, updateItem, cancelItem), SessionStatus enum (open, billing, closed, cancelled), StockHook (deductForOrder, itemCancelled; Phase 3 hook point), TableSessionService (open, cancel), Transaction History and POS End-to-End Tests Implementation Plan (+6 more)

### Community 45 - "Role Name"
Cohesion: 0.15
Nodes (8): RoleName, Bartender, Cashier, InventoryManager, Owner, Staff, Supervisor, UserPolicy

### Community 46 - "Schema"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 47 - "Payments Design (Session-Level, Close Only"
Cohesion: 0.19
Nodes (9): Business Day Cutoff (06:00) and BusinessDate, Cashier Shifts and Expected Drawer Cash, Entity-Relationship Diagram (all modules), Partial Unique Index table_sessions_one_open_per_table, ProfitLossService and P&L, Schedule Overlap Exclusion Constraint (btree_gist), BillService, TableSessionService / TableSession (+1 more)

### Community 48 - "Open Tab Flow"
Cohesion: 0.23
Nodes (4): chromium_path(), main(), run(), shot()

### Community 49 - "Payment Correction Service Test"
Cohesion: 0.27
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), PaymentCorrectionServiceTest

### Community 54 - "Changes From Ui Ux Pro"
Cohesion: 0.20
Nodes (8): YANGS Design System Master, Accessibility Checklist, Color Tokens (--color-*), Motion Rules (150ms, reduced motion), POS Layout (x-layouts.pos), 48px Touch Target (--spacing-touch), Typography (Barlow, Barlow Semi Condensed), Blade UI Components (resources/views/components/ui)

### Community 55 - "Consolidated Bill, Payments And Receipts"
Cohesion: 0.24
Nodes (11): PaymentService (record, settleFreeBill), product_price_histories (append-only price/cost history), AppendOnly trait and reject_append_only_change() trigger, PaymentService::closeTab (made public for voids), Consolidated Bill, Payments and Receipts Design, payments table (append-only, idempotency_key unique), Receipt number RCP-YYYYMMDD-NNNN (business day), reference_counters (prefix, business_date) (+3 more)

### Community 57 - "Table Controller"
Cohesion: 0.22
Nodes (3): TableController, ChangeTableStatusRequest, TableResource

### Community 59 - "Minimal Activity Logs Table And"
Cohesion: 0.25
Nodes (5): activity_logs DB Trigger (rejects UPDATE/DELETE), ActivityLog Eloquent Append-Only Guard, ActivityLogger Injectable Service, HasAuditedSoftDeletes Trait, Minimal activity_logs Table and ActivityLogger (Step 4)

### Community 61 - "Yangs Architecture And Database Design"
Cohesion: 0.25
Nodes (10): Open Questions (hosting, Senior/PWD, cutoff, payroll rules, precision), Phased Implementation Plan (8 phases, 46 steps), YANGS Architecture and Database Design, YANGS Module Map (Phases 1-2), YANGS Development Progress, Phases 3-8 Checklist (Inventory to Deployment), Phase 1 Foundation (checkpoint 2026-11-18), Phase 2 Checkpoint Audit (10 issues) (+2 more)

### Community 63 - "Payment Status"
Cohesion: 0.22
Nodes (7): PaymentStatus, Paid, Partial, PartiallyRefunded, Refunded, Unpaid, {closure#1}()

### Community 64 - "Yangs Bar Management And Pos"
Cohesion: 0.24
Nodes (9): Versioned REST API under /api/v1, YANGS Bar Management and POS System (project instructions), ActivityLogResource, API V1 ActivityLogController (GET /api/v1/activity-logs), Activity Log and Audit Foundation Plan, Login Rate Limit (5/min per username+IP), Authentication and RBAC Plan, Sanctum API Auth (login, logout, me) (+1 more)

### Community 65 - "Append-Only Tables With Rejecting Trigger"
Cohesion: 0.22
Nodes (7): Users vs Employees, ActivityLogger (only writer of activity_logs), API v1 (Sanctum, shared Form Requests and services), Deactivated User Blocking (LogoutDeactivatedUsers + Sanctum callback), HasAuditedSoftDeletes trait, ProductService and Price History, UserService

### Community 76 - "Manila, Bcmath)"
Cohesion: 0.28
Nodes (5): Currency PHP and Timezone Asia/Manila, config app.currency = PHP, Test Database Rename to yangs_test, EnvironmentTest (pgsql, DB name, Asia/Manila, bcmath), yangs_pos_test Database (original test DB)

### Community 77 - "Permission Enum (Module.Action Catalogue)"
Cohesion: 0.25
Nodes (7): Cashier Permission Set (POS only), OwnerSeeder from OWNER_* env, Permission Enum (module.action catalogue), Seeded Roles (Owner, Cashier, Supervisor, Bartender, Staff, Inventory Manager), DashboardController (owner, cashier, none empty states), Layouts (x-layouts.app, pos, guest), App\Support\Navigation (permission and Route::has filtered)

### Community 78 - "Stock Hook (Phase 3 Seam)"
Cohesion: 0.25
Nodes (7): Discounts (line or bill, percent or amount, frozen at close), Folder Structure (thin controllers, services own transactions), StockService (only writer of inventory_movements), LocksLiveSession (lockSession, ensureLive), OrderService (rounds with snapshots), StockHook (Phase 3 seam), Shared reference_counters (ORD, RCP)

### Community 79 - "Stock Deducted On Send Round"
Cohesion: 0.22
Nodes (3): Database Notifications (stock alerts, voids, pending payment, shifts), Owner Decisions (2026-11-18 Q&A), VAT (inclusive, frozen on session at close)

### Community 80 - "Billing, Payments And Receipts Implementation"
Cohesion: 0.25
Nodes (9): Billing, Payments and Receipts Implementation Plan, POS Bill Screen (Alpine bill, payment keypad, discount dialog), BillService (present, applyDiscount, removeDiscount), PaymentMethodService and PaymentMethodSeeder (Cash, GCash), ReceiptService (data, recordPrint) and 58/80 mm receipt view, ReferenceGenerator (shared prefix/business_date counter), SettingService (get, all, set with log), OrderReferenceGenerator (ORD-YYYYMMDD-NNNN atomic upsert) (+1 more)

### Community 86 - "Calculate"
Cohesion: 0.43
Nodes (3): {closure#1}(), {closure#1}(), {closure#2}()

### Community 87 - "Laravel-Permission (Roles Only, No Gate::Before)"
Cohesion: 0.29
Nodes (7): Management Layout (x-layouts.app), Supervisor PIN Approval, Design System, Layouts and x-ui Components, App\Enums\Permission (41 permissions), RolePermissionService (matrix editor), RouteGuardTest, Known Gaps (login hardening, GET bill mutates state, PIN guessing)

### Community 88 - "Categories And Products Implementation Plan"
Cohesion: 0.29
Nodes (7): Categories and Products Implementation Plan, CategoryService (create, update, archive, restore), Product margin (amount and percent, null when cost unknown), GET /api/v1/menu (POS menu, hides archived/unavailable), App\Support\Money (bcmath, half-up), ProductService (create, update, variant sync, archive), PosController@show (server-rendered POS bootstrap JSON)

### Community 89 - "Pos Ordering Ui Implementation Plan"
Cohesion: 0.36
Nodes (6): POS Ordering UI Implementation Plan, posScreen Alpine component (pos.js, pos/show.blade.php), RejectDeactivatedApiUsers middleware, Tablet browser test (Python Playwright open_tab_flow.py), Browser tests README, open_tab_flow.py (YANGS_SPEC 4B tablet walk-through)

### Community 90 - "Table Management Implementation Plan"
Cohesion: 0.36
Nodes (7): Table Management Implementation Plan, Cashier floor (/floor) with status cards and filter chips, DiningTableService (create, update, setActive), tables.update-status permission, TableStatus enum and transition rules, TableStatusService::change (sole status writer), Presenting the bill (open to billing, table Awaiting payment)

### Community 96 - "Foundation Setup Delta Plan"
Cohesion: 0.29
Nodes (5): Alpine.js Integration (Alpine.start once), /up Health Smoke Test, Foundation Setup Delta Plan, Eloquent Strict Mode Outside Production, Laravel 13 + PostgreSQL Setup Plan

### Community 97 - "User Service (Create, Update, Deactivation)"
Cohesion: 0.29
Nodes (4): EnsureUserIsActive Middleware (active), RolePermissionService (permission matrix sync), UserService (create, update, deactivation), UI Component Kit (x-ui.* anonymous Blade components)

### Community 98 - "Ui Work Rules (Master.Md, 48Px"
Cohesion: 0.33
Nodes (4): design-system/YANGS/MASTER.md, Design System and Base Layout Plan, Tablet Viewport Verification (768x1024, 1024x768, 1366x768), Tailwind 4 @theme Design Tokens (dark high-contrast)

### Community 99 - "Step 13 Transaction History And"
Cohesion: 0.29
Nodes (7): Test Suite (307 feature tests, E2E, concurrency, browser), TransactionController and TableSession::visibleTo, Fix: replay check before live-tab check, Step 13 Transaction History and POS End-to-End Tests, Local Setup on Windows (PHP 8.4, PostgreSQL 18, Node 24), SANCTUM_STATEFUL_DOMAINS for LAN Tablets, Tests run only against yangs_test

### Community 101 - "Product Type"
Cohesion: 0.33
Nodes (4): ProductType, Drink, Food, Other

### Community 106 - "0001 01 01 000002 Create"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 107 - "Close Tab"
Cohesion: 0.47
Nodes (5): PaymentService::record / closeTab, ReceiptService, RunningBill::calculate (single source of totals), SettingService and PaymentMethodService, Step 11 Billing, Payments and Receipts

### Community 108 - "Cashier Module"
Cohesion: 0.33
Nodes (6): Cashier Role, Financial Management, Inventory Management, Owner / Administrator Role, POS / Cashier Module, Table Management

### Community 109 - "Order Item Status"
Cohesion: 0.40
Nodes (3): OrderItemStatus, Active, Cancelled

### Community 119 - "Step 12 Voids, Refunds And"
Cohesion: 0.70
Nodes (5): ApprovalService (optional supervisor PIN), PaymentCorrectionService, RefundService, VoidService, Step 12 Voids, Refunds and Corrections

### Community 120 - "Activity Log Filter Scope (User,"
Cohesion: 0.40
Nodes (5): ActivityLogFilterRequest, activity_logs user_id/action Filter Indexes, ActivityLog filter Scope (user, action, Manila date range), logs.view Permission, Web ActivityLogController (index, show)

### Community 121 - "Yangs Bar Management And Pos"
Cohesion: 0.40
Nodes (5): Laravel, PostgreSQL, YANGS Bar Management and POS System, Role-Based Access Control (RBAC), RESTful API Architecture

## Ambiguous Edges - Review These
- `Concurrency: lockForUpdate, lock order session then inventory items` → `Shared reference_counters (ORD, RCP)`  [AMBIGUOUS]
  docs/PROGRESS.md · relation: semantically_similar_to

## Knowledge Gaps
- **133 isolated node(s):** `Active`, `Cancelled`, `Open`, `Cancelled`, `Unpaid` (+128 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 651 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **144 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User Model and E2E Tests` to `Product Catalogue`, `Shared Test Base`, `Payment Service Tests`, `Order Service Tests`, `Billing Core Services`, `Web Routes and Controllers`, `API Routes and Controllers`, `Void and Approval Tests`, `Append-Only Money Records`, `Billing HTTP and Audit Tests`, `Query Scopes and Transactions`, `Seeders and Settings Tests`, `Bill Screen and Resource`, `User Management`, `Table Session Tests`, `Validation Errors`, `Refund Service Tests`, `Product Price History`, `Audited Soft Deletes`, `Payment Model and Corrections API`, `Order Item Model`, `Payment Methods`, `Table Status Service`, `Form Requests`, `User Management Tests`, `Session Status and Concurrency`, `Running Bill and Tab Close`, `Role Permission Tests`, `Product Web Tests`, `Role Name`, `Payment Correction Service Test`, `Open Tab Api Test`, `Sub`, `Table Web Test`, `Navigation Test`, `Activity Log Test`, `Activity Log Viewer Test`, `Transaction Web Test`, `Order Schema Test`, `Log Authentication Events`, `Payment Method Test`, `Concurrency Test`, `Order Schema Test`, `Pos Screen Test`, `Calculate`, `Receipt Test`?**
  _High betweenness centrality (0.150) - this node is a cross-community bridge._
- **What connects `Active`, `Cancelled`, `Open` to the rest of the system?**
  _133 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Product Catalogue` be split into smaller, more focused modules?**
  _Cohesion score 0.05698778833107191 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Shared Test Base` to `Product Catalogue`, `Payment Service Tests`, `Order Service Tests`, `Billing Core Services`, `Void and Approval Tests`, `Billing HTTP and Audit Tests`, `Seeders and Settings Tests`, `User Model and E2E Tests`, `Example Test`, `Validation Errors`, `Refund Service Tests`, `Health Check Test`, `Table Session Tests`, `Audited Soft Deletes`, `Payment Model and Corrections API`, `Payment Methods`, `Table Status Service`, `Catalogue Schema and Margins`, `User Management Tests`, `Session Status and Concurrency`, `Role Permission Tests`, `Product Web Tests`, `Payment Correction Service Test`, `Open Tab Api Test`, `For`, `Table Web Test`, `Navigation Test`, `Activity Log Test`, `Component Test`, `Auth Api Test`, `Activity Log Viewer Test`, `Transaction Web Test`, `Order Schema Test`, `Seeder Test`, `Payment Method Test`, `Concurrency Test`, `Order Schema Test`, `Pos Screen Test`, `Authentication Test`, `Receipt Test`, `Dashboard Test`, `Environment Test`, `Route Guard Test`?**
  _High betweenness centrality (0.061) - this node is a cross-community bridge._
- **Should `Shared Test Base` be split into smaller, more focused modules?**
  _Cohesion score 0.11701631701631701 - nodes in this community are weakly interconnected._
- **Why does `TableSession` connect `Bill Screen and Resource` to `Shared Test Base`, `Payment Service Tests`, `Order Service Tests`, `Billing Core Services`, `Web Routes and Controllers`, `API Routes and Controllers`, `API Billing Endpoints`, `Void and Approval Tests`, `Append-Only Money Records`, `Billing HTTP and Audit Tests`, `Query Scopes and Transactions`, `Table Session Tests`, `Validation Errors`, `Refund Service Tests`, `Table Session API`, `Payment Model and Corrections API`, `Order Model`, `Payment Methods`, `Correction API Responses`, `Session Status and Concurrency`, `Running Bill and Tab Close`, `Payment Correction Service Test`, `Sub`, `Transaction Web Test`, `Order Schema Test`, `Concurrency Test`, `Order Schema Test`, `Pos Screen Test`, `Calculate`, `Receipt Test`, `Round`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `Concurrency: lockForUpdate, lock order session then inventory items` and `Shared reference_counters (ORD, RCP)`?**
  _Edge tagged AMBIGUOUS (relation: semantically_similar_to) - confidence is low._
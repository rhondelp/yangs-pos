# Graph Report - yangs-pos  (2026-11-19)

## Corpus Check
- 378 files · ~101,855 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 21 file(s) not represented in the graph (top: (none) 17, .example 1, .xml 1)

## Summary
- 2536 nodes · 6107 edges · 235 communities (90 shown, 145 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 467 edges (avg confidence: 0.86)
- Token cost: 524,819 input · 5,254 output

## Graph Freshness
- Built from commit: `b1475273`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Product Catalogue
- Shared Test Base
- Payment Service Tests
- Order Service Tests
- ActivityLogger
- Category
- DiningTable
- Permission Catalogue
- Composer Config
- api.php
- API Billing Endpoints
- Void and Approval Tests
- Append-Only Money Records
- FixPassTest
- Frontend Build and POS JS
- Query Scopes and Transactions
- Illuminate\Database\Seeder
- Bill Screen and Resource
- User Management
- User Model and E2E Tests
- Table Session Tests
- InventoryService.php
- Refund Service Tests
- Illuminate\Support\Facades\DB
- Table Session API
- HasAuditedSoftDeletes.php
- Payment Model and Corrections API
- Illuminate\Database\Schema\Blueprint
- Order Item Model
- Illuminate\Database\Eloquent\Model
- StockInTest.php
- TableStatus
- Illuminate\Http\JsonResponse
- Order Item API
- Supplier
- InventorySchemaTest.php
- Voids Refunds Corrections Design
- User Management Tests
- PaymentMethod
- Closure
- App Service Provider
- RunningBill
- Open Tab Design
- Role Permission Tests
- Product Web Tests
- Role Name
- 2026_11_18_171509_create_product_price_histories_table.php
- Payments Design (Session-Level, Close Only
- Open Tab Flow
- Payment Correction Service Test
- Open Tab Api Test
- Payment Method Controller
- RecipeService
- SessionStatus
- Changes From Ui Ux Pro
- Consolidated Bill, Payments And Receipts
- Table Web Test
- InventoryItemController
- Navigation Test
- Minimal Activity Logs Table And
- Illuminate\Database\Migrations\Migration
- Yangs Architecture And Database Design
- Activity Log Test
- Illuminate\Http\RedirectResponse
- Yangs Bar Management And Pos
- Append-Only Tables With Rejecting Trigger
- Component Test
- Auth Api Test
- Activity Log Viewer Test
- Transaction Web Test
- SettingService
- Seeder Test
- Update Settings Request
- ApprovalService
- web.php
- Money Test
- Manila, Bcmath)
- Permission Enum (Module.Action Catalogue)
- Stock Hook (Phase 3 Seam)
- Stock Deducted On Send Round
- Billing, Payments And Receipts Implementation
- Payment Method Test
- Concurrency Test
- InventoryItemService.php
- Pos Screen Test
- Controller
- VoidService.php
- Laravel-Permission (Roles Only, No Gate::Before)
- Categories And Products Implementation Plan
- Pos Ordering Ui Implementation Plan
- Table Management Implementation Plan
- Authentication Test
- Receipt Test
- Web/RoleController.php
- InventoryItem
- Illuminate\View\View
- Foundation Setup Delta Plan
- User Service (Create, Update, Deactivation)
- Ui Work Rules (Master.Md, 48Px
- Step 13 Transaction History And
- Dashboard Test
- Product Type
- TransactionController
- LoginRequest
- Illuminate\Foundation\Http\FormRequest
- Add Order Request
- 0001 01 01 000002 Create
- Close Tab
- Cashier Module
- InventoryItemService
- UserService
- Update Approval Pin Request
- V1/StockReceiptController.php
- 0001 01 01 000001 Create
- 2026 11 18 155107 Add
- 2026 11 18 161812 Add
- 2026 11 18 220143 Add
- 2026 11 19 100400 Add
- Step 12 Voids, Refunds And
- Activity Log Filter Scope (User,
- Yangs Bar Management And Pos
- Environment Test
- InventoryCatalogTest
- .saveUnits
- RefundService
- PaymentService
- Suppliers, Inventory Items and Stock-in Implementation Plan
- TableSessionResource
- 2026 11 18 155422 Create
- Illuminate\Support\Facades\Schema
- 2026 11 18 171504 Create
- 2026 11 18 171506 Create
- 2026 11 18 171507 Create
- CatalogApiTest
- 2026 11 18 202354 Create
- Phase2AuditTest
- 2026 11 18 202811 Create
- bootstrap/app.php
- 2026 11 19 100100 Create
- 2026 11 19 100200 Create
- 2026 11 19 100300 Create
- Illuminate\Http\Resources\Json\AnonymousResourceCollection
- ProductFilterRequest
- Show.Blade
- Edit.Blade
- InventoryApiTest
- SettingsTest
- 0001_01_01_000000_create_users_table.php
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
- TableApiTest
- V1/InventoryItemController.php
- 2026_11_19_100000_add_approval_pin_to_users_table.php
- AuditedSoftDeletesTest
- 2026_11_18_202809_create_table_sessions_table.php
- 2026_11_18_220140_create_payment_methods_table.php
- OpenTabWorkflowTest
- Quantity

## God Nodes (most connected - your core abstractions)
1. `User` - 173 edges
2. `TestCase` - 112 edges
3. `TableSession` - 109 edges
4. `Product` - 82 edges
5. `Controller` - 80 edges
6. `ActivityLogger` - 79 edges
7. `Permission` - 62 edges
8. `Category` - 59 edges
9. `DiningTable` - 54 edges
10. `ProductService` - 47 edges

## Surprising Connections (you probably didn't know these)
- `Global Constraints` --references--> `HasAuditedSoftDeletes`  [INFERRED]
  docs/superpowers/plans/2026-11-19-inventory-stock-in.md → app/Models/Concerns/HasAuditedSoftDeletes.php
- `Task 4: Screens` --references--> `Navigation`  [INFERRED]
  docs/superpowers/plans/2026-11-19-inventory-stock-in.md → app/Support/Navigation.php
- `Task 1: Schema and models` --references--> `InventoryItem`  [INFERRED]
  docs/superpowers/plans/2026-11-19-inventory-stock-in.md → app/Models/InventoryItem.php
- `Task 1: Schema and models` --references--> `InventoryMovement`  [INFERRED]
  docs/superpowers/plans/2026-11-19-inventory-stock-in.md → app/Models/InventoryMovement.php
- `Task 1: Schema and models` --references--> `StockReceipt`  [INFERRED]
  docs/superpowers/plans/2026-11-19-inventory-stock-in.md → app/Models/StockReceipt.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core Business Flow** — docs_yangs_spec_pos_module, docs_yangs_spec_inventory_management, docs_yangs_spec_financial_management [EXTRACTED 0.95]
- **Append-only correction services gated by optional approval PIN** — docs_superpowers_plans_2026_11_19_voids_refunds_corrections_voidservice, docs_superpowers_plans_2026_11_19_voids_refunds_corrections_refundservice, docs_superpowers_plans_2026_11_19_voids_refunds_corrections_paymentcorrectionservice, docs_superpowers_plans_2026_11_19_voids_refunds_corrections_approvalservice, docs_superpowers_plans_2026_11_19_voids_refunds_corrections_appendonly [EXTRACTED 1.00]
- **Inventory costing design (send-time deduction, WAC, recipes, precision)** — docs_architecture_stock_deduction_on_send, docs_architecture_weighted_average_cost, docs_architecture_recipe_components, docs_architecture_money_precision, docs_architecture_stockservice [EXTRACTED 1.00]
- **YANGS Blade layouts** — design_system_yangs_master_management_layout, design_system_yangs_master_pos_layout, design_system_yangs_master_guest_layout [EXTRACTED 1.00]
- **Technology Stack** — docs_yangs_spec_laravel, docs_yangs_spec_postgresql, docs_yangs_spec_tailwind_css, docs_yangs_spec_rest_api [EXTRACTED 1.00]
- **Two-layer append-only audit log (Eloquent guard + DB trigger + logger)** — docs_superpowers_plans_2026_11_18_audit_foundation_activitylog_append_only_guard, docs_superpowers_plans_2026_11_18_audit_foundation_activity_logs_trigger, docs_superpowers_plans_2026_11_18_audit_foundation_activitylogger_service, claude_append_only_records [INFERRED 0.85]
- **Append-only corrections gated by supervisor PIN** — docs_modules_voidservice, docs_modules_refundservice, docs_modules_paymentcorrectionservice, docs_modules_approvalservice, docs_architecture_append_only_tables [INFERRED 0.85]
- **Open-tab lifecycle: open, order, bill, pay, close** — docs_superpowers_plans_2026_11_18_open_tab_tablesessionservice, docs_superpowers_plans_2026_11_18_open_tab_orderservice, docs_superpowers_plans_2026_11_18_open_tab_runningbill, docs_superpowers_plans_2026_11_18_billing_payments_billservice, docs_superpowers_plans_2026_11_18_billing_payments_paymentservice, docs_superpowers_plans_2026_11_18_tables_tablestatusservice [INFERRED 0.85]
- **Payment recording and tab close flow** — docs_modules_paymentservice, docs_modules_lockslivesession, docs_modules_runningbill, docs_progress_idempotency_key, docs_modules_tablestatusservice [INFERRED 0.85]
- **Permission-driven access control (catalogue, roles, matrix, route guard, nav filter)** — docs_superpowers_plans_2026_11_18_auth_rbac_permission_enum, docs_superpowers_plans_2026_11_18_auth_rbac_seeded_roles, docs_superpowers_plans_2026_11_18_auth_rbac_rolepermissionservice, docs_superpowers_plans_2026_11_18_auth_rbac_route_guard_test, docs_superpowers_plans_2026_11_18_design_system_layout_navigation [INFERRED 0.85]
- **Phase 1 implementation plan series following the standard workflow** — claude_standard_workflow, docs_superpowers_plans_2026_11_18_laravel_postgres_setup_plan, docs_superpowers_plans_2026_11_18_foundation_setup_delta_plan, docs_superpowers_plans_2026_11_18_auth_rbac_plan, docs_superpowers_plans_2026_11_18_audit_foundation_plan, docs_superpowers_plans_2026_11_18_design_system_layout_plan [INFERRED 0.85]
- **Race-safety via DB locks, atomic counters and unique indexes** — docs_superpowers_plans_2026_11_18_open_tab_orderreferencegenerator, docs_superpowers_specs_2026_11_18_open_tab_design_one_open_tab_index, docs_superpowers_specs_2026_11_18_billing_payments_design_payment_idempotency, docs_superpowers_plans_2026_11_19_transaction_history_and_e2e_concurrency_tests [INFERRED 0.85]

## Communities (235 total, 145 thin omitted)

### Community 0 - "Product Catalogue"
Cohesion: 0.09
Nodes (7): ProductController, SaveProductRequest, ProductResource, Product, {closure#4}(), ProductService, ProductServiceTest

### Community 1 - "Shared Test Base"
Cohesion: 0.08
Nodes (12): ActivityLog, DiningTableService, RolesAndPermissionsSeeder, ActiveUserTest, PasswordUpdateTest, ExampleTest, HealthCheckTest, ProfileTest (+4 more)

### Community 2 - "Payment Service Tests"
Cohesion: 0.07
Nodes (26): {closure#1}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}() (+18 more)

### Community 3 - "Order Service Tests"
Cohesion: 0.07
Nodes (31): OrderStatus, Cancelled, Open, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}() (+23 more)

### Community 4 - "ActivityLogger"
Cohesion: 0.10
Nodes (6): LogAuthenticationEvents, ActivityLogger, {closure#1}(), TableStatusService, {closure#1}(), OwnerSeeder

### Community 5 - "Category"
Cohesion: 0.13
Nodes (7): CategoryController, CategoryController, SaveCategoryRequest, CategoryResource, Category, CategoryService, {closure#3}()

### Community 6 - "DiningTable"
Cohesion: 0.14
Nodes (3): TableController, SaveTableRequest, DiningTable

### Community 7 - "Permission Catalogue"
Cohesion: 0.04
Nodes (46): Permission, ApprovalsGrant, AttendanceManage, CashDrawerManageOwn, CashDrawerViewAll, CorrectionsCreate, DashboardCashier, DashboardOwner (+38 more)

### Community 8 - "Composer Config"
Cohesion: 0.04
Nodes (48): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+40 more)

### Community 10 - "API Billing Endpoints"
Cohesion: 0.07
Nodes (12): ActivityLogResource, InventoryMovementResource, MenuCategoryResource, OrderVoidResource, PaymentCorrectionResource, PaymentMethodResource, PaymentResource, ReceiptResource (+4 more)

### Community 11 - "Void and Approval Tests"
Cohesion: 0.06
Nodes (16): Order, VoidService, ApprovalServiceTest, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+8 more)

### Community 12 - "Append-Only Money Records"
Cohesion: 0.08
Nodes (7): AppendOnly, InventoryMovement, OrderVoid, PaymentCorrection, ProductPriceHistory, StockReceipt, StockReceiptItem

### Community 14 - "Frontend Build and POS JS"
Cohesion: 0.07
Nodes (32): dependencies, alpinejs@^3.17.5, devDependencies, concurrently@^9.0.1, laravel-vite-plugin@^2.0.0, tailwindcss@^4.0.0, @tailwindcss/forms@^0.5.2, @tailwindcss/vite@^4.0.0 (+24 more)

### Community 15 - "Query Scopes and Transactions"
Cohesion: 0.13
Nodes (15): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#1}(), {closure#2}() (+7 more)

### Community 16 - "Illuminate\Database\Seeder"
Cohesion: 0.19
Nodes (4): CategorySeeder, DatabaseSeeder, SampleTableSeeder, SettingsSeeder

### Community 17 - "Bill Screen and Resource"
Cohesion: 0.20
Nodes (6): BillController, BillResource, TableSession, BillService, LocksLiveSession, {closure#27}()

### Community 19 - "User Model and E2E Tests"
Cohesion: 0.09
Nodes (4): User, UserPolicy, UserFactory, TransactionFilterTest

### Community 20 - "Table Session Tests"
Cohesion: 0.13
Nodes (9): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

### Community 21 - "InventoryService.php"
Cohesion: 0.16
Nodes (3): {closure#2}(), {closure#4}(), ReferenceGenerator

### Community 22 - "Refund Service Tests"
Cohesion: 0.19
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+2 more)

### Community 23 - "Illuminate\Support\Facades\DB"
Cohesion: 0.09
Nodes (15): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#4}(), {closure#1}(), {closure#2}(), {closure#1}() (+7 more)

### Community 24 - "Table Session API"
Cohesion: 0.20
Nodes (5): {closure#2}(), {closure#3}(), {closure#6}(), {closure#7}(), PosController

### Community 25 - "HasAuditedSoftDeletes.php"
Cohesion: 0.27
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.16
Nodes (13): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#2}(), {closure#3}() (+5 more)

### Community 28 - "Order Item Model"
Cohesion: 0.12
Nodes (9): OrderItem, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), OrderService, StockHook, itemCancelled() (+1 more)

### Community 29 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.13
Nodes (4): HasAuditedSoftDeletes, HasMargin, ProductVariant, SupplyItem

### Community 30 - "StockInTest.php"
Cohesion: 0.09
Nodes (23): {closure#5}(), InventoryService, Data, Intent, Out of scope (later Phase 3 steps), Owner decisions, Permissions, Rules (+15 more)

### Community 31 - "TableStatus"
Cohesion: 0.08
Nodes (11): {closure#1}(), TableStatus, Available, AwaitingPayment, Cleaning, Occupied, Reserved, TableController (+3 more)

### Community 33 - "Order Item API"
Cohesion: 0.13
Nodes (5): OrderItemController, CancelOrderItemRequest, {closure#1}(), UpdateOrderItemRequest, OrderItemResource

### Community 34 - "Supplier"
Cohesion: 0.11
Nodes (9): SupplierController, SupplierController, SaveSupplierRequest, SupplierResource, Supplier, {closure#1}(), {closure#2}(), {closure#4}() (+1 more)

### Community 35 - "InventorySchemaTest.php"
Cohesion: 0.08
Nodes (3): CatalogSchemaTest, {closure#1}(), InventorySchemaTest

### Community 36 - "Voids Refunds Corrections Design"
Cohesion: 0.26
Nodes (15): RunningBill::calculate (gross, discounts, total, cancelled), TransactionFilterRequest (date, table, cashier, status, method), TableSession::visibleTo (view-own vs view-all), Voids, Refunds and Corrections Implementation Plan, ApprovalService (approve, setPin), Api V1 CorrectionController (void, refund, correction routes), PaymentCorrectionService::correct, RefundService::refund (+7 more)

### Community 38 - "PaymentMethod"
Cohesion: 0.11
Nodes (5): TableSessionController, PaymentMethod, TableSessionService, PaymentMethodSeeder, {closure#1}()

### Community 39 - "Closure"
Cohesion: 0.11
Nodes (7): LogoutDeactivatedUsers, RejectDeactivatedApiUsers, {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}()

### Community 41 - "RunningBill"
Cohesion: 0.11
Nodes (12): {closure#4}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#6}() (+4 more)

### Community 42 - "Open Tab Design"
Cohesion: 0.23
Nodes (14): Open Tab (Table Sessions and Orders) Implementation Plan, BusinessDate::for (Manila date minus 06:00 cutoff), Open-tab API (sessions, orders, order-items routes), OrderService (addOrder, updateItem, cancelItem), SessionStatus enum (open, billing, closed, cancelled), StockHook (deductForOrder, itemCancelled; Phase 3 hook point), TableSessionService (open, cancel), Transaction History and POS End-to-End Tests Implementation Plan (+6 more)

### Community 45 - "Role Name"
Cohesion: 0.15
Nodes (7): RoleName, Bartender, Cashier, InventoryManager, Owner, Staff, Supervisor

### Community 47 - "Payments Design (Session-Level, Close Only"
Cohesion: 0.19
Nodes (9): Business Day Cutoff (06:00) and BusinessDate, Cashier Shifts and Expected Drawer Cash, Entity-Relationship Diagram (all modules), Partial Unique Index table_sessions_one_open_per_table, ProfitLossService and P&L, Schedule Overlap Exclusion Constraint (btree_gist), BillService, TableSessionService / TableSession (+1 more)

### Community 48 - "Open Tab Flow"
Cohesion: 0.23
Nodes (4): chromium_path(), main(), run(), shot()

### Community 49 - "Payment Correction Service Test"
Cohesion: 0.32
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), PaymentCorrectionServiceTest

### Community 51 - "Payment Method Controller"
Cohesion: 0.18
Nodes (3): PaymentMethodController, SavePaymentMethodRequest, PaymentMethodService

### Community 52 - "RecipeService"
Cohesion: 0.16
Nodes (5): RecipeController, SaveRecipeRequest, RecipeComponentResource, {closure#1}(), RecipeService

### Community 53 - "SessionStatus"
Cohesion: 0.07
Nodes (18): PaymentStatus, Paid, Partial, PartiallyRefunded, Refunded, Unpaid, SessionStatus, Billing (+10 more)

### Community 54 - "Changes From Ui Ux Pro"
Cohesion: 0.20
Nodes (8): YANGS Design System Master, Accessibility Checklist, Color Tokens (--color-*), Motion Rules (150ms, reduced motion), POS Layout (x-layouts.pos), 48px Touch Target (--spacing-touch), Typography (Barlow, Barlow Semi Condensed), Blade UI Components (resources/views/components/ui)

### Community 55 - "Consolidated Bill, Payments And Receipts"
Cohesion: 0.24
Nodes (11): PaymentService (record, settleFreeBill), product_price_histories (append-only price/cost history), AppendOnly trait and reject_append_only_change() trigger, PaymentService::closeTab (made public for voids), Consolidated Bill, Payments and Receipts Design, payments table (append-only, idempotency_key unique), Receipt number RCP-YYYYMMDD-NNNN (business day), reference_counters (prefix, business_date) (+3 more)

### Community 57 - "InventoryItemController"
Cohesion: 0.19
Nodes (3): InventoryItemController, SaveInventoryItemRequest, InventoryItemResource

### Community 58 - "Navigation Test"
Cohesion: 0.13
Nodes (3): Navigation, InventoryWebTest, NavigationTest

### Community 59 - "Minimal Activity Logs Table And"
Cohesion: 0.25
Nodes (5): activity_logs DB Trigger (rejects UPDATE/DELETE), ActivityLog Eloquent Append-Only Guard, ActivityLogger Injectable Service, HasAuditedSoftDeletes Trait, Minimal activity_logs Table and ActivityLogger (Step 4)

### Community 61 - "Yangs Architecture And Database Design"
Cohesion: 0.25
Nodes (10): Open Questions (hosting, Senior/PWD, cutoff, payroll rules, precision), Phased Implementation Plan (8 phases, 46 steps), YANGS Architecture and Database Design, YANGS Module Map (Phases 1-2), YANGS Development Progress, Phases 3-8 Checklist (Inventory to Deployment), Phase 1 Foundation (checkpoint 2026-11-18), Phase 2 Checkpoint Audit (10 issues) (+2 more)

### Community 64 - "Yangs Bar Management And Pos"
Cohesion: 0.24
Nodes (9): Versioned REST API under /api/v1, YANGS Bar Management and POS System (project instructions), ActivityLogResource, API V1 ActivityLogController (GET /api/v1/activity-logs), Activity Log and Audit Foundation Plan, Login Rate Limit (5/min per username+IP), Authentication and RBAC Plan, Sanctum API Auth (login, logout, me) (+1 more)

### Community 65 - "Append-Only Tables With Rejecting Trigger"
Cohesion: 0.22
Nodes (7): Users vs Employees, ActivityLogger (only writer of activity_logs), API v1 (Sanctum, shared Form Requests and services), Deactivated User Blocking (LogoutDeactivatedUsers + Sanctum callback), HasAuditedSoftDeletes trait, ProductService and Price History, UserService

### Community 70 - "SettingService"
Cohesion: 0.25
Nodes (3): BillController, ReceiptService, SettingService

### Community 73 - "ApprovalService"
Cohesion: 0.20
Nodes (3): ApprovalService, {closure#1}(), PaymentCorrectionService

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

### Community 83 - "InventoryItemService.php"
Cohesion: 0.19
Nodes (7): InventoryUnit, RecipeComponent, {closure#1}(), {closure#6}(), {closure#7}(), {closure#2}(), Task 1: Schema and models

### Community 85 - "Controller"
Cohesion: 0.11
Nodes (9): {closure#2}(), MenuController, ReceiptController, {closure#1}(), PasswordController, Controller, FloorController, ReceiptController (+1 more)

### Community 86 - "VoidService.php"
Cohesion: 0.20
Nodes (7): OrderItemStatus, Active, Cancelled, {closure#1}(), {closure#2}(), {closure#3}(), {closure#5}()

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

### Community 93 - "Web/RoleController.php"
Cohesion: 0.16
Nodes (6): RoleController, {closure#1}(), RoleController, UpdateRolePermissionsRequest, {closure#2}(), RolePermissionService

### Community 95 - "Illuminate\View\View"
Cohesion: 0.23
Nodes (3): ProfileController, {closure#1}(), StockReceiptController

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
Cohesion: 0.25
Nodes (4): ProductType, Drink, Food, Other

### Community 102 - "TransactionController"
Cohesion: 0.13
Nodes (4): TransactionController, CorrectPaymentRequest, RefundRequest, TransactionFilterRequest

### Community 103 - "LoginRequest"
Cohesion: 0.11
Nodes (3): AuthenticatedSessionController, LoginRequest, RouteGuardTest

### Community 104 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.07
Nodes (7): UpdateApprovalPinRequest, ApplyDiscountRequest, RecordPaymentRequest, VoidItemRequest, VoidOrderRequest, StockReceiptRequest, CancelSessionRequest

### Community 105 - "Add Order Request"
Cohesion: 0.24
Nodes (3): OrderController, AddOrderRequest, OrderResource

### Community 106 - "0001 01 01 000002 Create"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 107 - "Close Tab"
Cohesion: 0.47
Nodes (5): PaymentService::record / closeTab, ReceiptService, RunningBill::calculate (single source of totals), SettingService and PaymentMethodService, Step 11 Billing, Payments and Receipts

### Community 108 - "Cashier Module"
Cohesion: 0.33
Nodes (6): Cashier Role, Financial Management, Inventory Management, Owner / Administrator Role, POS / Cashier Module, Table Management

### Community 109 - "InventoryItemService"
Cohesion: 0.18
Nodes (6): {closure#2}(), {closure#4}(), InventoryItemService, {closure#4}(), {closure#5}(), {closure#6}()

### Community 110 - "UserService"
Cohesion: 0.12
Nodes (5): AuthController, UserController, LoginRequest, UserResource, UserService

### Community 119 - "Step 12 Voids, Refunds And"
Cohesion: 0.70
Nodes (5): ApprovalService (optional supervisor PIN), PaymentCorrectionService, RefundService, VoidService, Step 12 Voids, Refunds and Corrections

### Community 120 - "Activity Log Filter Scope (User,"
Cohesion: 0.40
Nodes (5): ActivityLogFilterRequest, activity_logs user_id/action Filter Indexes, ActivityLog filter Scope (user, action, Manila date range), logs.view Permission, Web ActivityLogController (index, show)

### Community 121 - "Yangs Bar Management And Pos"
Cohesion: 0.40
Nodes (5): Laravel, PostgreSQL, YANGS Bar Management and POS System, Role-Based Access Control (RBAC), RESTful API Architecture

### Community 124 - ".saveUnits"
Cohesion: 0.22
Nodes (4): SaveInventoryUnitsRequest, {closure#1}(), {closure#2}(), {closure#3}()

### Community 126 - "PaymentService"
Cohesion: 0.36
Nodes (3): PaymentService, {closure#19}(), {closure#7}()

### Community 127 - "Suppliers, Inventory Items and Stock-in Implementation Plan"
Cohesion: 0.22
Nodes (8): Global Constraints, Review Focus, Suppliers, Inventory Items and Stock-in Implementation Plan, Task 2: Suppliers, items, units, recipes, Task 3: Stock-in, Task 4: Screens, Task 5: API, Task 6: Verify

### Community 130 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 138 - "bootstrap/app.php"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 151 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

## Ambiguous Edges - Review These
- `Shared reference_counters (ORD, RCP)` → `Concurrency: lockForUpdate, lock order session then inventory items`  [AMBIGUOUS]
  docs/PROGRESS.md · relation: semantically_similar_to

## Knowledge Gaps
- **134 isolated node(s):** `Active`, `Cancelled`, `Open`, `Cancelled`, `Unpaid` (+129 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 747 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **145 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User Model and E2E Tests` to `Product Catalogue`, `Shared Test Base`, `Payment Service Tests`, `Order Service Tests`, `ActivityLogger`, `CatalogApiTest`, `Phase2AuditTest`, `Void and Approval Tests`, `Append-Only Money Records`, `FixPassTest`, `Query Scopes and Transactions`, `Bill Screen and Resource`, `User Management`, `Table Session Tests`, `InventoryService.php`, `Refund Service Tests`, `Illuminate\Support\Facades\DB`, `InventoryApiTest`, `SettingsTest`, `Payment Model and Corrections API`, `Order Item Model`, `StockInTest.php`, `TableStatus`, `InventorySchemaTest.php`, `User Management Tests`, `PaymentMethod`, `RunningBill`, `Role Permission Tests`, `Product Web Tests`, `Payment Correction Service Test`, `Open Tab Api Test`, `SessionStatus`, `Table Web Test`, `Navigation Test`, `Activity Log Test`, `Activity Log Viewer Test`, `Transaction Web Test`, `SettingService`, `ApprovalService`, `Payment Method Test`, `Concurrency Test`, `Pos Screen Test`, `VoidService.php`, `TableApiTest`, `Authentication Test`, `Receipt Test`, `InventoryCatalogTest.php`, `OpenTabWorkflowTest`, `TransactionController`, `UserService`, `Update Approval Pin Request`, `RefundService`, `PaymentService`?**
  _High betweenness centrality (0.165) - this node is a cross-community bridge._
- **What connects `Active`, `Cancelled`, `Open` to the rest of the system?**
  _134 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Product Catalogue` be split into smaller, more focused modules?**
  _Cohesion score 0.09219858156028368 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Shared Test Base` to `Product Catalogue`, `Payment Service Tests`, `Order Service Tests`, `CatalogApiTest`, `Phase2AuditTest`, `Void and Approval Tests`, `FixPassTest`, `User Model and E2E Tests`, `InventoryApiTest`, `Table Session Tests`, `Refund Service Tests`, `SettingsTest`, `Payment Model and Corrections API`, `StockInTest.php`, `TableStatus`, `InventorySchemaTest.php`, `User Management Tests`, `PaymentMethod`, `Role Permission Tests`, `Product Web Tests`, `Payment Correction Service Test`, `Open Tab Api Test`, `SessionStatus`, `Table Web Test`, `Navigation Test`, `Activity Log Test`, `Component Test`, `Auth Api Test`, `Activity Log Viewer Test`, `Transaction Web Test`, `Seeder Test`, `Payment Method Test`, `Concurrency Test`, `Pos Screen Test`, `TableApiTest`, `Authentication Test`, `Receipt Test`, `AuditedSoftDeletesTest`, `InventoryCatalogTest.php`, `OpenTabWorkflowTest`, `Dashboard Test`, `LoginRequest`, `Update Approval Pin Request`, `Environment Test`, `InventoryCatalogTest`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._
- **Should `Shared Test Base` be split into smaller, more focused modules?**
  _Cohesion score 0.08234126984126984 - nodes in this community are weakly interconnected._
- **Why does `Product` connect `Product Catalogue` to `Shared Test Base`, `Payment Service Tests`, `Order Service Tests`, `CatalogApiTest`, `Phase2AuditTest`, `Void and Approval Tests`, `Append-Only Money Records`, `FixPassTest`, `Query Scopes and Transactions`, `User Model and E2E Tests`, `Illuminate\Support\Facades\DB`, `Order Item Model`, `Illuminate\Database\Eloquent\Model`, `InventorySchemaTest.php`, `PaymentMethod`, `Product Web Tests`, `Open Tab Api Test`, `RecipeService`, `Illuminate\Http\RedirectResponse`, `Transaction Web Test`, `InventoryItemService.php`, `Pos Screen Test`, `Controller`, `Receipt Test`, `InventoryCatalogTest.php`, `OpenTabWorkflowTest`, `Update Approval Pin Request`?**
  _High betweenness centrality (0.036) - this node is a cross-community bridge._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `Shared reference_counters (ORD, RCP)` and `Concurrency: lockForUpdate, lock order session then inventory items`?**
  _Edge tagged AMBIGUOUS (relation: semantically_similar_to) - confidence is low._
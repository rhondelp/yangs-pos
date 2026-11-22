# Graph Report - yangs-pos  (2026-11-22)

## Corpus Check
- 404 files · ~116,768 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 21 file(s) not represented in the graph (top: (none) 17, .example 1, .xml 1)

## Summary
- 2828 nodes · 6957 edges · 246 communities (94 shown, 152 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 547 edges (avg confidence: 0.87)
- Token cost: 524,819 input · 5,254 output

## Graph Freshness
- Built from commit: `50982bbd`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- ProductServiceTest
- Shared Test Base
- Payment Service Tests
- Order Service Tests
- ActivityLogger
- Category
- TableSession
- Permission Catalogue
- Composer Config
- Controller
- API Billing Endpoints
- ApprovalServiceTest
- Illuminate\Database\Eloquent\Model
- BillingHttpTest
- Frontend Build and POS JS
- Query Scopes and Transactions
- Illuminate\Database\Seeder
- Bill Screen and Resource
- SaveUserRequest
- OrderServiceTest.php
- Table Session Tests
- InventoryLevelFilterRequest
- Refund Service Tests
- Illuminate\Support\Facades\DB
- SessionStatus
- App\Models\Concerns\HasAuditedSoftDeletes
- App\Services\Corrections\RefundService
- Illuminate\Database\Schema\Blueprint
- InventoryItemService
- App\Http\Resources\V1\InventoryMovementResource
- StockInTest.php
- App\Http\Controllers\Web\FloorController
- Illuminate\Http\JsonResponse
- .update
- Supplier
- InventoryLevelsTest
- Voids Refunds Corrections Design
- User Management Tests
- App\Models\User
- OrderSchemaTest.php
- App Service Provider
- RunningBill
- Open Tab Design
- Role Permission Tests
- Product Web Tests
- RoleName
- 2026_11_18_171509_create_product_price_histories_table.php
- Payments Design (Session-Level, Close Only
- Open Tab Flow
- Payment Correction Service Test
- Open Tab Api Test
- Payment Method Controller
- Product
- ReferenceGenerator
- Changes From Ui Ux Pro
- Consolidated Bill, Payments And Receipts
- Table Web Test
- SaveInventoryItemRequest
- Navigation Test
- Minimal Activity Logs Table And
- Illuminate\Database\Migrations\Migration
- Yangs Architecture And Database Design
- InventorySchemaTest.php
- 0001_01_01_000000_create_users_table.php
- Yangs Bar Management And Pos
- Append-Only Tables With Rejecting Trigger
- Component Test
- Auth Api Test
- Activity Log Viewer Test
- MoneyTest
- Illuminate\Support\Facades\DB
- Illuminate\Foundation\Testing\RefreshDatabase
- Update Settings Request
- InventoryMovement
- Controller
- Closure
- Manila, Bcmath)
- Permission Enum (Module.Action Catalogue)
- Stock Hook (Phase 3 Seam)
- Stock Deducted On Send Round
- Billing, Payments And Receipts Implementation
- Payment Method Test
- Concurrency Test
- .levels
- DiningTable
- TableStatus
- .change
- Laravel-Permission (Roles Only, No Gate::Before)
- Categories And Products Implementation Plan
- Pos Ordering Ui Implementation Plan
- Table Management Implementation Plan
- App\Http\Resources\V1\OrderResource
- Receipt Test
- Web/RoleController.php
- SessionStatus
- InventoryService
- Foundation Setup Delta Plan
- User Service (Create, Update, Deactivation)
- Ui Work Rules (Master.Md, 48Px
- Step 13 Transaction History And
- TableStatusService
- StockAlertsTest
- .refund
- LoginRequest
- Illuminate\Foundation\Http\FormRequest
- Add Order Request
- 0001 01 01 000002 Create
- Close Tab
- Cashier Module
- StockOutTest.php
- SettingsTest
- InventoryCheckCommand.php
- User
- PaymentStatus
- 2026 11 18 155107 Add
- 2026 11 18 161812 Add
- 2026 11 18 220143 Add
- 2026 11 19 100400 Add
- Step 12 Voids, Refunds And
- Activity Log Filter Scope (User,
- Yangs Bar Management And Pos
- Environment Test
- InventoryItemController
- .units
- SaleDeductionTest
- bootstrap/app.php
- UpdateApprovalPinRequest
- MovementHistoryTest
- Illuminate\Support\Facades\Schema
- 2026_11_18_155845_create_personal_access_tokens_table.php
- App\Http\Controllers\Api\V1\StockReceiptController
- 2026 11 18 171506 Create
- VoidOrderRequest
- CatalogApiTest
- Step 16 – Inventory monitoring and reports (design)
- StockOutRequest
- 2026 11 18 202811 Create
- ReceiptService.php
- 2026 11 19 100100 Create
- 2026 11 19 100200 Create
- 2026 11 19 100300 Create
- Suppliers, Inventory Items and Stock-in – Design
- 2026_11_18_171504_create_categories_table.php
- Show.Blade
- Edit.Blade
- InventoryApiTest
- InventorySchemaTest.php
- Edit.Blade
- Index.Blade
- bill.blade.php
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
- Illuminate\Http\Resources\Json\AnonymousResourceCollection
- 2026_11_19_100000_add_approval_pin_to_users_table.php
- App\Http\Controllers\Web\ProductController
- 2026_11_19_210000_add_stock_tracking_to_order_items_and_voids.php
- 2026_11_18_202809_create_table_sessions_table.php
- RecordPaymentRequest
- .stockValue
- InventoryItem
- inventory._tabs
- Stock-out and Automatic Sale Deduction – Design
- .reject
- StockReceiptRequest
- AuthenticatedSessionController
- .store
- Inventory Monitoring and Reports Implementation Plan
- 2026_11_18_174621_create_dining_tables_table.php
- 2026_11_18_220142_create_payments_table.php
- movements.blade.php

## God Nodes (most connected - your core abstractions)
1. `User` - 147 edges
2. `TestCase` - 102 edges
3. `TableSession` - 91 edges
4. `Product` - 74 edges
5. `Controller` - 70 edges
6. `ActivityLogger` - 64 edges
7. `Permission` - 61 edges
8. `Category` - 54 edges
9. `DiningTable` - 49 edges
10. `InventoryItem` - 48 edges

## Surprising Connections (you probably didn't know these)
- `Global Constraints` --references--> `HasAuditedSoftDeletes`  [INFERRED]
  docs/superpowers/plans/2026-11-19-inventory-stock-in.md → app/Models/Concerns/HasAuditedSoftDeletes.php
- `Task 4: Screens` --references--> `Navigation`  [INFERRED]
  docs/superpowers/plans/2026-11-19-inventory-stock-in.md → app/Support/Navigation.php
- `Goals` --references--> `UnusualAdjustmentRecorded`  [INFERRED]
  docs/superpowers/specs/2026-11-20-inventory-monitoring-design.md → app/Events/UnusualAdjustmentRecorded.php
- `Rules` --references--> `InventoryService`  [INFERRED]
  docs/superpowers/specs/2026-11-19-stock-out-and-sale-deduction-design.md → app/Services/Inventory/InventoryService.php
- `Suppliers, Inventory Items and Stock-in Implementation Plan` --references--> `InventoryItemService`  [INFERRED]
  docs/superpowers/plans/2026-11-19-inventory-stock-in.md → app/Services/Inventory/InventoryItemService.php

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

## Communities (246 total, 152 thin omitted)

### Community 1 - "Shared Test Base"
Cohesion: 0.14
Nodes (12): ActivityLog, Category, PaymentMethod, PaymentService, ProductService, TableSessionService, DiningTableService, PaymentMethodSeeder (+4 more)

### Community 2 - "Payment Service Tests"
Cohesion: 0.07
Nodes (27): {closure#1}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}() (+19 more)

### Community 3 - "Order Service Tests"
Cohesion: 0.07
Nodes (28): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+20 more)

### Community 4 - "ActivityLogger"
Cohesion: 0.12
Nodes (4): LogAuthenticationEvents, ActivityLogger, ApprovalService, {closure#1}()

### Community 5 - "Category"
Cohesion: 0.08
Nodes (14): CategoryController, CategoryController, SaveCategoryRequest, CategoryResource, CategoryService, {closure#4}(), Global Constraints, Review Focus (+6 more)

### Community 6 - "TableSession"
Cohesion: 0.19
Nodes (6): ReceiptController, ReceiptController, TableSession, ReceiptService, LocksLiveSession, {closure#1}()

### Community 7 - "Permission Catalogue"
Cohesion: 0.04
Nodes (45): Permission, ApprovalsGrant, AttendanceManage, CashDrawerManageOwn, CashDrawerViewAll, CorrectionsCreate, DashboardCashier, DashboardOwner (+37 more)

### Community 8 - "Composer Config"
Cohesion: 0.04
Nodes (48): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+40 more)

### Community 9 - "Controller"
Cohesion: 0.07
Nodes (10): ActivityLogController, {closure#2}(), MenuController, PaymentMethodController, RoleController, StockReceiptController, TableSessionController, Controller (+2 more)

### Community 10 - "API Billing Endpoints"
Cohesion: 0.07
Nodes (13): ActivityLogResource, InventoryItemResource, MenuCategoryResource, OrderResource, OrderVoidResource, PaymentCorrectionResource, PaymentMethodResource, ReceiptResource (+5 more)

### Community 12 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.06
Nodes (14): AppendOnly, HasAuditedSoftDeletes, HasMargin, InventoryUnit, OrderVoid, PaymentCorrection, ProductPriceHistory, ProductVariant (+6 more)

### Community 13 - "BillingHttpTest"
Cohesion: 0.08
Nodes (4): BillingHttpTest, Phase2AuditTest, FixPassTest, StockReturnTest

### Community 14 - "Frontend Build and POS JS"
Cohesion: 0.07
Nodes (32): dependencies, alpinejs@^3.17.5, devDependencies, concurrently@^9.0.1, laravel-vite-plugin@^2.0.0, tailwindcss@^4.0.0, @tailwindcss/forms@^0.5.2, @tailwindcss/vite@^4.0.0 (+24 more)

### Community 15 - "Query Scopes and Transactions"
Cohesion: 0.07
Nodes (28): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), TransactionController, TransactionFilterRequest (+20 more)

### Community 16 - "Illuminate\Database\Seeder"
Cohesion: 0.15
Nodes (6): CategorySeeder, DatabaseSeeder, {closure#1}(), OwnerSeeder, SampleTableSeeder, SettingsSeeder

### Community 17 - "Bill Screen and Resource"
Cohesion: 0.21
Nodes (5): BillController, BillController, BillResource, PaymentResource, BillService

### Community 18 - "SaveUserRequest"
Cohesion: 0.09
Nodes (6): AuthController, UserController, UserController, SaveUserRequest, UserResource, UserService

### Community 19 - "OrderServiceTest.php"
Cohesion: 0.09
Nodes (13): OrderStatus, Cancelled, Open, OrderItem, {closure#1}(), {closure#2}(), {closure#3}(), {closure#5}() (+5 more)

### Community 20 - "Table Session Tests"
Cohesion: 0.15
Nodes (9): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

### Community 22 - "Refund Service Tests"
Cohesion: 0.19
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+2 more)

### Community 23 - "Illuminate\Support\Facades\DB"
Cohesion: 0.12
Nodes (12): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}() (+4 more)

### Community 24 - "SessionStatus"
Cohesion: 0.14
Nodes (6): {closure#2}(), {closure#3}(), {closure#6}(), {closure#7}(), PosController, TableSessionResource

### Community 25 - "App\Models\Concerns\HasAuditedSoftDeletes"
Cohesion: 0.43
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 26 - "App\Services\Corrections\RefundService"
Cohesion: 0.27
Nodes (3): Refund, {closure#1}(), RefundService

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.12
Nodes (17): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#2}(), {closure#3}() (+9 more)

### Community 28 - "InventoryItemService"
Cohesion: 0.21
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), InventoryCatalogTest

### Community 30 - "StockInTest.php"
Cohesion: 0.17
Nodes (13): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+5 more)

### Community 32 - "Illuminate\Http\JsonResponse"
Cohesion: 0.15
Nodes (5): CorrectionController, OrderController, OrderItemController, CancelOrderItemRequest, OrderItemResource

### Community 34 - "Supplier"
Cohesion: 0.11
Nodes (9): SupplierController, SupplierController, SaveSupplierRequest, SupplierResource, Supplier, {closure#1}(), {closure#2}(), {closure#4}() (+1 more)

### Community 36 - "Voids Refunds Corrections Design"
Cohesion: 0.26
Nodes (15): RunningBill::calculate (gross, discounts, total, cancelled), TransactionFilterRequest (date, table, cashier, status, method), TableSession::visibleTo (view-own vs view-all), Voids, Refunds and Corrections Implementation Plan, ApprovalService (approve, setPin), Api V1 CorrectionController (void, refund, correction routes), PaymentCorrectionService::correct, RefundService::refund (+7 more)

### Community 38 - "App\Models\User"
Cohesion: 0.08
Nodes (4): Phase3AuditTest, {closure#6}(), SellsTrackedProducts, InventoryConsistencyCheckTest

### Community 39 - "OrderSchemaTest.php"
Cohesion: 0.08
Nodes (11): Order, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#9}() (+3 more)

### Community 41 - "RunningBill"
Cohesion: 0.17
Nodes (8): {closure#2}(), {closure#3}(), {closure#9}(), {closure#3}(), {closure#4}(), {closure#5}(), RunningBill, Money

### Community 42 - "Open Tab Design"
Cohesion: 0.23
Nodes (14): Open Tab (Table Sessions and Orders) Implementation Plan, BusinessDate::for (Manila date minus 06:00 cutoff), Open-tab API (sessions, orders, order-items routes), OrderService (addOrder, updateItem, cancelItem), SessionStatus enum (open, billing, closed, cancelled), StockHook (deductForOrder, itemCancelled; Phase 3 hook point), TableSessionService (open, cancel), Transaction History and POS End-to-End Tests Implementation Plan (+6 more)

### Community 45 - "RoleName"
Cohesion: 0.17
Nodes (9): RoleName, Bartender, Cashier, InventoryManager, Owner, Staff, Supervisor, {closure#1}() (+1 more)

### Community 47 - "Payments Design (Session-Level, Close Only"
Cohesion: 0.19
Nodes (9): Business Day Cutoff (06:00) and BusinessDate, Cashier Shifts and Expected Drawer Cash, Entity-Relationship Diagram (all modules), Partial Unique Index table_sessions_one_open_per_table, ProfitLossService and P&L, Schedule Overlap Exclusion Constraint (btree_gist), BillService, TableSessionService / TableSession (+1 more)

### Community 48 - "Open Tab Flow"
Cohesion: 0.23
Nodes (4): chromium_path(), main(), run(), shot()

### Community 49 - "Payment Correction Service Test"
Cohesion: 0.09
Nodes (9): Payment, {closure#1}(), PaymentCorrectionService, CorrectionApiTest, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}() (+1 more)

### Community 51 - "Payment Method Controller"
Cohesion: 0.16
Nodes (4): PaymentMethodController, SavePaymentMethodRequest, {closure#1}(), PaymentMethodService

### Community 52 - "Product"
Cohesion: 0.15
Nodes (6): RecipeController, ProductController, RecipeController, SaveRecipeRequest, Product, RecipeService

### Community 54 - "Changes From Ui Ux Pro"
Cohesion: 0.20
Nodes (8): YANGS Design System Master, Accessibility Checklist, Color Tokens (--color-*), Motion Rules (150ms, reduced motion), POS Layout (x-layouts.pos), 48px Touch Target (--spacing-touch), Typography (Barlow, Barlow Semi Condensed), Blade UI Components (resources/views/components/ui)

### Community 55 - "Consolidated Bill, Payments And Receipts"
Cohesion: 0.24
Nodes (11): PaymentService (record, settleFreeBill), product_price_histories (append-only price/cost history), AppendOnly trait and reject_append_only_change() trigger, PaymentService::closeTab (made public for voids), Consolidated Bill, Payments and Receipts Design, payments table (append-only, idempotency_key unique), Receipt number RCP-YYYYMMDD-NNNN (business day), reference_counters (prefix, business_date) (+3 more)

### Community 58 - "Navigation Test"
Cohesion: 0.13
Nodes (3): Navigation, InventoryWebTest, NavigationTest

### Community 59 - "Minimal Activity Logs Table And"
Cohesion: 0.25
Nodes (5): activity_logs DB Trigger (rejects UPDATE/DELETE), ActivityLog Eloquent Append-Only Guard, ActivityLogger Injectable Service, HasAuditedSoftDeletes Trait, Minimal activity_logs Table and ActivityLogger (Step 4)

### Community 61 - "Yangs Architecture And Database Design"
Cohesion: 0.25
Nodes (10): Open Questions (hosting, Senior/PWD, cutoff, payroll rules, precision), Phased Implementation Plan (8 phases, 46 steps), YANGS Architecture and Database Design, YANGS Module Map (Phases 1-2), YANGS Development Progress, Phases 3-8 Checklist (Inventory to Deployment), Phase 1 Foundation (checkpoint 2026-11-18), Phase 2 Checkpoint Audit (10 issues) (+2 more)

### Community 62 - "InventorySchemaTest.php"
Cohesion: 0.07
Nodes (4): ActivityLogTest, AuditedSoftDeletesTest, CatalogSchemaTest, {closure#1}()

### Community 63 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.16
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}()

### Community 64 - "Yangs Bar Management And Pos"
Cohesion: 0.24
Nodes (9): Versioned REST API under /api/v1, YANGS Bar Management and POS System (project instructions), ActivityLogResource, API V1 ActivityLogController (GET /api/v1/activity-logs), Activity Log and Audit Foundation Plan, Login Rate Limit (5/min per username+IP), Authentication and RBAC Plan, Sanctum API Auth (login, logout, me) (+1 more)

### Community 65 - "Append-Only Tables With Rejecting Trigger"
Cohesion: 0.22
Nodes (7): Users vs Employees, ActivityLogger (only writer of activity_logs), API v1 (Sanctum, shared Form Requests and services), Deactivated User Blocking (LogoutDeactivatedUsers + Sanctum callback), HasAuditedSoftDeletes trait, ProductService and Price History, UserService

### Community 70 - "Illuminate\Support\Facades\DB"
Cohesion: 0.10
Nodes (4): {closure#3}(), OrderService, SettingService, BusinessDate

### Community 71 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.05
Nodes (11): ActiveUserTest, AuthenticationTest, PasswordUpdateTest, OpenTabWorkflowTest, ExampleTest, HealthCheckTest, ProfileTest, SeederTest (+3 more)

### Community 73 - "InventoryMovement"
Cohesion: 0.17
Nodes (7): StockLevelCrossed, UnusualAdjustmentRecorded, InventoryMovement, Task 4: Unusual stock-outs and stock-level crossing events, {closure#1}(), {closure#2}(), {closure#3}()

### Community 74 - "Controller"
Cohesion: 0.07
Nodes (7): ProfileController, DashboardController, SettingsController, {closure#1}(), StockReceiptController, TableController, SaveTableRequest

### Community 75 - "Closure"
Cohesion: 0.13
Nodes (6): {closure#1}(), {closure#1}(), {closure#2}(), {closure#4}(), {closure#1}(), {closure#1}()

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

### Community 84 - "DiningTable"
Cohesion: 0.12
Nodes (3): FloorController, DiningTable, PosScreenTest

### Community 85 - "TableStatus"
Cohesion: 0.13
Nodes (9): {closure#1}(), TableStatus, Available, AwaitingPayment, Cleaning, Occupied, Reserved, ChangeTableStatusRequest (+1 more)

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

### Community 91 - "App\Http\Resources\V1\OrderResource"
Cohesion: 0.25
Nodes (4): OrderItemStatus, Active, Cancelled, {closure#4}()

### Community 93 - "Web/RoleController.php"
Cohesion: 0.14
Nodes (7): {closure#1}(), {closure#2}(), RoleController, UpdateRolePermissionsRequest, {closure#1}(), {closure#2}(), RolePermissionService

### Community 94 - "SessionStatus"
Cohesion: 0.29
Nodes (5): SessionStatus, Billing, Cancelled, Closed, Open

### Community 95 - "InventoryService"
Cohesion: 0.07
Nodes (19): {closure#11}(), {closure#2}(), {closure#7}(), InventoryService, {closure#1}(), StockHook, {closure#1}(), Global Constraints (+11 more)

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

### Community 104 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.10
Nodes (5): LoginRequest, ApplyDiscountRequest, CorrectPaymentRequest, VoidItemRequest, CancelSessionRequest

### Community 106 - "0001 01 01 000002 Create"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 107 - "Close Tab"
Cohesion: 0.47
Nodes (5): PaymentService::record / closeTab, ReceiptService, RunningBill::calculate (single source of totals), SettingService and PaymentMethodService, Step 11 Billing, Payments and Receipts

### Community 108 - "Cashier Module"
Cohesion: 0.33
Nodes (6): Cashier Role, Financial Management, Inventory Management, Owner / Administrator Role, POS / Cashier Module, Table Management

### Community 109 - "StockOutTest.php"
Cohesion: 0.18
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), StockOutTest

### Community 112 - "User"
Cohesion: 0.08
Nodes (5): User, UserPolicy, UserFactory, TransactionFilterTest, TransactionWebTest

### Community 114 - "PaymentStatus"
Cohesion: 0.12
Nodes (14): PaymentStatus, Paid, Partial, PartiallyRefunded, Refunded, Unpaid, {closure#1}(), {closure#1}() (+6 more)

### Community 119 - "Step 12 Voids, Refunds And"
Cohesion: 0.70
Nodes (5): ApprovalService (optional supervisor PIN), PaymentCorrectionService, RefundService, VoidService, Step 12 Voids, Refunds and Corrections

### Community 120 - "Activity Log Filter Scope (User,"
Cohesion: 0.40
Nodes (5): ActivityLogFilterRequest, activity_logs user_id/action Filter Indexes, ActivityLog filter Scope (user, action, Manila date range), logs.view Permission, Web ActivityLogController (index, show)

### Community 121 - "Yangs Bar Management And Pos"
Cohesion: 0.40
Nodes (5): Laravel, PostgreSQL, YANGS Bar Management and POS System, Role-Based Access Control (RBAC), RESTful API Architecture

### Community 126 - "bootstrap/app.php"
Cohesion: 0.18
Nodes (5): LogoutDeactivatedUsers, RejectDeactivatedApiUsers, {closure#1}(), {closure#2}(), {closure#3}()

### Community 129 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.12
Nodes (4): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 135 - "Step 16 – Inventory monitoring and reports (design)"
Cohesion: 0.25
Nodes (7): Anti-goals, Goals, Intent, Left to the builder, Non-goals, Step 16 – Inventory monitoring and reports (design), Tests (feature)

### Community 143 - "Suppliers, Inventory Items and Stock-in – Design"
Cohesion: 0.25
Nodes (7): Data, Intent, Out of scope (later Phase 3 steps), Owner decisions, Permissions, Screens and API, Suppliers, Inventory Items and Stock-in – Design

### Community 219 - "Illuminate\Http\Resources\Json\AnonymousResourceCollection"
Cohesion: 0.16
Nodes (6): InventoryReportController, {closure#1}(), InventoryMovementController, MovementFilterRequest, InventoryReportService, Task 3: Movement history page, Adjustments tab and movements API

### Community 221 - "App\Http\Controllers\Web\ProductController"
Cohesion: 0.08
Nodes (8): ProductType, Drink, Food, Other, ProductController, ProductFilterRequest, SaveProductRequest, ProductResource

### Community 222 - "2026_11_19_210000_add_stock_tracking_to_order_items_and_voids.php"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 226 - "InventoryItem"
Cohesion: 0.06
Nodes (18): {closure#1}(), {closure#1}(), {closure#5}(), InventoryItemController, InventoryItem, {closure#2}(), {closure#4}(), {closure#5}() (+10 more)

### Community 235 - "Stock-out and Automatic Sale Deduction – Design"
Cohesion: 0.33
Nodes (5): Out of scope, Owner decisions, Rules, Screens and API, Stock-out and Automatic Sale Deduction – Design

### Community 239 - "AuthenticatedSessionController"
Cohesion: 0.15
Nodes (3): {closure#1}(), PasswordController, RouteGuardTest

### Community 241 - "Inventory Monitoring and Reports Implementation Plan"
Cohesion: 0.25
Nodes (7): {closure#7}(), Global Constraints, Inventory Monitoring and Reports Implementation Plan, Review Focus, Task 2: Report service, stock-levels page and valuation API, Task 5: Verify, screens, docs, Design

## Ambiguous Edges - Review These
- `Shared reference_counters (ORD, RCP)` → `Concurrency: lockForUpdate, lock order session then inventory items`  [AMBIGUOUS]
  docs/PROGRESS.md · relation: semantically_similar_to

## Knowledge Gaps
- **136 isolated node(s):** `inventory._tabs`, `pos._draft`, `pos._tab`, `profile.partials.approval-pin-form`, `profile.partials.update-password-form` (+131 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 814 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **152 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Shared Test Base`, `Payment Service Tests`, `Order Service Tests`, `ActivityLogger`, `TableSession`, `CatalogApiTest`, `ReceiptService.php`, `ApprovalServiceTest`, `Illuminate\Database\Eloquent\Model`, `BillingHttpTest`, `Query Scopes and Transactions`, `Illuminate\Database\Seeder`, `Bill Screen and Resource`, `SaveUserRequest`, `Table Session Tests`, `InventoryApiTest`, `Refund Service Tests`, `Illuminate\Support\Facades\DB`, `InventorySchemaTest.php`, `App\Services\Corrections\RefundService`, `StockInTest.php`, `User Management Tests`, `App\Models\User`, `OrderSchemaTest.php`, `RunningBill`, `Role Permission Tests`, `Product Web Tests`, `RoleName`, `Payment Correction Service Test`, `Open Tab Api Test`, `Table Web Test`, `Navigation Test`, `InventorySchemaTest.php`, `Auth Api Test`, `Activity Log Viewer Test`, `Illuminate\Support\Facades\DB`, `Illuminate\Foundation\Testing\RefreshDatabase`, `Payment Method Test`, `DiningTable`, `.change`, `TableApiTest`, `Receipt Test`, `PaymentStatus`?**
  _High betweenness centrality (0.107) - this node is a cross-community bridge._
- **What connects `inventory._tabs`, `pos._draft`, `pos._tab` to the rest of the system?**
  _136 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Shared Test Base` be split into smaller, more focused modules?**
  _Cohesion score 0.14431082331174838 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `ProductServiceTest`, `Shared Test Base`, `Payment Service Tests`, `Order Service Tests`, `CatalogApiTest`, `ApprovalServiceTest`, `Illuminate\Database\Eloquent\Model`, `BillingHttpTest`, `InventoryApiTest`, `Table Session Tests`, `Refund Service Tests`, `InventorySchemaTest.php`, `StockInTest.php`, `User Management Tests`, `App\Models\User`, `OrderSchemaTest.php`, `Role Permission Tests`, `Product Web Tests`, `Payment Correction Service Test`, `Open Tab Api Test`, `ReferenceGenerator`, `Table Web Test`, `Navigation Test`, `InventorySchemaTest.php`, `Component Test`, `Auth Api Test`, `Activity Log Viewer Test`, `Payment Method Test`, `DiningTable`, `.change`, `TableApiTest`, `Receipt Test`, `InventoryService`, `AuthenticatedSessionController`, `User`, `Environment Test`?**
  _High betweenness centrality (0.044) - this node is a cross-community bridge._
- **Should `Payment Service Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.07199032062915911 - nodes in this community are weakly interconnected._
- **Why does `TableSession` connect `TableSession` to `Shared Test Base`, `Payment Service Tests`, `Order Service Tests`, `Controller`, `ReceiptService.php`, `Illuminate\Database\Eloquent\Model`, `BillingHttpTest`, `Query Scopes and Transactions`, `Bill Screen and Resource`, `OrderServiceTest.php`, `Table Session Tests`, `Refund Service Tests`, `SessionStatus`, `App\Services\Corrections\RefundService`, `OrderSchemaTest.php`, `RunningBill`, `Payment Correction Service Test`, `Illuminate\Support\Facades\DB`, `DiningTable`, `Receipt Test`, `User`, `PaymentStatus`?**
  _High betweenness centrality (0.041) - this node is a cross-community bridge._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `Shared reference_counters (ORD, RCP)` and `Concurrency: lockForUpdate, lock order session then inventory items`?**
  _Edge tagged AMBIGUOUS (relation: semantically_similar_to) - confidence is low._
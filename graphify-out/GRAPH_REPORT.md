# Graph Report - yangs-pos  (2026-11-19)

## Corpus Check
- 286 files · ~71,382 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 21 file(s) not represented in the graph (top: (none) 17, .example 1, .xml 1)

## Summary
- 1831 nodes · 4101 edges · 180 communities (60 shown, 120 thin omitted)
- Extraction: 90% EXTRACTED · 10% INFERRED · 0% AMBIGUOUS · INFERRED: 400 edges (avg confidence: 0.88)
- Token cost: 227,375 input · 2,059 output

## Graph Freshness
- Built from commit: `591c444a`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- User
- Composer Config
- Permission Catalogue
- Illuminate\Database\Schema\Blueprint
- ActivityLogViewerTest
- RolePermissionTest
- SeederTest.php
- LoginRequest
- Frontend Build Config
- User Management Tests
- Illuminate\View\View
- Web User Management
- YANGS – Module Map (Phase 1)
- Architecture and Progress Docs
- Controller
- Design System Docs
- Setup Plans and README
- Stock and Costing Design
- PasswordController.php
- Activity Log Tests
- UserService (create, update, deactivation)
- UI Component Tests
- API Auth Tests
- Illuminate\Http\Request
- Append-Only Audit Design
- Environment and Money Rules
- Permission-Aware Navigation
- Finance Design
- POS Tables and Payments Design
- TestCase
- OrderServiceTest.php
- Product
- Auth and RBAC Plan
- CategoryService
- ActivityLogger
- .log
- Payment and Discount Design
- Illuminate\Database\Eloquent\Model
- TableSessionServiceTest
- Illuminate\Foundation\Testing\RefreshDatabase
- Spec Modules and Roles
- api.php
- PaymentServiceTest
- Order
- Spec Stack
- TableSession
- Illuminate\Database\Eloquent\Relations\BelongsTo
- Money
- Profile Views
- User Create View
- User Edit View
- Guest Layout Doc
- Spec Audit Section
- Spec Payroll Section
- Tailwind Concept
- Robots File
- PaymentServiceTest.php
- Illuminate\Database\Seeder
- OrderSchemaTest.php
- Illuminate\Foundation\Http\FormRequest
- Illuminate\Database\Eloquent\Builder
- .change
- FloorController.php
- CatalogSchemaTest.php
- TableStatus
- ProductWebTest
- Task 5: API
- HasAuditedSoftDeletes
- RoleName
- PosController.php
- PaymentService
- PaymentMethodTest
- BillingHttpTest
- OpenTabApiTest
- TableWebTest
- SessionStatus
- Tasks
- PaymentMethod
- TableController
- RolePermissionService
- SettingService
- .for
- UpdateOrderItemRequest
- UpdateSettingsRequest
- .applyDiscount
- Illuminate\Support\Facades\Schema
- CatalogApiTest
- PosScreenTest
- ReferenceGenerator
- Illuminate\Database\Migrations\Migration
- ReceiptTest
- SettingsTest
- Open Tab (Table Sessions and Orders) – Design
- OrderItemStatus
- ProductType
- TableSessionController.php
- RecordPaymentRequest
- AddOrderRequest
- 0001_01_01_000002_create_jobs_table.php
- Roles and permissions (RBAC)
- TableApiTest
- UI Work Rules (MASTER.md, 48px targets, tablet orientations)
- 0001_01_01_000001_create_cache_table.php
- 2026_11_18_155107_add_username_and_is_active_to_users_table.php
- 2026_11_18_161812_add_filter_indexes_to_activity_logs_table.php
- 2026_11_18_220143_add_billing_columns_to_table_sessions_table.php
- OwnerSeeder.php
- LoginRequest
- 2026_11_18_155845_create_personal_access_tokens_table.php
- 2026_11_18_171504_create_categories_table.php
- 2026_11_18_171506_create_products_table.php
- 2026_11_18_171507_create_product_variants_table.php
- 2026_11_18_174621_create_dining_tables_table.php
- 2026_11_18_202354_create_order_reference_counters_table.php
- 2026_11_18_202809_create_table_sessions_table.php
- 2026_11_18_202810_create_orders_table.php
- 2026_11_18_215642_create_settings_table.php
- 2026_11_18_220140_create_payment_methods_table.php
- 2026_11_18_220142_create_payments_table.php
- pos/show.blade.php
- payment-methods/edit.blade.php
- payment-methods/index.blade.php
- products/create.blade.php
- products/edit.blade.php
- tables/create.blade.php
- tables/edit.blade.php

## God Nodes (most connected - your core abstractions)
1. `User` - 113 edges
2. `TestCase` - 82 edges
3. `TableSession` - 74 edges
4. `Product` - 63 edges
5. `Permission` - 62 edges
6. `Controller` - 61 edges
7. `ActivityLogger` - 56 edges
8. `DiningTable` - 49 edges
9. `Category` - 45 edges
10. `ProductService` - 37 edges

## Surprising Connections (you probably didn't know these)
- `Rules` --references--> `TableStatusService`  [INFERRED]
  docs/superpowers/specs/2026-11-18-open-tab-design.md → app/Services/Tables/TableStatusService.php
- `Tests` --references--> `TestCase`  [INFERRED]
  docs/MODULES.md → tests/TestCase.php
- `Task 2: Schema, enums and models` --references--> `OrderItemStatus`  [INFERRED]
  docs/superpowers/plans/2026-11-18-open-tab.md → app/Enums/OrderItemStatus.php
- `Task 2: Schema, enums and models` --references--> `PaymentStatus`  [INFERRED]
  docs/superpowers/plans/2026-11-18-open-tab.md → app/Enums/PaymentStatus.php
- `Roles and permissions (RBAC)` --references--> `Permission`  [INFERRED]
  docs/MODULES.md → app/Enums/Permission.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core Business Flow** — docs_yangs_spec_pos_module, docs_yangs_spec_inventory_management, docs_yangs_spec_financial_management [EXTRACTED 0.95]
- **Payment recording and session close flow** — docs_architecture_payment_service, docs_architecture_table_sessions, docs_architecture_payments_table, docs_architecture_cashier_shifts, docs_architecture_vat, docs_architecture_discounts, docs_architecture_activity_logger [EXTRACTED 1.00]
- **Stock deduction and WAC costing flow** — docs_architecture_stock_service, docs_architecture_stock_deduction_on_send, docs_architecture_weighted_average_cost, docs_architecture_recipe_components, docs_architecture_inventory_movements, docs_architecture_order_items [EXTRACTED 1.00]
- **YANGS Blade layouts** — design_system_yangs_master_management_layout, design_system_yangs_master_pos_layout, design_system_yangs_master_guest_layout [EXTRACTED 1.00]
- **Technology Stack** — docs_yangs_spec_laravel, docs_yangs_spec_postgresql, docs_yangs_spec_tailwind_css, docs_yangs_spec_rest_api [EXTRACTED 1.00]
- **Two-layer append-only audit log (Eloquent guard + DB trigger + logger)** — docs_superpowers_plans_2026_11_18_audit_foundation_activitylog_append_only_guard, docs_superpowers_plans_2026_11_18_audit_foundation_activity_logs_trigger, docs_superpowers_plans_2026_11_18_audit_foundation_activitylogger_service, claude_append_only_records [INFERRED 0.85]
- **Permission-driven access control (catalogue, roles, matrix, route guard, nav filter)** — docs_superpowers_plans_2026_11_18_auth_rbac_permission_enum, docs_superpowers_plans_2026_11_18_auth_rbac_seeded_roles, docs_superpowers_plans_2026_11_18_auth_rbac_rolepermissionservice, docs_superpowers_plans_2026_11_18_auth_rbac_route_guard_test, docs_superpowers_plans_2026_11_18_design_system_layout_navigation [INFERRED 0.85]
- **Phase 1 implementation plan series following the standard workflow** — claude_standard_workflow, docs_superpowers_plans_2026_11_18_laravel_postgres_setup_plan, docs_superpowers_plans_2026_11_18_foundation_setup_delta_plan, docs_superpowers_plans_2026_11_18_auth_rbac_plan, docs_superpowers_plans_2026_11_18_audit_foundation_plan, docs_superpowers_plans_2026_11_18_design_system_layout_plan [INFERRED 0.85]

## Communities (180 total, 120 thin omitted)

### Community 0 - "User"
Cohesion: 0.09
Nodes (5): User, UserPolicy, Navigation, UserFactory, NavigationTest

### Community 1 - "Composer Config"
Cohesion: 0.04
Nodes (48): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+40 more)

### Community 2 - "Permission Catalogue"
Cohesion: 0.04
Nodes (43): Permission, ApprovalsGrant, AttendanceManage, CashDrawerManageOwn, CashDrawerViewAll, DashboardCashier, DashboardOwner, EmployeesManage (+35 more)

### Community 3 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.21
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 8 - "Frontend Build Config"
Cohesion: 0.07
Nodes (34): Global Constraints, POS Ordering UI Implementation Plan, Rulings, Tasks, dependencies, alpinejs@^3.17.5, devDependencies, concurrently@^9.0.1 (+26 more)

### Community 10 - "Illuminate\View\View"
Cohesion: 0.09
Nodes (6): AuthenticatedSessionController, DashboardController, PaymentMethodController, RoleController, PaymentMethodService, UI: design system, layouts, dashboards

### Community 11 - "Web User Management"
Cohesion: 0.12
Nodes (6): UserController, UserController, SaveUserRequest, UserResource, UserService, User management

### Community 12 - "YANGS – Module Map (Phase 1)"
Cohesion: 0.05
Nodes (16): LogoutDeactivatedUsers, RejectDeactivatedApiUsers, {closure#1}(), {closure#1}(), {closure#1}(), AppServiceProvider, {closure#1}(), {closure#1}() (+8 more)

### Community 13 - "Architecture and Progress Docs"
Cohesion: 0.15
Nodes (14): Management Layout (x-layouts.app), YANGS Architecture and Database Design, Entity-Relationship Diagram, Open Questions (hosting, Senior/PWD, payroll rules), payroll_records table, Phased Implementation Plan (46 steps, 8 phases), Users vs Employees, YANGS Development Progress (+6 more)

### Community 14 - "Controller"
Cohesion: 0.09
Nodes (10): ActivityLogController, MenuController, PaymentMethodController, RoleController, TableController, Controller, ActivityLogController, ActivityLogFilterRequest (+2 more)

### Community 15 - "Design System Docs"
Cohesion: 0.19
Nodes (9): YANGS Design System Master, Accessibility Checklist, Color Tokens (--color-*), Motion Rules (150ms, reduced motion), POS Layout (x-layouts.pos), 48px Touch Target (--spacing-touch), Typography (Barlow, Barlow Semi Condensed), Blade UI Components (resources/views/components/ui) (+1 more)

### Community 16 - "Setup Plans and README"
Cohesion: 0.20
Nodes (9): Alpine.js Integration (Alpine.start once), /up Health Smoke Test, Foundation Setup Delta Plan, Eloquent Strict Mode Outside Production, Laravel 13 + PostgreSQL Setup Plan, Local Setup on Windows (PowerShell), Stack: Laravel 13, PostgreSQL 18, Blade, Tailwind 4 (Vite), Alpine.js, YANGS README (+1 more)

### Community 17 - "Stock and Costing Design"
Cohesion: 0.23
Nodes (8): inventory_items table, inventory_movements ledger, Database Notifications (stock, voids, pending payment), order_item_voids table, order_items table (price/cost snapshots), stock_receipts table, StockService (sole writer of inventory movements), Phase 3 Inventory

### Community 20 - "UserService (create, update, deactivation)"
Cohesion: 0.22
Nodes (5): EnsureUserIsActive Middleware (active), RolePermissionService (permission matrix sync), UserService (create, update, deactivation), UI Component Kit (x-ui.* anonymous Blade components), Tailwind 4 @theme Design Tokens (dark high-contrast)

### Community 23 - "Illuminate\Http\Request"
Cohesion: 0.07
Nodes (9): ProfileController, ActivityLogResource, MenuCategoryResource, OrderResource, PaymentMethodResource, ReceiptResource, RoleResource, VariantResource (+1 more)

### Community 24 - "Append-Only Audit Design"
Cohesion: 0.25
Nodes (5): activity_logs DB Trigger (rejects UPDATE/DELETE), ActivityLog Eloquent Append-Only Guard, ActivityLogger Injectable Service, HasAuditedSoftDeletes Trait, Minimal activity_logs Table and ActivityLogger (Step 4)

### Community 25 - "Environment and Money Rules"
Cohesion: 0.28
Nodes (5): Currency PHP and Timezone Asia/Manila, config app.currency = PHP, Test Database Rename to yangs_test, EnvironmentTest (pgsql, DB name, Asia/Manila, bcmath), yangs_pos_test Database (original test DB)

### Community 26 - "Permission-Aware Navigation"
Cohesion: 0.20
Nodes (9): logs.view Permission, Web ActivityLogController (index, show), Cashier Permission Set (POS only), OwnerSeeder from OWNER_* env, Permission Enum (module.action catalogue), Seeded Roles (Owner, Cashier, Supervisor, Bartender, Staff, Inventory Manager), DashboardController (owner, cashier, none empty states), Layouts (x-layouts.app, pos, guest) (+1 more)

### Community 27 - "Finance Design"
Cohesion: 0.22
Nodes (5): cash_movements table, Cashier Shifts and Expected Drawer Cash, expenses table, Money and BusinessDate support classes, Phase 4 Financial Management

### Community 28 - "POS Tables and Payments Design"
Cohesion: 0.31
Nodes (6): ActivityLogger Service, activity_logs table, dining_tables table, payments table, refunds table, table_sessions table

### Community 29 - "TestCase"
Cohesion: 0.07
Nodes (8): ActiveUserTest, AuthenticationTest, ExampleTest, HealthCheckTest, ProfileTest, TableSeederTest, DashboardTest, TestCase

### Community 30 - "OrderServiceTest.php"
Cohesion: 0.06
Nodes (38): OrderItem, {closure#1}(), {closure#4}(), {closure#5}(), OrderService, StockHook, Open Tab (Table Sessions and Orders) Implementation Plan, Task 4: OrderService and RunningBill (+30 more)

### Community 31 - "Product"
Cohesion: 0.07
Nodes (9): ProductController, ProductController, ProductFilterRequest, SaveProductRequest, ProductResource, Product, {closure#4}(), ProductService (+1 more)

### Community 32 - "Auth and RBAC Plan"
Cohesion: 0.19
Nodes (12): Versioned REST API under /api/v1, YANGS Bar Management and POS System (project instructions), ActivityLogFilterRequest, ActivityLogResource, API V1 ActivityLogController (GET /api/v1/activity-logs), activity_logs user_id/action Filter Indexes, ActivityLog filter Scope (user, action, Manila date range), Activity Log and Audit Foundation Plan (+4 more)

### Community 33 - "CategoryService"
Cohesion: 0.08
Nodes (15): CategoryController, CategoryController, SaveCategoryRequest, CategoryResource, CategoryService, {closure#4}(), Categories and Products Implementation Plan, Global Constraints (+7 more)

### Community 34 - "ActivityLogger"
Cohesion: 0.11
Nodes (10): LogAuthenticationEvents, ActivityLogger, LocksLiveSession, {closure#2}(), RunningBill, TableStatusService, BusinessDate, Global Constraints (+2 more)

### Community 35 - ".log"
Cohesion: 0.09
Nodes (13): {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}() (+5 more)

### Community 36 - "Payment and Discount Design"
Cohesion: 0.33
Nodes (6): Supervisor PIN Approval (ApprovalService), Discounts (line/bill, percent/amount, frozen at close), Owner Decisions (Q&A 2026-11-18), PaymentService::record(), App\Enums\Permission catalogue, VAT Setting (inclusive, frozen at close)

### Community 37 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.15
Nodes (4): AuditedSoftDeletesTest, {closure#1}(), SupplyItem, EnvironmentTest

### Community 38 - "TableSessionServiceTest"
Cohesion: 0.13
Nodes (9): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

### Community 39 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.17
Nodes (7): Category, DiningTable, TableSessionService, DiningTableService, RolesAndPermissionsSeeder, Task 2: TableStatusService and table service, {closure#1}()

### Community 40 - "Spec Modules and Roles"
Cohesion: 0.33
Nodes (6): Cashier Role, Financial Management, Inventory Management, Owner / Administrator Role, POS / Cashier Module, Table Management

### Community 41 - "api.php"
Cohesion: 0.11
Nodes (5): AuthController, OrderController, OrderItemController, OrderItemResource, Authentication

### Community 44 - "Order"
Cohesion: 0.11
Nodes (8): OrderStatus, Cancelled, Open, {closure#2}(), Order, {closure#3}(), Task 2: Schema, enums and models, deductForOrder()

### Community 45 - "Spec Stack"
Cohesion: 0.40
Nodes (5): Laravel, PostgreSQL, YANGS Bar Management and POS System, Role-Based Access Control (RBAC), RESTful API Architecture

### Community 46 - "TableSession"
Cohesion: 0.23
Nodes (6): BillController, BillController, BillResource, PaymentResource, TableSession, BillService

### Community 47 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.13
Nodes (3): AppendOnly, Payment, ProductPriceHistory

### Community 49 - "Money"
Cohesion: 0.10
Nodes (14): PaymentStatus, Paid, Partial, Unpaid, {closure#2}(), {closure#1}(), {closure#3}(), {closure#3}() (+6 more)

### Community 89 - "PaymentServiceTest.php"
Cohesion: 0.16
Nodes (16): {closure#1}(), {closure#12}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#18}(), {closure#2}() (+8 more)

### Community 96 - "Illuminate\Database\Seeder"
Cohesion: 0.14
Nodes (9): CategorySeeder, DatabaseSeeder, SampleTableSeeder, SettingsSeeder, Task 2: Schema, models, seeder, Review Focus, Task 1: Schema, enum, permission, seeders, Task 3: Web (+1 more)

### Community 99 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.17
Nodes (7): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 100 - ".change"
Cohesion: 0.23
Nodes (4): {closure#1}(), {closure#1}(), {closure#2}(), TableStatusServiceTest

### Community 101 - "FloorController.php"
Cohesion: 0.17
Nodes (3): FloorController, ChangeTableStatusRequest, TableFilterRequest

### Community 102 - "CatalogSchemaTest.php"
Cohesion: 0.14
Nodes (4): HasMargin, ProductVariant, CatalogSchemaTest, {closure#1}()

### Community 103 - "TableStatus"
Cohesion: 0.18
Nodes (8): {closure#1}(), TableStatus, Available, AwaitingPayment, Cleaning, Occupied, Reserved, {closure#1}()

### Community 105 - "Task 5: API"
Cohesion: 0.15
Nodes (7): CancelSessionRequest, OpenSessionRequest, Review Focus, Task 1: Business date and order references, Task 3: TableSessionService and StockHook, Task 5: API, Task 6: Docs

### Community 106 - "HasAuditedSoftDeletes"
Cohesion: 0.22
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), HasAuditedSoftDeletes

### Community 107 - "RoleName"
Cohesion: 0.17
Nodes (9): RoleName, Bartender, Cashier, InventoryManager, Owner, Staff, Supervisor, {closure#1}() (+1 more)

### Community 108 - "PosController.php"
Cohesion: 0.20
Nodes (5): {closure#2}(), {closure#3}(), {closure#6}(), {closure#7}(), PosController

### Community 109 - "PaymentService"
Cohesion: 0.24
Nodes (7): PaymentService, Consolidated Bill, Payments and Receipts – Design, Data, Out of scope, Rules, {closure#19}(), {closure#7}()

### Community 114 - "SessionStatus"
Cohesion: 0.18
Nodes (7): SessionStatus, Billing, Cancelled, Closed, Open, {closure#2}(), {closure#3}()

### Community 115 - "Tasks"
Cohesion: 0.35
Nodes (4): ReceiptController, ReceiptController, ReceiptService, Tasks

### Community 118 - "RolePermissionService"
Cohesion: 0.27
Nodes (3): UpdateRolePermissionsRequest, {closure#2}(), RolePermissionService

### Community 119 - "SettingService"
Cohesion: 0.27
Nodes (3): SettingsController, {closure#2}(), SettingService

### Community 123 - ".applyDiscount"
Cohesion: 0.22
Nodes (7): {closure#13}(), {closure#20}(), {closure#21}(), {closure#22}(), {closure#23}(), {closure#24}(), {closure#25}()

### Community 131 - "Open Tab (Table Sessions and Orders) – Design"
Cohesion: 0.29
Nodes (6): Data, Divergences from ARCHITECTURE.md (brief wins; doc updated), Intent, Open Tab (Table Sessions and Orders) – Design, Out of scope, Rules

### Community 132 - "OrderItemStatus"
Cohesion: 0.33
Nodes (3): OrderItemStatus, Active, Cancelled

### Community 133 - "ProductType"
Cohesion: 0.33
Nodes (4): ProductType, Drink, Food, Other

### Community 137 - "0001_01_01_000002_create_jobs_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 140 - "UI Work Rules (MASTER.md, 48px targets, tablet orientations)"
Cohesion: 0.50
Nodes (3): design-system/YANGS/MASTER.md, Design System and Base Layout Plan, Tablet Viewport Verification (768x1024, 1024x768, 1366x768)

## Knowledge Gaps
- **109 isolated node(s):** `Active`, `Cancelled`, `Open`, `Cancelled`, `Unpaid` (+104 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 587 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **120 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `ReceiptTest`, `SettingsTest`, `ActivityLogViewerTest`, `RolePermissionTest`, `SeederTest.php`, `User Management Tests`, `Web User Management`, `YANGS – Module Map (Phase 1)`, `TableApiTest`, `OwnerSeeder.php`, `Activity Log Tests`, `API Auth Tests`, `TestCase`, `OrderServiceTest.php`, `ActivityLogger`, `.log`, `Illuminate\Database\Eloquent\Model`, `TableSessionServiceTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `api.php`, `PaymentServiceTest`, `TableSession`, `Money`, `PaymentServiceTest.php`, `OrderSchemaTest.php`, `Illuminate\Foundation\Http\FormRequest`, `.change`, `ProductWebTest`, `PaymentService`, `PaymentMethodTest`, `BillingHttpTest`, `OpenTabApiTest`, `TableWebTest`, `SessionStatus`, `Tasks`, `.applyDiscount`, `CatalogApiTest`, `PosScreenTest`?**
  _High betweenness centrality (0.145) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `TestCase` (e.g. with `Tests` and `YANGS – Module Map (Phase 1)`) actually correct?**
  _`TestCase` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Active`, `Cancelled`, `Open` to the rest of the system?**
  _109 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `User` be split into smaller, more focused modules?**
  _Cohesion score 0.0946969696969697 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `TestCase` to `User`, `ReceiptTest`, `SettingsTest`, `ActivityLogViewerTest`, `RolePermissionTest`, `SeederTest.php`, `User Management Tests`, `Roles and permissions (RBAC)`, `TableApiTest`, `YANGS – Module Map (Phase 1)`, `Activity Log Tests`, `UI Component Tests`, `API Auth Tests`, `OrderServiceTest.php`, `Product`, `Illuminate\Database\Eloquent\Model`, `TableSessionServiceTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `PaymentServiceTest`, `PaymentServiceTest.php`, `OrderSchemaTest.php`, `.change`, `CatalogSchemaTest.php`, `ProductWebTest`, `PaymentMethodTest`, `BillingHttpTest`, `OpenTabApiTest`, `TableWebTest`, `.for`, `CatalogApiTest`, `PosScreenTest`, `ReferenceGenerator`?**
  _High betweenness centrality (0.087) - this node is a cross-community bridge._
- **Should `Composer Config` be split into smaller, more focused modules?**
  _Cohesion score 0.04081632653061224 - nodes in this community are weakly interconnected._
- **Why does `TableSession` connect `TableSession` to `ReceiptTest`, `OrderItemStatus`, `TableSessionController.php`, `OrderServiceTest.php`, `.items`, `ActivityLogger`, `Illuminate\Database\Eloquent\Model`, `TableSessionServiceTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `api.php`, `PaymentServiceTest`, `Order`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `Money`, `PaymentServiceTest.php`, `OrderSchemaTest.php`, `PosController.php`, `PaymentService`, `BillingHttpTest`, `SessionStatus`, `Tasks`, `.applyDiscount`, `PosScreenTest`?**
  _High betweenness centrality (0.074) - this node is a cross-community bridge._
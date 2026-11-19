# Graph Report - yangs-pos  (2026-11-19)

## Corpus Check
- 321 files · ~82,550 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 21 file(s) not represented in the graph (top: (none) 17, .example 1, .xml 1)

## Summary
- 2099 nodes · 4979 edges · 207 communities (72 shown, 135 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 457 edges (avg confidence: 0.88)
- Token cost: 227,375 input · 2,059 output

## Graph Freshness
- Built from commit: `d4524234`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- User.php
- Composer Config
- Permission Catalogue
- Illuminate\Database\Schema\Blueprint
- ActivityLogViewerTest
- RolePermissionTest
- SeederTest
- LoginRequest
- Frontend Build Config
- User Management Tests
- Illuminate\View\View
- Web User Management
- Closure
- Architecture and Progress Docs
- Controller
- Design System Docs
- Setup Plans and README
- Stock and Costing Design
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
- AuthenticationTest
- OrderServiceTest.php
- ProductServiceTest
- Auth and RBAC Plan
- CategoryService
- ActivityLogger
- Payment and Discount Design
- EnvironmentTest
- TableSessionServiceTest
- User
- Spec Modules and Roles
- App\Http\Controllers\Api\V1\AuthController
- PaymentServiceTest.php
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
- Payment
- Illuminate\Database\Seeder
- OrderSchemaTest.php
- Illuminate\Foundation\Http\FormRequest
- Illuminate\Database\Eloquent\Builder
- .change
- FloorController.php
- Illuminate\Database\Eloquent\Model
- TableStatus
- ProductWebTest
- App\Http\Controllers\Api\V1\TableSessionController
- Refund
- RoleName
- .show
- VoidServiceTest
- PaymentMethodTest
- BillingHttpTest
- OpenTabApiTest
- TableWebTest
- SessionStatus
- OrderItem
- PaymentMethod
- DiningTable
- RolePermissionService
- .log
- .for
- App\Http\Controllers\Api\V1\OrderItemController
- UpdateSettingsRequest
- .calculate
- Illuminate\Support\Facades\Schema
- CatalogApiTest
- Illuminate\Http\JsonResponse
- ReferenceGenerator
- Illuminate\Database\Migrations\Migration
- ReceiptTest
- Illuminate\Database\Seeder
- Open Tab (Table Sessions and Orders) – Design
- OrderItemStatus
- SaveProductRequest
- ProductController
- BillResource
- App\Http\Controllers\Api\V1\OrderController
- 0001_01_01_000002_create_jobs_table.php
- YANGS – Module Map (Phase 1)
- Product
- UI Work Rules (MASTER.md, 48px targets, tablet orientations)
- 0001_01_01_000001_create_cache_table.php
- 2026_11_18_155107_add_username_and_is_active_to_users_table.php
- 2026_11_18_161812_add_filter_indexes_to_activity_logs_table.php
- PaymentStatus
- ProductService
- App\Http\Controllers\Api\V1\TableController
- 2026_11_18_155845_create_personal_access_tokens_table.php
- 2026_11_18_171504_create_categories_table.php
- 2026_11_18_171506_create_products_table.php
- UserService
- 2026_11_18_174621_create_dining_tables_table.php
- 2026_11_18_202354_create_order_reference_counters_table.php
- 2026_11_18_202809_create_table_sessions_table.php
- 2026_11_18_202810_create_orders_table.php
- 2026_11_18_215642_create_settings_table.php
- Rules
- 2026_11_18_220142_create_payments_table.php
- NavigationTest
- pos/show.blade.php
- payment-methods/edit.blade.php
- payment-methods/index.blade.php
- products/create.blade.php
- products/edit.blade.php
- tables/create.blade.php
- tables/edit.blade.php
- bill.blade.php
- MoneyTest
- AuthenticatedSessionController.php
- AppServiceProvider.php
- Categories and Products Implementation Plan
- OrderService
- bootstrap/app.php
- Review Focus
- ProductPriceHistory
- CorrectionApiTest
- DashboardTest
- Open Tab (Table Sessions and Orders) Implementation Plan
- UserPolicy
- .voidOrder
- Voids, Refunds and Corrections Implementation Plan
- 2026_11_19_100000_add_approval_pin_to_users_table.php
- 2026_11_18_171509_create_product_price_histories_table.php
- 2026_11_18_202811_create_order_items_table.php
- 2026_11_19_100100_create_order_voids_table.php
- 2026_11_19_100200_create_refunds_table.php
- 2026_11_19_100300_create_payment_corrections_table.php
- {closure#2}
- transactions/show.blade.php

## God Nodes (most connected - your core abstractions)
1. `User` - 144 edges
2. `TableSession` - 95 edges
3. `TestCase` - 72 edges
4. `Permission` - 64 edges
5. `Controller` - 59 edges
6. `Product` - 57 edges
7. `DiningTable` - 45 edges
8. `ActivityLogger` - 44 edges
9. `Category` - 42 edges
10. `OrderItem` - 40 edges

## Surprising Connections (you probably didn't know these)
- `Tests` --references--> `TestCase`  [INFERRED]
  docs/MODULES.md → tests/TestCase.php
- `Global Constraints` --references--> `TableStatusService`  [INFERRED]
  docs/superpowers/plans/2026-11-18-tables.md → app/Services/Tables/TableStatusService.php
- `Rules` --references--> `TableStatusService`  [INFERRED]
  docs/superpowers/specs/2026-11-18-open-tab-design.md → app/Services/Tables/TableStatusService.php
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

## Communities (207 total, 135 thin omitted)

### Community 1 - "Composer Config"
Cohesion: 0.04
Nodes (48): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+40 more)

### Community 2 - "Permission Catalogue"
Cohesion: 0.04
Nodes (45): Permission, ApprovalsGrant, AttendanceManage, CashDrawerManageOwn, CashDrawerViewAll, CorrectionsCreate, DashboardCashier, DashboardOwner (+37 more)

### Community 3 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.15
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+2 more)

### Community 8 - "Frontend Build Config"
Cohesion: 0.07
Nodes (34): Global Constraints, POS Ordering UI Implementation Plan, Rulings, Tasks, dependencies, alpinejs@^3.17.5, devDependencies, concurrently@^9.0.1 (+26 more)

### Community 10 - "Illuminate\View\View"
Cohesion: 0.13
Nodes (4): ProfileController, ActivityLogController, DashboardController, UI: design system, layouts, dashboards

### Community 12 - "Closure"
Cohesion: 0.27
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 13 - "Architecture and Progress Docs"
Cohesion: 0.15
Nodes (14): Management Layout (x-layouts.app), YANGS Architecture and Database Design, Entity-Relationship Diagram, Open Questions (hosting, Senior/PWD, payroll rules), payroll_records table, Phased Implementation Plan (46 steps, 8 phases), Users vs Employees, YANGS Development Progress (+6 more)

### Community 14 - "Controller"
Cohesion: 0.09
Nodes (8): ActivityLogController, {closure#2}(), MenuController, PaymentMethodController, ReceiptController, RoleController, Controller, ActivityLogFilterRequest

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
Nodes (12): ActivityLogResource, MenuCategoryResource, OrderVoidResource, PaymentCorrectionResource, PaymentMethodResource, ReceiptResource, RefundResource, RoleResource (+4 more)

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

### Community 30 - "OrderServiceTest.php"
Cohesion: 0.07
Nodes (33): OrderStatus, Cancelled, Open, Order, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}() (+25 more)

### Community 32 - "Auth and RBAC Plan"
Cohesion: 0.19
Nodes (12): Versioned REST API under /api/v1, YANGS Bar Management and POS System (project instructions), ActivityLogFilterRequest, ActivityLogResource, API V1 ActivityLogController (GET /api/v1/activity-logs), activity_logs user_id/action Filter Indexes, ActivityLog filter Scope (user, action, Manila date range), Activity Log and Audit Foundation Plan (+4 more)

### Community 33 - "CategoryService"
Cohesion: 0.09
Nodes (12): CategoryController, CategoryController, {closure#2}(), {closure#3}(), {closure#6}(), {closure#7}(), SaveCategoryRequest, CategoryResource (+4 more)

### Community 35 - "ActivityLogger"
Cohesion: 0.15
Nodes (5): ActivityLogger, PaymentMethodService, TableSessionService, TableStatusService, BusinessDate

### Community 36 - "Payment and Discount Design"
Cohesion: 0.33
Nodes (6): Supervisor PIN Approval (ApprovalService), Discounts (line/bill, percent/amount, frozen at close), Owner Decisions (Q&A 2026-11-18), PaymentService::record(), App\Enums\Permission catalogue, VAT Setting (inclusive, frozen at close)

### Community 38 - "TableSessionServiceTest"
Cohesion: 0.12
Nodes (11): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+3 more)

### Community 39 - "User"
Cohesion: 0.08
Nodes (12): ActivityLog, User, DiningTableService, RolesAndPermissionsSeeder, ActiveUserTest, PasswordUpdateTest, ExampleTest, HealthCheckTest (+4 more)

### Community 40 - "Spec Modules and Roles"
Cohesion: 0.33
Nodes (6): Cashier Role, Financial Management, Inventory Management, Owner / Administrator Role, POS / Cashier Module, Table Management

### Community 41 - "App\Http\Controllers\Api\V1\AuthController"
Cohesion: 0.17
Nodes (5): AuthController, {closure#1}(), PasswordController, UserResource, Authentication

### Community 42 - "PaymentServiceTest.php"
Cohesion: 0.06
Nodes (33): Consolidated Bill, Payments and Receipts – Design, Data, Out of scope, Rules, {closure#1}(), {closure#11}(), {closure#12}(), {closure#13}() (+25 more)

### Community 45 - "Spec Stack"
Cohesion: 0.40
Nodes (5): Laravel, PostgreSQL, YANGS Bar Management and POS System, Role-Based Access Control (RBAC), RESTful API Architecture

### Community 46 - "TableSession"
Cohesion: 0.11
Nodes (12): BillController, ReceiptController, TransactionController, TableSession, BillService, {closure#3}(), PaymentService, ReceiptService (+4 more)

### Community 49 - "Money"
Cohesion: 0.25
Nodes (6): {closure#2}(), {closure#1}(), Money, Billing, Payments and Receipts Implementation Plan, Global Constraints, Global Constraints

### Community 89 - "Payment"
Cohesion: 0.05
Nodes (22): Payment, ApprovalService, {closure#1}(), PaymentCorrectionService, RefundService, ApprovalServiceTest, {closure#1}(), {closure#2}() (+14 more)

### Community 96 - "Illuminate\Database\Seeder"
Cohesion: 0.14
Nodes (6): CategorySeeder, DatabaseSeeder, SampleTableSeeder, Task 2: Schema, models, seeder, Task 1: Schema, enum, permission, seeders, TableSeederTest

### Community 98 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.08
Nodes (6): LoginRequest, UpdateApprovalPinRequest, ApplyDiscountRequest, CorrectPaymentRequest, TransactionFilterRequest, VoidOrderRequest

### Community 99 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.17
Nodes (9): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}(), {closure#2}() (+1 more)

### Community 102 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.08
Nodes (12): {closure#1}(), {closure#2}(), {closure#3}(), HasAuditedSoftDeletes, HasMargin, ProductVariant, Audit trail, AuditedSoftDeletesTest (+4 more)

### Community 103 - "TableStatus"
Cohesion: 0.15
Nodes (8): {closure#1}(), TableStatus, Available, AwaitingPayment, Cleaning, Occupied, Reserved, {closure#1}()

### Community 105 - "App\Http\Controllers\Api\V1\TableSessionController"
Cohesion: 0.17
Nodes (4): TableSessionController, CancelSessionRequest, OpenSessionRequest, TableSessionResource

### Community 106 - "Refund"
Cohesion: 0.16
Nodes (3): OrderVoid, Refund, {closure#6}()

### Community 107 - "RoleName"
Cohesion: 0.18
Nodes (7): RoleName, Bartender, Cashier, InventoryManager, Owner, Staff, Supervisor

### Community 109 - "VoidServiceTest"
Cohesion: 0.19
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#9}(), VoidServiceTest

### Community 114 - "SessionStatus"
Cohesion: 0.33
Nodes (5): SessionStatus, Billing, Cancelled, Closed, Open

### Community 115 - "OrderItem"
Cohesion: 0.15
Nodes (6): OrderItem, {closure#3}(), {closure#5}(), StockHook, itemCancelled(), quantityAdded()

### Community 116 - "PaymentMethod"
Cohesion: 0.15
Nodes (4): PaymentMethodController, SavePaymentMethodRequest, PaymentMethod, {closure#1}()

### Community 117 - "DiningTable"
Cohesion: 0.11
Nodes (5): TableController, SaveTableRequest, DiningTable, {closure#3}(), PosScreenTest

### Community 118 - "RolePermissionService"
Cohesion: 0.18
Nodes (5): {closure#1}(), RoleController, UpdateRolePermissionsRequest, {closure#2}(), RolePermissionService

### Community 119 - ".log"
Cohesion: 0.12
Nodes (12): LogAuthenticationEvents, {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}() (+4 more)

### Community 120 - ".for"
Cohesion: 0.24
Nodes (3): {closure#1}(), Task 4: OrderService and RunningBill, BusinessDateTest

### Community 121 - "App\Http\Controllers\Api\V1\OrderItemController"
Cohesion: 0.15
Nodes (5): OrderItemController, CancelOrderItemRequest, {closure#1}(), UpdateOrderItemRequest, OrderItemResource

### Community 123 - ".calculate"
Cohesion: 0.16
Nodes (11): {closure#3}(), {closure#1}(), {closure#2}(), Review Focus, Task 1: Permissions, setting, approval PIN, Task 2: Voids, Task 3: Refunds, Task 4: Payment corrections (+3 more)

### Community 126 - "Illuminate\Http\JsonResponse"
Cohesion: 0.22
Nodes (3): CorrectionController, RefundRequest, VoidItemRequest

### Community 130 - "Illuminate\Database\Seeder"
Cohesion: 0.12
Nodes (4): OwnerSeeder, PaymentMethodSeeder, SettingsSeeder, SettingsTest

### Community 131 - "Open Tab (Table Sessions and Orders) – Design"
Cohesion: 0.29
Nodes (6): Data, Divergences from ARCHITECTURE.md (brief wins; doc updated), Intent, Open Tab (Table Sessions and Orders) – Design, Out of scope, Rules

### Community 132 - "OrderItemStatus"
Cohesion: 0.33
Nodes (3): OrderItemStatus, Active, Cancelled

### Community 133 - "SaveProductRequest"
Cohesion: 0.15
Nodes (5): ProductType, Drink, Food, Other, SaveProductRequest

### Community 134 - "ProductController"
Cohesion: 0.18
Nodes (3): ProductController, ProductFilterRequest, ProductResource

### Community 135 - "BillResource"
Cohesion: 0.23
Nodes (4): BillController, RecordPaymentRequest, BillResource, PaymentResource

### Community 136 - "App\Http\Controllers\Api\V1\OrderController"
Cohesion: 0.19
Nodes (4): OrderController, AddOrderRequest, OrderResource, Task 5: API

### Community 137 - "0001_01_01_000002_create_jobs_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 138 - "YANGS – Module Map (Phase 1)"
Cohesion: 0.25
Nodes (6): Documentation and plans, Not built yet (designed in `docs/ARCHITECTURE.md`), Roles and permissions (RBAC), Tests, YANGS – Module Map (Phase 1), RouteGuardTest

### Community 140 - "UI Work Rules (MASTER.md, 48px targets, tablet orientations)"
Cohesion: 0.50
Nodes (3): design-system/YANGS/MASTER.md, Design System and Base Layout Plan, Tablet Viewport Verification (768x1024, 1024x768, 1366x768)

### Community 144 - "PaymentStatus"
Cohesion: 0.17
Nodes (10): PaymentStatus, Paid, Partial, PartiallyRefunded, Refunded, Unpaid, {closure#1}(), {closure#2}() (+2 more)

### Community 145 - "ProductService"
Cohesion: 0.26
Nodes (4): {closure#1}(), {closure#2}(), {closure#4}(), ProductService

### Community 150 - "UserService"
Cohesion: 0.23
Nodes (4): UserController, {closure#2}(), UserService, User management

### Community 156 - "Rules"
Cohesion: 0.17
Nodes (11): API (`/api/v1`), Approval PIN (optional), Correction (`corrections.create`), Intent, Out of scope, Permissions, Refund (`payments.refund`), Rules (+3 more)

### Community 181 - "AuthenticatedSessionController.php"
Cohesion: 0.27
Nodes (3): LogoutDeactivatedUsers, RejectDeactivatedApiUsers, Platform and configuration

### Community 183 - "Categories and Products Implementation Plan"
Cohesion: 0.20
Nodes (9): Categories and Products Implementation Plan, Global Constraints, Review Focus, Rulings, Task 1: Money support, Task 3: Services, Task 4: Web UI, Task 5: API (+1 more)

### Community 184 - "OrderService"
Cohesion: 0.47
Nodes (4): {closure#3}(), {closure#4}(), {closure#5}(), OrderService

### Community 185 - "bootstrap/app.php"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 186 - "Review Focus"
Cohesion: 0.25
Nodes (7): Global Constraints, Review Focus, Rulings, Table Management Implementation Plan, Task 2: TableStatusService and table service, Task 3: Web, Task 5: Docs and verify

### Community 190 - "Open Tab (Table Sessions and Orders) Implementation Plan"
Cohesion: 0.33
Nodes (5): Open Tab (Table Sessions and Orders) Implementation Plan, Review Focus, Task 1: Business date and order references, Task 3: TableSessionService and StockHook, Task 6: Docs

### Community 193 - "Voids, Refunds and Corrections Implementation Plan"
Cohesion: 0.40
Nodes (3): LocksLiveSession, Global Constraints, Voids, Refunds and Corrections Implementation Plan

## Knowledge Gaps
- **116 isolated node(s):** `Unpaid`, `Partial`, `Paid`, `PartiallyRefunded`, `Refunded` (+111 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 644 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **135 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `User.php`, `ReceiptTest`, `Illuminate\Database\Seeder`, `ActivityLogViewerTest`, `RolePermissionTest`, `User Management Tests`, `Illuminate\View\View`, `Web User Management`, `YANGS – Module Map (Phase 1)`, `PaymentStatus`, `Activity Log Tests`, `UserService`, `OrderServiceTest.php`, `NavigationTest`, `Illuminate\Support\Facades\DB`, `ActivityLogger`, `TableSessionServiceTest`, `App\Http\Controllers\Api\V1\AuthController`, `PaymentServiceTest.php`, `TableSession`, `OrderService`, `CorrectionApiTest`, `UserPolicy`, `.voidOrder`, `Payment`, `OrderSchemaTest.php`, `.change`, `Illuminate\Database\Eloquent\Model`, `ProductWebTest`, `RoleName`, `VoidServiceTest`, `PaymentMethodTest`, `BillingHttpTest`, `OpenTabApiTest`, `TableWebTest`, `OrderItem`, `DiningTable`, `.log`, `.calculate`, `CatalogApiTest`?**
  _High betweenness centrality (0.179) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `TestCase` (e.g. with `Tests` and `YANGS – Module Map (Phase 1)`) actually correct?**
  _`TestCase` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Unpaid`, `Partial`, `Paid` to the rest of the system?**
  _116 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Composer Config` be split into smaller, more focused modules?**
  _Cohesion score 0.04081632653061224 - nodes in this community are weakly interconnected._
- **Why does `DiningTable` connect `DiningTable` to `ReceiptTest`, `Frontend Build Config`, `Closure`, `App\Http\Controllers\Api\V1\TableController`, `Illuminate\Support\Facades\DB`, `ActivityLogger`, `TableSessionServiceTest`, `User`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `Money`, `Illuminate\Database\Seeder`, `OrderSchemaTest.php`, `Illuminate\Database\Eloquent\Builder`, `.change`, `FloorController.php`, `Illuminate\Database\Eloquent\Model`, `App\Http\Controllers\Api\V1\TableSessionController`, `OpenTabApiTest`, `TableWebTest`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **Should `Permission Catalogue` be split into smaller, more focused modules?**
  _Cohesion score 0.0425531914893617 - nodes in this community are weakly interconnected._
- **Why does `TableSession` connect `TableSession` to `ReceiptTest`, `OrderItemStatus`, `BillResource`, `App\Http\Controllers\Api\V1\OrderController`, `Controller`, `PaymentStatus`, `OrderServiceTest.php`, `CategoryService`, `Illuminate\Support\Facades\DB`, `ActivityLogger`, `TableSessionServiceTest`, `User`, `PaymentServiceTest.php`, `Illuminate\Database\Eloquent\Relations\HasMany`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `CorrectionApiTest`, `Voids, Refunds and Corrections Implementation Plan`, `Payment`, `OrderSchemaTest.php`, `Illuminate\Database\Eloquent\Model`, `App\Http\Controllers\Api\V1\TableSessionController`, `.show`, `VoidServiceTest`, `BillingHttpTest`, `OrderItem`, `DiningTable`, `.calculate`, `Illuminate\Http\JsonResponse`?**
  _High betweenness centrality (0.054) - this node is a cross-community bridge._
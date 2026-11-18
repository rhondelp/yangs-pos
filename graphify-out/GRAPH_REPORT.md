# Graph Report - yangs-pos  (2026-11-18)

## Corpus Check
- Corpus is ~33,443 words - fits in a single context window. You may not need a graph.

## Summary
- 787 nodes · 1228 edges · 96 communities (28 shown, 68 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 74 edges (avg confidence: 0.85)
- Token cost: 222,171 input · 0 output

## Community Hubs (Navigation)
- User and Audit Core
- Composer Config
- Permission Catalogue
- Database Migrations
- Activity Log Model
- Role Permission Matrix
- Role and Owner Seeders
- Login and Form Requests
- Frontend Build Config
- User Management Tests
- Auth and Dashboard Controllers
- Web User Management
- App Service Provider
- Architecture and Progress Docs
- Activity Log API
- Design System Docs
- Setup Plans and README
- Stock and Costing Design
- Web Routes
- Activity Log Tests
- UI Rules and Kit Docs
- UI Component Tests
- API Auth Tests
- API Resources
- Append-Only Audit Design
- Environment and Money Rules
- Permission-Aware Navigation
- Finance Design
- POS Tables and Payments Design
- Base Test Case
- Authentication Tests
- App Bootstrap
- Auth and RBAC Plan
- Active User and Profile Tests
- Web View Controllers
- User Service Design
- Payment and Discount Design
- Environment Tests
- Dashboard Tests
- Seeder Test Wiring
- Spec Modules and Roles
- Password Update Tests
- Deactivated User Middleware
- Activity Log Viewer Design
- Spec Stack
- Soft Delete Tests
- Navigation Tests
- Unit Example Test
- Profile Views
- User Create View
- User Edit View
- Guest Layout Doc
- Spec Audit Section
- Spec Payroll Section
- Tailwind Concept
- Robots File

## God Nodes (most connected - your core abstractions)
1. `Permission` - 54 edges
2. `User` - 54 edges
3. `TestCase` - 38 edges
4. `Controller` - 22 edges
5. `ActivityLogger` - 22 edges
6. `ActivityLog` - 19 edges
7. `UserManagementTest` - 19 edges
8. `RoleName` - 16 edges
9. `RolePermissionTest` - 14 edges
10. `SaveUserRequest` - 13 edges

## Surprising Connections (you probably didn't know these)
- `Derived Table Status and Session Lifecycle` --conceptually_related_to--> `Status Colors with Text Labels`  [INFERRED]
  docs/ARCHITECTURE.md → design-system/YANGS/MASTER.md
- `Folder Structure (thin controllers, services own transactions)` --references--> `Management Layout (x-layouts.app)`  [INFERRED]
  docs/ARCHITECTURE.md → design-system/YANGS/MASTER.md
- `Folder Structure (thin controllers, services own transactions)` --references--> `Blade UI Components (resources/views/components/ui)`  [INFERRED]
  docs/ARCHITECTURE.md → design-system/YANGS/MASTER.md
- `UserService (create, update, deactivation)` --implements--> `Code Structure (Services, Form Requests, Policies, Blade components, API Resources)`  [INFERRED]
  docs/superpowers/plans/2026-11-18-auth-rbac.md → CLAUDE.md
- `Authentication and RBAC Plan` --conceptually_related_to--> `Allowed Packages List`  [INFERRED]
  docs/superpowers/plans/2026-11-18-auth-rbac.md → CLAUDE.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core Business Flow** — docs_yangs_spec_pos_module, docs_yangs_spec_inventory_management, docs_yangs_spec_financial_management [EXTRACTED 0.95]
- **Technology Stack** — docs_yangs_spec_laravel, docs_yangs_spec_postgresql, docs_yangs_spec_tailwind_css, docs_yangs_spec_rest_api [EXTRACTED 1.00]
- **Payment recording and session close flow** — docs_architecture_payment_service, docs_architecture_table_sessions, docs_architecture_payments_table, docs_architecture_cashier_shifts, docs_architecture_vat, docs_architecture_discounts, docs_architecture_activity_logger [EXTRACTED 1.00]
- **Stock deduction and WAC costing flow** — docs_architecture_stock_service, docs_architecture_stock_deduction_on_send, docs_architecture_weighted_average_cost, docs_architecture_recipe_components, docs_architecture_inventory_movements, docs_architecture_order_items [EXTRACTED 1.00]
- **YANGS Blade layouts** — design_system_yangs_master_management_layout, design_system_yangs_master_pos_layout, design_system_yangs_master_guest_layout [EXTRACTED 1.00]
- **Two-layer append-only audit log (Eloquent guard + DB trigger + logger)** — docs_superpowers_plans_2026_11_18_audit_foundation_activitylog_append_only_guard, docs_superpowers_plans_2026_11_18_audit_foundation_activity_logs_trigger, docs_superpowers_plans_2026_11_18_audit_foundation_activitylogger_service, claude_append_only_records [INFERRED 0.85]
- **Phase 1 implementation plan series following the standard workflow** — claude_standard_workflow, docs_superpowers_plans_2026_11_18_laravel_postgres_setup_plan, docs_superpowers_plans_2026_11_18_foundation_setup_delta_plan, docs_superpowers_plans_2026_11_18_auth_rbac_plan, docs_superpowers_plans_2026_11_18_audit_foundation_plan, docs_superpowers_plans_2026_11_18_design_system_layout_plan [INFERRED 0.85]
- **Permission-driven access control (catalogue, roles, matrix, route guard, nav filter)** — docs_superpowers_plans_2026_11_18_auth_rbac_permission_enum, docs_superpowers_plans_2026_11_18_auth_rbac_seeded_roles, docs_superpowers_plans_2026_11_18_auth_rbac_rolepermissionservice, docs_superpowers_plans_2026_11_18_auth_rbac_route_guard_test, docs_superpowers_plans_2026_11_18_design_system_layout_navigation [INFERRED 0.85]

## Communities (96 total, 68 thin omitted)

### Community 0 - "User and Audit Core"
Cohesion: 0.06
Nodes (13): AuthController, UserController, UserResource, LogAuthenticationEvents, User, UserPolicy, ActivityLogger, {closure#1}() (+5 more)

### Community 1 - "Composer Config"
Cohesion: 0.04
Nodes (48): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+40 more)

### Community 2 - "Permission Catalogue"
Cohesion: 0.04
Nodes (43): Permission, ApprovalsGrant, AttendanceManage, CashDrawerManageOwn, CashDrawerViewAll, DashboardCashier, DashboardOwner, EmployeesManage (+35 more)

### Community 3 - "Database Migrations"
Cohesion: 0.08
Nodes (19): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}() (+11 more)

### Community 4 - "Activity Log Model"
Cohesion: 0.08
Nodes (12): ActivityLog, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#2}(), {closure#3}() (+4 more)

### Community 5 - "Role Permission Matrix"
Cohesion: 0.11
Nodes (7): RoleController, {closure#1}(), UpdateRolePermissionsRequest, {closure#2}(), RolePermissionService, {closure#1}(), RolePermissionTest

### Community 6 - "Role and Owner Seeders"
Cohesion: 0.07
Nodes (11): RoleName, Bartender, Cashier, InventoryManager, Owner, Staff, Supervisor, DatabaseSeeder (+3 more)

### Community 7 - "Login and Form Requests"
Cohesion: 0.08
Nodes (3): LoginRequest, LoginRequest, RouteGuardTest

### Community 8 - "Frontend Build Config"
Cohesion: 0.11
Nodes (22): dependencies, alpinejs@^3.17.5, devDependencies, concurrently@^9.0.1, laravel-vite-plugin@^2.0.0, tailwindcss@^4.0.0, @tailwindcss/forms@^0.5.2, @tailwindcss/vite@^4.0.0 (+14 more)

### Community 10 - "Auth and Dashboard Controllers"
Cohesion: 0.21
Nodes (5): AuthenticatedSessionController, {closure#1}(), PasswordController, Controller, DashboardController

### Community 13 - "Architecture and Progress Docs"
Cohesion: 0.15
Nodes (14): Management Layout (x-layouts.app), YANGS Architecture and Database Design, Entity-Relationship Diagram, Open Questions (hosting, Senior/PWD, payroll rules), payroll_records table, Phased Implementation Plan (46 steps, 8 phases), Users vs Employees, YANGS Development Progress (+6 more)

### Community 15 - "Design System Docs"
Cohesion: 0.19
Nodes (9): YANGS Design System Master, Accessibility Checklist, Color Tokens (--color-*), Motion Rules (150ms, reduced motion), POS Layout (x-layouts.pos), 48px Touch Target (--spacing-touch), Typography (Barlow, Barlow Semi Condensed), Blade UI Components (resources/views/components/ui) (+1 more)

### Community 16 - "Setup Plans and README"
Cohesion: 0.20
Nodes (10): YANGS Bar Management and POS System (project instructions), Alpine.js Integration (Alpine.start once), /up Health Smoke Test, Foundation Setup Delta Plan, Eloquent Strict Mode Outside Production, Laravel 13 + PostgreSQL Setup Plan, Local Setup on Windows (PowerShell), Stack: Laravel 13, PostgreSQL 18, Blade, Tailwind 4 (Vite), Alpine.js (+2 more)

### Community 17 - "Stock and Costing Design"
Cohesion: 0.23
Nodes (8): inventory_items table, inventory_movements ledger, Database Notifications (stock, voids, pending payment), order_item_voids table, order_items table (price/cost snapshots), stock_receipts table, StockService (sole writer of inventory movements), Phase 3 Inventory

### Community 20 - "UI Rules and Kit Docs"
Cohesion: 0.22
Nodes (5): UI Component Kit (x-ui.* anonymous Blade components), design-system/YANGS/MASTER.md, Design System and Base Layout Plan, Tablet Viewport Verification (768x1024, 1024x768, 1366x768), Tailwind 4 @theme Design Tokens (dark high-contrast)

### Community 24 - "Append-Only Audit Design"
Cohesion: 0.28
Nodes (4): activity_logs DB Trigger (rejects UPDATE/DELETE), ActivityLog Eloquent Append-Only Guard, HasAuditedSoftDeletes Trait, Activity Log and Audit Foundation Plan

### Community 25 - "Environment and Money Rules"
Cohesion: 0.28
Nodes (5): Currency PHP and Timezone Asia/Manila, config app.currency = PHP, Test Database Rename to yangs_test, EnvironmentTest (pgsql, DB name, Asia/Manila, bcmath), yangs_pos_test Database (original test DB)

### Community 26 - "Permission-Aware Navigation"
Cohesion: 0.25
Nodes (7): Cashier Permission Set (POS only), OwnerSeeder from OWNER_* env, Permission Enum (module.action catalogue), Seeded Roles (Owner, Cashier, Supervisor, Bartender, Staff, Inventory Manager), DashboardController (owner, cashier, none empty states), Layouts (x-layouts.app, pos, guest), App\Support\Navigation (permission and Route::has filtered)

### Community 27 - "Finance Design"
Cohesion: 0.22
Nodes (5): cash_movements table, Cashier Shifts and Expected Drawer Cash, expenses table, Money and BusinessDate support classes, Phase 4 Financial Management

### Community 28 - "POS Tables and Payments Design"
Cohesion: 0.31
Nodes (6): ActivityLogger Service, activity_logs table, dining_tables table, payments table, refunds table, table_sessions table

### Community 29 - "Base Test Case"
Cohesion: 0.31
Nodes (3): ExampleTest, HealthCheckTest, TestCase

### Community 31 - "App Bootstrap"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 32 - "Auth and RBAC Plan"
Cohesion: 0.25
Nodes (7): Versioned REST API under /api/v1, ActivityLogResource, API V1 ActivityLogController (GET /api/v1/activity-logs), Login Rate Limit (5/min per username+IP), Authentication and RBAC Plan, Sanctum API Auth (login, logout, me), Username Login (lowercase unique, email optional)

### Community 35 - "User Service Design"
Cohesion: 0.38
Nodes (5): ActivityLogger Injectable Service, EnsureUserIsActive Middleware (active), Minimal activity_logs Table and ActivityLogger (Step 4), RolePermissionService (permission matrix sync), UserService (create, update, deactivation)

### Community 36 - "Payment and Discount Design"
Cohesion: 0.33
Nodes (6): Supervisor PIN Approval (ApprovalService), Discounts (line/bill, percent/amount, frozen at close), Owner Decisions (Q&A 2026-11-18), PaymentService::record(), App\Enums\Permission catalogue, VAT Setting (inclusive, frozen at close)

### Community 40 - "Spec Modules and Roles"
Cohesion: 0.33
Nodes (6): Cashier Role, Financial Management, Inventory Management, Owner / Administrator Role, POS / Cashier Module, Table Management

### Community 44 - "Activity Log Viewer Design"
Cohesion: 0.40
Nodes (5): ActivityLogFilterRequest, activity_logs user_id/action Filter Indexes, ActivityLog filter Scope (user, action, Manila date range), logs.view Permission, Web ActivityLogController (index, show)

### Community 45 - "Spec Stack"
Cohesion: 0.40
Nodes (5): Laravel, PostgreSQL, YANGS Bar Management and POS System, Role-Based Access Control (RBAC), RESTful API Architecture

## Knowledge Gaps
- **81 isolated node(s):** `DashboardOwner`, `DashboardCashier`, `TablesView`, `TablesManage`, `PosOpenSession` (+76 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 330 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **68 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User and Audit Core` to `Active User and Profile Tests`, `Web View Controllers`, `Activity Log Model`, `Role Permission Matrix`, `Role and Owner Seeders`, `Login and Form Requests`, `Seeder Test Wiring`, `Password Update Tests`, `Environment Tests`, `Web User Management`, `User Management Tests`, `Navigation Tests`, `Activity Log Tests`, `Authentication Tests`?**
  _High betweenness centrality (0.129) - this node is a cross-community bridge._
- **What connects `DashboardOwner`, `DashboardCashier`, `TablesView` to the rest of the system?**
  _81 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `User and Audit Core` be split into smaller, more focused modules?**
  _Cohesion score 0.05721153846153846 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Base Test Case` to `Active User and Profile Tests`, `Activity Log Model`, `Environment Tests`, `Role Permission Matrix`, `Seeder Test Wiring`, `Login and Form Requests`, `Password Update Tests`, `Role and Owner Seeders`, `Dashboard Tests`, `User Management Tests`, `Soft Delete Tests`, `Navigation Tests`, `Activity Log Tests`, `UI Component Tests`, `API Auth Tests`, `Authentication Tests`?**
  _High betweenness centrality (0.079) - this node is a cross-community bridge._
- **Should `Composer Config` be split into smaller, more focused modules?**
  _Cohesion score 0.04081632653061224 - nodes in this community are weakly interconnected._
- **Why does `Permission` connect `Permission Catalogue` to `User and Audit Core`, `Role Permission Matrix`, `Role and Owner Seeders`, `Login and Form Requests`, `Auth and Dashboard Controllers`?**
  _High betweenness centrality (0.074) - this node is a cross-community bridge._
- **Should `Permission Catalogue` be split into smaller, more focused modules?**
  _Cohesion score 0.044444444444444446 - nodes in this community are weakly interconnected._
# YANGS – Module Map (Phase 1)

Built from the Graphify graph on 2026-11-18: 787 nodes, 1,228 edges, 96 communities, in `graphify-out/`. The most connected nodes are `Permission` and `User` (54 edges each), `TestCase`, `Controller` and `ActivityLogger`. Rebuild the graph at each phase checkpoint. Ask it questions with `graphify query "<question>"`.

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

## API v1
- **Routes:** `routes/api.php` puts everything under `/api/v1` behind `auth:sanctum` and the same `can:` permissions as the web.
- **Output:** API Resources (`UserResource`, `RoleResource`, `ActivityLogResource`).
- **Shared code:** controllers reuse the web Form Requests and services, so both paths enforce the same rules.
- **Endpoints today:** auth, users, roles and activity logs. Each later module adds its endpoints in the same step as its screens.
- Graph communities: *API Resources*, *Activity Log API*.

## Tests
81 feature tests, all on PostgreSQL. `TestCase` is the second biggest bridge in the graph, linking every test community.
- **Environment:** the driver, database name, timezone, config-cache guard and strict mode.
- **Auth:** web and API login, rate limits and deactivation.
- **RBAC:** seeders, the permission matrix and the route guard.
- **Users:** user management, including the owner-lockout rules.
- **Audit:** append-only at both layers, the viewer filters and the soft-delete trait.
- **UI:** components, navigation and dashboards.

## Documentation and plans
- `docs/YANGS_SPEC.md`: the authority.
- `docs/ARCHITECTURE.md`: the ERD for all 20 modules, design decisions and roadmap.
- `CLAUDE.md`: non-negotiable rules and workflow.
- `docs/PROGRESS.md`: phase checklist and checkpoint notes.
- `docs/superpowers/plans/*`: one plan per step.

The graph links the architecture to the design system (table status to status colors, folder structure to layouts). It links the plans to the CLAUDE.md rules they implement.

## Not built yet (designed in `docs/ARCHITECTURE.md`)
- **Phase 2:** tables, sessions, orders, payments and receipts.
- **Phase 3:** suppliers, stock and costing.
- **Phase 4:** expenses and P&L.
- **Phase 5:** employees, schedules, attendance and payroll.
- **Later:** settings, supervisor PIN approval and notifications.

The graph already holds their design nodes (for example *Stock and Costing Design* and *POS Tables and Payments Design*), so later steps can query them before writing code.

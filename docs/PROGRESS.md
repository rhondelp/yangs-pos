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
- **Cookie-based API calls:** these are not covered by the deactivated-user check. Revisit when the POS screen calls the API with session cookies.
- **Supervisor PIN approval:** designed, not built (needed for voids and discounts).
- **Settings table and service:** designed, not built (VAT, business-day cutoff and similar).
- **Modal and confirm dialog:** tested in code but not used on any page yet.
- **Still open with the owner:**
  - hosting (local PC or cloud);
  - 6-decimal unit costs;
  - Senior/PWD discount.
- **Owner account in dev:** not seeded yet. Set `OWNER_PASSWORD` in `.env`, then run `php artisan db:seed --class=OwnerSeeder`.

## Phase 2 – Core POS
- [x] Table management
- [x] Product and category management
- [x] Customer table sessions
- [x] Order creation
- [ ] Open tabs (backend and API done in Step 9; POS screen next)
- [ ] Consolidated billing
- [ ] Payment processing
- [ ] Receipt generation

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

# Foundation Setup Delta Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish Phase 1 setup on top of the existing Laravel 13 + PostgreSQL scaffold: test database `yangs_test`, currency config, Eloquent strict mode, `/up` smoke test, Alpine.js, Windows setup README.

**Architecture:** The scaffold from `2026-11-18-laravel-postgres-setup.md` already provides Laravel 13.43, `pgsql` connection, `Asia/Manila`, locale `en`, Tailwind 4 via Vite, PHPUnit, and the framework's `/up` route (`bootstrap/app.php` `health: '/up'`). This plan only adds what is missing.

**Tech Stack:** PHP 8.4, Laravel 13, PHPUnit 12, PostgreSQL 18, Vite 7, Tailwind 4, Alpine.js 3.

**Spec:** `docs/YANGS_SPEC.md` (sections 2, 15 Phase 1), `docs/ARCHITECTURE.md` (3.17: Alpine.js approved by the owner on 2026-11-18), `CLAUDE.md`.

## Global Constraints

- Test database name: `yangs_test` (owner instruction; replaces `yangs_pos_test`). App database stays `yangs_pos`.
- Timezone `Asia/Manila`, locale `en`, currency `PHP`.
- Strict mode only outside production.
- Commands are PowerShell. No `git commit`.
- No packages beyond `alpinejs`.

## Review Focus

- Tests still hitting the dev DB: `EnvironmentTest` asserts `yangs_test`; config-cache guard stays.
- Strict mode leaking into production: guarded by `! $this->app->isProduction()`; the test proves it is on in `testing`.
- Alpine started twice or not at all: `Alpine.start()` called once in `resources/js/app.js`; the build must bundle it.
- `.env` and `.env.example` drifting: both get the same DB block; only `.env` holds the password.
- Old `yangs_pos_test` database left behind: not dropped by the agent; reported to the owner.

---

### Task 1: Test database rename and currency config

**Files:**
- Modify: `phpunit.xml` (`DB_DATABASE`), `config/app.php` (add `'currency' => 'PHP'`)
- Test: `tests/Feature/EnvironmentTest.php`

- [ ] **Step 1: Change the assertions** in `test_app_runs_on_postgres_test_database_in_manila_time`: database `yangs_test`; add `assertSame('en', app()->getLocale())` and `assertSame('PHP', config('app.currency'))`.
- [ ] **Step 2: Run** `php artisan test --filter=EnvironmentTest`. Expected: FAIL on `yangs_test`.
- [ ] **Step 3: Implement** the two config edits; create `yangs_test` with the scratchpad `create-dbs.php` script (parameterised to the new name).
- [ ] **Step 4: Run** `php artisan test`. Expected: all PASS.

### Task 2: Eloquent strict mode outside production

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (`boot`)
- Test: `tests/Feature/EnvironmentTest.php`

- [ ] **Step 1: Write** `test_eloquent_is_strict_outside_production`: asserts `Model::preventsLazyLoading()`, `Model::preventsSilentlyDiscardingAttributes()`, `Model::preventsAccessingMissingAttributes()` are all true.
- [ ] **Step 2: Run** it. Expected: FAIL.
- [ ] **Step 3: Implement** `Model::shouldBeStrict(! $this->app->isProduction());` in `boot()` (covers `preventLazyLoading`).
- [ ] **Step 4: Run** `php artisan test`. Expected: all PASS.

### Task 3: `/up` health smoke test

**Files:**
- Test: `tests/Feature/HealthCheckTest.php`

- [ ] **Step 1: Write** `test_health_route_responds_ok`: `$this->get('/up')->assertOk();`
- [ ] **Step 2: Run** it. Expected: PASS without code changes (the route ships with Laravel 13). The test pins the route so removing `health: '/up'` breaks the suite.

### Task 4: Alpine.js

**Files:**
- Modify: `package.json` (via `npm install alpinejs`), `resources/js/app.js`

- [ ] **Step 1: Run** `npm run build` and search `public/build/assets/*.js` for `x-data`. Expected: no match (Alpine absent).
- [ ] **Step 2: Implement** `npm install alpinejs`; in `app.js` import Alpine, set `window.Alpine = Alpine`, call `Alpine.start()` once.
- [ ] **Step 3: Run** `npm run build` and the same search. Expected: match.

### Task 5: README "Local setup on Windows"

**Files:**
- Modify: `README.md` (replace the Laravel boilerplate)

- [ ] **Step 1: Write** a short project README with a "Local setup on Windows" section: prerequisites (PHP 8.4 with `pdo_pgsql`, `pgsql`, `bcmath`, `intl`, `mbstring`; Composer; Node 24; PostgreSQL 18), steps in PowerShell (`composer install`, `npm install`, copy `.env.example` to `.env`, set `DB_PASSWORD`, `php artisan key:generate`, create `yangs_pos` and `yangs_test`, `php artisan migrate`, `npm run build` or `npm run dev`, `php artisan serve`, `php artisan test`), and the stale-PATH tip.

### Task 6: Final verification

- [ ] **Run** `php artisan migrate --no-interaction`, `php artisan test`, `npm run build`. Expected: migrate "Nothing to migrate" or DONE; all tests PASS; build prints `built in`.

# Laravel 13 + PostgreSQL Setup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A fresh Laravel 13 app in the repo root that connects to local PostgreSQL 18, runs its test suite against a real PostgreSQL test database, and builds Tailwind assets.

**Architecture:** Stock `laravel/laravel` 13 skeleton, scaffolded in a temp folder and moved into the repo root so existing files survive. App and tests both use the `pgsql` connection (tests on `yangs_pos_test`), because later steps rely on PostgreSQL row locking and `decimal` behavior that SQLite does not reproduce.

**Tech Stack:** PHP 8.4.27, Laravel 13, PHPUnit (skeleton default), PostgreSQL 18, Vite + Tailwind CSS (skeleton default), Node 24.

**Spec:** `docs/YANGS_SPEC.md` (sections 2, 13, 15 Phase 1) and `CLAUDE.md`.

## Global Constraints

- Latest stable Laravel: `laravel/laravel` `^13`.
- Database: PostgreSQL. Connection `pgsql`, host `127.0.0.1`, port `5432`, user `postgres`, databases `yangs_pos` (app) and `yangs_pos_test` (tests).
- `DB_PASSWORD` is left blank; the user fills it in `.env`. Never print or commit it.
- Timezone `Asia/Manila`. Currency PHP (used from later steps).
- bcmath must be available (money math).
- Commands are PowerShell. The engineer does not run `git commit`.
- No packages beyond the skeleton in this step.
- Keep existing `docs/`, `CLAUDE.md`, `.claude/`, `graphify-out/` untouched.

## Review Focus

- Tests silently running on SQLite: skeleton `phpunit.xml` sets `DB_CONNECTION=sqlite`; it must be switched, and a test must fail if the driver is not `pgsql`.
- Tests wiping the dev database: `RefreshDatabase` must only ever touch `yangs_pos_test`; the environment test asserts the database name.
- Missing password: with `DB_PASSWORD` blank, `php artisan migrate` fails with a clear auth error, not a partial migration. Verification waits for the user's password.
- `.env` committed by accident: the skeleton `.gitignore` must keep `.env` ignored after the merge.
- Wrong timezone: `config('app.timezone')` must be `Asia/Manila` regardless of server time zone.

---

### Task 1: Scaffold Laravel 13 into the repo root

**Files:**
- Create: whole Laravel skeleton in repo root (`app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `artisan`, `composer.json`, `package.json`, `vite.config.js`, `phpunit.xml`, `.env.example`, `.gitignore`, `.editorconfig`, `.gitattributes`, `README.md`)
- Keep: `docs/`, `CLAUDE.md`, `.claude/`, `graphify-out/`

**Interfaces:**
- Produces: working `php artisan`, `composer.json` requiring `laravel/framework ^13`, `npm run build`.

- [ ] **Step 1: Scaffold into a temp folder**

Run: `composer create-project laravel/laravel:^13 "$env:TEMP\yangs-skeleton" --no-interaction`
Expected: ends with "Application key set successfully".

- [ ] **Step 2: Move skeleton into the repo root without overwriting**

Move every item from the temp folder into the repo root. Skip any name that already exists (none of the skeleton's names collide with `docs`, `CLAUDE.md`, `.claude`, `graphify-out`). Delete the temp folder afterwards.

- [ ] **Step 3: Verify framework and ignore rules**

Run: `php artisan --version`
Expected: `Laravel Framework 13.x.x`

Run: `git check-ignore .env vendor node_modules`
Expected: all three paths printed (ignored).

- [ ] **Step 4: Install and build frontend assets**

Run: `npm install; if ($?) { npm run build }`
Expected: Vite prints `built in` and writes `public/build/manifest.json`.

### Task 2: Configure PostgreSQL and timezone

**Files:**
- Modify: `.env`, `.env.example` (DB block)
- Modify: `config/app.php` (`timezone`)
- Modify: `phpunit.xml` (DB env vars)
- Test: `tests/Feature/EnvironmentTest.php`

**Interfaces:**
- Consumes: Task 1 skeleton.
- Produces: default connection `pgsql`; tests run on `yangs_pos_test`.

- [ ] **Step 1: Write the failing test** `tests/Feature/EnvironmentTest.php`

```php
public function test_app_runs_on_postgres_test_database_in_manila_time(): void
{
    $this->assertSame('pgsql', DB::connection()->getDriverName());
    $this->assertSame('yangs_pos_test', DB::connection()->getDatabaseName());
    $this->assertSame('Asia/Manila', config('app.timezone'));
    $this->assertSame('Asia/Manila', now()->timezoneName);
    $this->assertTrue(extension_loaded('bcmath'));
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=EnvironmentTest`
Expected: FAIL (driver is `sqlite`).

- [ ] **Step 3: Configure**

- `.env` and `.env.example`: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=yangs_pos`, `DB_USERNAME=postgres`, `DB_PASSWORD=` (blank; uncomment the skeleton's commented DB lines).
- `config/app.php`: `'timezone' => 'Asia/Manila'`.
- `phpunit.xml`: replace `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` with `DB_CONNECTION=pgsql` and `DB_DATABASE=yangs_pos_test`. Password comes from `.env`.

- [ ] **Step 4: User sets `DB_PASSWORD` in `.env`** (blocking hand-off)

- [ ] **Step 5: Create both databases**

Run a PHP one-liner that reads credentials via `php artisan tinker`-free PDO to the `postgres` maintenance DB and runs `CREATE DATABASE yangs_pos` and `CREATE DATABASE yangs_pos_test` if they do not exist (check `pg_database`). The password is read from `.env` and never echoed.
Expected: both names listed by `SELECT datname FROM pg_database`.

- [ ] **Step 6: Migrate and run the suite**

Run: `php artisan migrate --no-interaction; if ($?) { php artisan test }`
Expected: migrations `DONE`; all tests PASS including `EnvironmentTest`.

- [ ] **Step 7: Report** summary, changed files, test output, and suggested commit `chore: scaffold Laravel 13 with PostgreSQL config`. Tick "Laravel setup" and "PostgreSQL configuration" in `docs/PROGRESS.md`.

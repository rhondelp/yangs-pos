# YANGS – Bar Management and POS System

POS and business management system for YANGS: open-tab table ordering, payments, inventory, expenses, staff scheduling and payroll, and financial reporting.

- Spec: `docs/YANGS_SPEC.md`
- Architecture and database design: `docs/ARCHITECTURE.md`
- Progress: `docs/PROGRESS.md`

Stack: Laravel 13, PostgreSQL 18, Blade, Tailwind CSS 4 (Vite), Alpine.js.

## Local setup on Windows

### Prerequisites

- PHP 8.4 with these extensions enabled in `php.ini`: `pdo_pgsql`, `pgsql`, `bcmath`, `intl`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`. Set `date.timezone = Asia/Manila`.
- Composer 2
- Node.js 24 and npm
- PostgreSQL 18 running on `127.0.0.1:5432`

Check that the right PHP is first on your PATH:

```powershell
php -v
```

It must print PHP 8.4. If it prints an older version (for example XAMPP's PHP 8.2), put the PHP 8.4 folder before `C:\xampp\php` in the system PATH, then open a new terminal. Terminals opened before the change keep the old PATH.

### Steps

Run these in PowerShell from the project folder.

1. Install dependencies:

   ```powershell
   composer install
   npm install
   ```

2. Create your environment file and app key:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

3. Open `.env` and set `DB_PASSWORD` to your PostgreSQL `postgres` user's password. Never commit `.env`.

4. Create the app and test databases (asks for the `postgres` password):

   ```powershell
   & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -c "CREATE DATABASE yangs_pos" -c "CREATE DATABASE yangs_test"
   ```

5. Run the migrations:

   ```powershell
   php artisan migrate
   ```

6. Build the frontend once, or run the Vite dev server while you work:

   ```powershell
   npm run build
   # or, in a second terminal:
   npm run dev
   ```

7. Start the app and open http://127.0.0.1:8000:

   ```powershell
   php artisan serve
   ```

   Health check: http://127.0.0.1:8000/up

### Tests

```powershell
php artisan test
```

Tests always run against the `yangs_test` database (set in `phpunit.xml`), never `yangs_pos`. A test fails if they ever point anywhere else.

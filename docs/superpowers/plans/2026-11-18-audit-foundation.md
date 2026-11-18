# Activity Log and Audit Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the audit trail on top of Step 4: model-level append-only guard, an owner log viewer (filters + old/new detail) on web and API, and a soft-delete convention for business records.

**Architecture:** Step 4 already built `activity_logs` (DB trigger rejects UPDATE/DELETE), `App\Services\Audit\ActivityLogger::log()`, and web/API login/logout logging with tests. This step adds an Eloquent guard, a `filter` scope shared by web and API, two pages, one API endpoint, and a `HasAuditedSoftDeletes` trait.

**Tech Stack:** Laravel 13, PostgreSQL 18, spatie/laravel-permission, Sanctum, Blade + Tailwind 4.

**Spec:** `docs/YANGS_SPEC.md` section 9; `docs/ARCHITECTURE.md` 3.14; owner's Step 5 brief (2026-11-18); `CLAUDE.md` audit and no-hard-delete rules.

## Global Constraints

- Permission: `logs.view` (web and API). Cashier must get 403.
- Append-only at two layers: Eloquent (LogicException before any SQL) and the DB trigger.
- Filters: user, action, date range (`from`/`to`, Manila calendar dates, inclusive). Newest first. 25 per page (API `per_page` 1–100).
- No new packages.

## Rulings

1. Keep `ActivityLogger` as an injectable service (`app(ActivityLogger::class)->log(...)` or constructor injection) instead of a static method: services already inject it, and tests can swap it. Same one-line call shape as the brief's example.
2. Soft-delete trait records *who* archived through the activity log (`<model>.archived` / `<model>.restored`) rather than a `deleted_by` column, because Laravel's soft delete writes only `deleted_at`; one source of truth for "who".
3. Add indexes on `activity_logs.user_id` and `activity_logs.action` (new migration) for the filters; the log grows with every business action.

## Review Focus

- Date range across midnight Manila: `to=2026-11-18` must include 23:59 Manila entries and exclude 00:00 the next day.
- Invalid filters (`to` before `from`, unknown user id, non-date): 422 on API, error message on web, never a 500.
- Large JSON values in the detail view are escaped (no XSS from logged user input).
- Force delete through the trait is refused and the row survives.
- Pagination keeps filters in page links.

---

### Task 1: Eloquent append-only guard

**Files:** Modify `app/Models/ActivityLog.php` (`booted()`: `updating` and `deleting` throw `LogicException`). Test: `tests/Feature/Audit/ActivityLogTest.php`.

- [ ] `test_model_refuses_update_and_delete_before_reaching_the_database`: `$log->update([...])` and `$log->delete()` throw `LogicException` (not `QueryException`); row unchanged. RED → implement → GREEN.

### Task 2: Filter scope, web viewer, API

**Files:**
- Create: `database/migrations/*_add_filter_indexes_to_activity_logs_table.php`, `app/Http/Requests/Audit/ActivityLogFilterRequest.php`, `app/Http/Controllers/Web/ActivityLogController.php` (`index`, `show`), `app/Http/Controllers/Api/V1/ActivityLogController.php` (`index`), `app/Http/Resources/V1/ActivityLogResource.php`, `resources/views/activity-logs/{index,show}.blade.php`
- Modify: `app/Models/ActivityLog.php` (`scopeFilter(Builder $q, array $filters)`), `routes/web.php`, `routes/api.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/Audit/ActivityLogViewerTest.php`

**Interfaces — Produces:** `ActivityLog::query()->filter(['user_id' => ?int, 'action' => ?string, 'from' => ?'Y-m-d', 'to' => ?'Y-m-d'])`; `GET /activity-logs`, `GET /activity-logs/{activityLog}`, `GET /api/v1/activity-logs` → paginated `ActivityLogResource` (`id, user_id, user_name, approved_by, action, subject_type, subject_id, old_values, new_values, ip_address, created_at` ISO 8601).

Tests: `test_owner_sees_a_paginated_newest_first_list`, `test_filters_by_user_action_and_inclusive_manila_date_range`, `test_detail_shows_old_and_new_values_escaped`, `test_api_lists_filtered_logs`, `test_invalid_filters_are_rejected`, `test_cashier_cannot_view_logs_on_web_or_api`.

### Task 3: Soft-delete convention

**Files:** Create `app/Models/Concerns/HasAuditedSoftDeletes.php`. Modify `CLAUDE.md` (No hard deletes section). Test: `tests/Feature/Audit/AuditedSoftDeletesTest.php` (fixture model on a table created in the test).

Tests: `test_delete_archives_and_logs`, `test_restore_logs`, `test_force_delete_is_refused`.

### Task 4: Verify

- [ ] `php artisan migrate`, `php artisan test`, `npm run build` pass; tick nothing new in PROGRESS.md (audit is not a separate checklist line).

# Employees and Scheduling – Design (Step 19)

## Intent

The owner needs one place for the people who work at YANGS: who they are, how they are paid, and when they work.
Pay must be private and its history exact, because payroll (Step 20) is built on it. The weekly schedule must be
quick to fill on a tablet and must never let one person be in two shifts at once, including overnight shifts.

## Goals

- Employee records with pay rate history (a raise starts on a chosen date) and an optional link to a login.
- Night bonuses: extra cash the owner hands an employee on a given night.
- Shift templates and per-employee schedules that may cross midnight, with overlap detection in the database.
- A weekly grid (employees × days) to assign, edit, cancel and copy a week; a daily view.
- Every change logged (who, what, old and new values); schedule history visible on the schedule.
- REST API for all of it under `/api/v1`.

## Non-goals

- Attendance, payroll, advances (Step 20 and later). Bonuses are recorded now; payroll reads them later.
- Hourly pay (not asked for), employee numbers, employee self-service, notifications of upcoming shifts.
- Cash drawer movements for bonuses (cashier shifts are not built).

## Anti-goals

- A supervisor who builds schedules seeing anyone's pay.
- A pay change that silently rewrites past pay (a raise must never change a day before its start date).
- A bonus counted twice in profit (as a bonus and again as an "Employee-related" expense).
- Two overlapping live shifts for one employee, by any path (web, API, copy week, a race).

## Owner decisions

- **Rate history (option A):** each pay change is a new row with a start date; payroll uses the rate in force on each
  day worked.
- **Night bonus:** the owner's choice, for that night only, **paid in cash that night**. Payroll (Step 20) shows it in
  gross pay as already paid and never pays it again. In the P&L it is payroll cost, counted once.
- **Schedule history:** edit in place, old and new values in the activity log (approach A below).
- **Weekly grid:** server-rendered with a dialog per cell (approach A below).
- **Everything is editable** (owner, on approval): bonuses, pay rates, shift templates and schedules all have an Edit
  button. Schedules and templates change in place (activity log keeps old values). Bonuses and rates are payroll
  records, so an edit keeps the old version: on save the old row is replaced by a new one that points to it, the old
  values stay visible in the record's history, and payroll uses the latest version.

## Approaches considered

1. **History:** (A, picked) edit in place plus activity log: one row per shift, simple grid, attendance can point at
   it later. (B) every change cancels and re-creates the row: full history in the table, but the grid and attendance
   must follow chains of rows; heavier, and schedules are not money records.
2. **Grid:** (A, picked) server-rendered grid, `x-ui.modal` dialog per cell, page reload on save, errors shown in the
   dialog. Same pattern as the other management pages. (B) live grid over the API like the POS: faster feel, about
   twice the code and its own per-cell error handling; not worth it for a schedule edited a few times a week.

## Data

All money `decimal(12,2)`; all timestamps `timestamptz`; business day cutoff 06:00 (`config('pos.business_day_cutoff')`).

- **`employees`**: `full_name` (120), `phone` (30, null), `email` (120, null), `address` (300, null), `position` (60),
  `employment_status` (`active | on_leave | resigned | terminated`), `date_hired`, `date_separated` (null; required for
  `resigned` / `terminated`, not before `date_hired`), `emergency_contact_name` (120, null), `emergency_contact_phone`
  (30, null), `user_id` (null, unique, FK users), timestamps, `softDeletesTz` (`HasAuditedSoftDeletes`).
  - *ERD change (builder call):* the link is `employees.user_id`, not `users.employee_id`, so the employee service owns
    it and never writes the users table. ARCHITECTURE §3.13 gets a one-line update.
- **`employee_rates`** (append-only, `reject_append_only_change()` trigger): `employee_id`, `pay_type`
  (`daily | weekly | monthly | custom`), `rate` (> 0), `custom_basis` (80, required when `custom`, e.g. "per event"),
  `effective_from` (date), `replaces_id` (null, unique, FK `employee_rates`), `created_by`, `created_at`.
  - **Editing a rate** (amount, pay type, basis or start date) adds a new row with `replaces_id` = the edited row, and
    needs a reason. A replaced row is history only.
  - The rate for a date = among rows not replaced by another row, the one with the latest `effective_from ≤ date`,
    ties broken by highest `id`. An employee is created with a first rate (`effective_from` = `date_hired`).
- **`employee_bonuses`** (void once, like expenses; trigger refuses delete and any update except one void):
  `employee_id`, `business_date`, `amount` (> 0), `reason` (200), `given_by`, `replaces_id` (null, unique, FK
  `employee_bonuses`), `voided_at`, `voided_by`, `void_reason`. The date defaults to today's business date and cannot
  be in the future.
  - **Editing a bonus** (employee, date, amount, reason) needs a "why" and, in one transaction, voids the old row with
    that reason and records the new one with `replaces_id` = the old row. Logged once as `employee.bonus_changed`
    with old and new values. The bonus page shows the chain ("Replaced on … by …").
  - Removing a bonus is still a void with a reason.
- **`shifts`** (templates): `name` (40, unique), `start_time`, `end_time` (end ≤ start means it ends the next day),
  `is_active`. Editable (name, times, active); an edit never moves schedules already made from it, because schedules
  store real times. Seeded once, production-safe like `ExpenseCategorySeeder`: Morning 06:00–14:00, Afternoon 14:00–20:00,
  Evening 20:00–04:00 (builder call: the bar opens at 20:00; the owner edits them). "Custom" is not a row: it is a
  schedule with `shift_id` null.
- **`schedules`**: `employee_id`, `shift_id` (null = custom), `starts_at`, `ends_at`, `duty_date` (business date of
  `starts_at`), `note` (200, null), `created_by`, `updated_by` (null), `cancelled_at`, `cancelled_by`,
  `cancel_reason` (200), timestamps.
  - `CHECK (ends_at > starts_at AND ends_at - starts_at <= interval '16 hours')` (builder call: no shift longer than
    16 h, catches a wrong AM/PM).
  - `CREATE EXTENSION IF NOT EXISTS btree_gist`; `EXCLUDE USING gist (employee_id WITH =, tstzrange(starts_at,
    ends_at, '[)') WITH &&) WHERE (cancelled_at IS NULL)`. Back-to-back shifts (one ends 20:00, next starts 20:00) are
    allowed.
  - Cancelling replaces deleting. Schedules are never hard deleted.

## Times on the grid

A cell is one employee on one business day. Times entered for that cell map to real moments like this:

- start = `duty_date + start_time`, plus one day when `start_time` is before the cutoff (02:00 on the Nov 18 business
  day is Nov 19 02:00);
- end = start's calendar date + `end_time`, plus one day when that is not after start (20:00–04:00 ends the next day).
- `duty_date` is always `BusinessDate::for(starts_at)`, so it equals the cell.

## Rules

- Only `active` employees can be given new schedules or bonuses; `on_leave` and separated ones are shown greyed.
- Setting `resigned` or `terminated` cancels that employee's live schedules starting after `date_separated`, in the same
  transaction, each logged (`schedule.cancelled`, reason "Employee separated").
- **Overlap:** the service checks first and refuses with a message naming the clash ("Ana already works Evening,
  Fri Nov 20 20:00–04:00"). The exclusion constraint is the backstop for races; its violation (SQLSTATE `23P01`) is
  turned into the same message.
- **Copy week:** copies every live schedule of a source week (Monday start) to a target week, same weekday and times,
  same template. Each one either copies or is skipped: overlap, employee not active, or the copy would be before today's
  business date. One transaction; a summary lists copied and skipped (with why). Logged once as `schedule.week_copied`
  with counts, and each copy as `schedule.created`.
- Editing or cancelling a past schedule is allowed (corrections before payroll) and logged.
- Rate changes and rate edits: `effective_from` may be past or future. A new rate is logged as
  `employee.rate_changed`, an edit as `employee.rate_edited` (with the reason); both with old and new pay type, rate,
  basis and start date.
- Activity log actions: `employee.created`, `employee.updated` (changed fields only, never pay), `employee.archived`,
  `employee.restored`, `employee.rate_changed`, `employee.rate_edited`, `employee.bonus_given`, `employee.bonus_changed`,
  `employee.bonus_voided`, `shift.updated`,
  `schedule.created`, `schedule.changed` (old and new start, end, template, note), `schedule.cancelled`,
  `schedule.week_copied`.

## Permissions

Existing permissions, none Cashier defaults:
- `employees.view`: employee list and page without pay.
- `employees.manage`: create, edit, archive, link a login, rates, bonuses; sees pay.
- `schedules.view`: weekly grid and daily view.
- `schedules.manage`: assign, edit, cancel, copy week, edit shift templates.

Bonuses on the daily view appear only with `employees.manage`.

## Screens (Blade, `x-ui.*` components, `design-system/YANGS/MASTER.md`, 48 px targets, portrait and landscape)

- **Employees** (`/employees`): list with status filter; employee page with details, current rate and rate history,
  bonuses, and a link to their week. Forms for employee, rate change (with start date) and bonus (with the line
  "Paid in cash tonight. Do not also record it as an expense.").
- **Schedule** (`/schedule?week=2026-W48`): grid, rows = employees (active first), columns Mon–Sun with dates.
  - A cell shows its shifts (template name or "Custom", times). Tap an empty cell: dialog with template buttons and
    custom start / end, note. Tap a shift: edit dialog with its history and "Cancel shift" (reason).
  - Prev / next week, "Copy this week to…" (default next week) with the summary as a flash.
  - Portrait: the grid scrolls sideways inside its card with the name column sticky.
- **Day** (`/schedule/day?date=…`): shifts sorted by start, with times and duration; bonuses of that night and
  "Give bonus" for `employees.manage`.
- **Shift templates**: small edit list (times, active) for `schedules.manage`.

## API (`/api/v1`, Sanctum, same permissions and Form Requests)

- `employees` index (filter status), show, store, update; `employees/{id}/archive`, `employees/{id}/restore`.
- `employees/{id}/rates` index, store; `employee-rates/{id}` update (edit, keeps history).
- `employees/{id}/bonuses` index, store; `employee-bonuses/{id}` update (edit, keeps history); `employee-bonuses/{id}/void`.
- `shifts` index, update.
- `schedules` index (`week=` or `date=`, optional `employee_id`), store, update; `schedules/{id}/cancel`;
  `schedules/copy-week` (`from_week`, `to_week`).
- Resources hide every pay field unless the user has `employees.manage`.

## Tests (feature, PostgreSQL)

- Overlap: same employee same day; overnight 20:00–04:00 against next day 02:00–06:00; Sunday-night shift against Monday
  of the next week; back-to-back allowed; another employee at the same time allowed; a cancelled shift frees the slot;
  an edit that creates an overlap refused; the database constraint alone refuses an insert that skips the service.
- Times: a 02:00 start on a business day lands on the next calendar day; `duty_date` equals the cell; 16 h cap.
- History: create, change and cancel write `schedule.*` entries with old and new values and the user.
- Copy week: copies, skips overlaps and inactive employees with reasons, all in one transaction.
- Rates: rate for a date picks the right row (past, future, same-day); an edit replaces the row for every date
  including a moved start date, keeps the old row, needs a reason, and is logged; a replaced row cannot be edited again.
- Bonuses: recorded; an edit voids the old row and records the new one in one transaction (the night's total counts
  only the new amount); voided once; rows never updated otherwise or deleted (model and trigger); future date refused.
- Templates: an edit does not change existing schedules.
- Separation cancels later schedules. Pay hidden without `employees.manage` (web and API). Cashier gets 403.

## Left to the builder

Query shapes, Alpine wiring of the dialogs, exact copy of messages within the rules above, labels and the order of
form fields.

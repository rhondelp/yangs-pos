# Employees and Scheduling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Employee records with editable pay-rate history and night bonuses, shift templates, overlap-safe schedules (overnight included), a weekly grid and a day view, and the matching `/api/v1` endpoints.

**Architecture:** Five tables (`employees`, `employee_rates`, `employee_bonuses`, `shifts`, `schedules`), with services in `app/Services/Staff/`. Overlaps are refused by a PostgreSQL exclusion constraint, and the service checks first so it can give a readable message. Payroll records (rates, bonuses) are never updated in place: an edit writes a new row with `replaces_id`. Web pages are server-rendered Blade with `x-ui.modal` dialogs; the API reuses the same Form Requests and services.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 18 (`btree_gist`), Blade + Tailwind + Alpine, bcmath via `App\Support\Money`.

**Spec:** `docs/superpowers/specs/2026-11-23-employees-and-scheduling-design.md`

## Global Constraints

- Money `decimal(12,2)`, validated as `/^\d{1,10}(\.\d{1,2})?$/` and > 0; never floats.
- Timestamps `timestamptz`, app timezone `Asia/Manila`, business day cutoff `config('pos.business_day_cutoff')` (06:00); `duty_date` = `BusinessDate::for(starts_at)`.
- Business logic in services (`app/Services/Staff/*`), which also check the permission (`$by->can(...)`) and throw `AuthorizationException`. Shape validation is in Form Requests (`app/Http/Requests/Staff/*`). Business-rule failures throw `ValidationException` keyed by the input name.
- Every write runs in `DB::transaction` and logs with `ActivityLogger::log($action, $subject, $old, $new, $by)` inside it, changed fields only. Pay values are never in `employee.updated`.
- `employees` uses `HasAuditedSoftDeletes` + `softDeletesTz()`. `employee_rates` is append-only (`reject_append_only_change()` trigger + model refuses update/delete). `employee_bonuses` is void-once (trigger like `expenses_void_once()` + model guard like `App\Models\Expense`). `schedules` and `shifts` are editable; schedules are never deleted (cancel instead).
- Permissions (existing, none are Cashier defaults): `employees.view`, `employees.manage`, `schedules.view`, `schedules.manage`. Pay fields are visible only with `employees.manage`.
- UI: read `design-system/YANGS/MASTER.md`, use `x-ui.*` components, sentence case, 48 px touch targets, check at 768×1024 and 1024×768. Use the `ui-ux-pro-max` skill for the grid layout.
- No `git commit` by the agent. Run tests with `php artisan test --compact`, using PowerShell syntax.

## Review Focus

1. **Editing a shift without changing its times** must not clash with itself. Test: Task 4, `test_an_edit_does_not_clash_with_itself`.
2. **A Sunday 20:00–04:00 shift** ends in the next ISO week. It must show in the Sunday column and copy to next Sunday, and it must still clash with a Monday 02:00 shift. Test: Task 4, `test_sunday_overnight_crosses_the_week`.
3. **A bad `week=` or `date=` value** (`2026-W99`, `2026-02-30`) must be refused with a message, not crash. Test: Task 5, `test_bad_week_and_date_are_refused`.
4. **Linking a login that is already linked** to another employee must be refused with a message naming that employee. Test: Task 2, `test_a_login_links_to_one_employee`.
5. **The rate for a day before the first rate** must be null, so payroll can say "no rate". Test: Task 2, `test_rate_on_a_day_before_any_rate_is_null`.

---

### Task 1: Schema, models and seeded templates

**Files:**
- Create: `database/migrations/2026_11_23_100000_create_staff_tables.php`
- Create: `app/Enums/EmploymentStatus.php` (`Active = 'active'`, `OnLeave = 'on_leave'`, `Resigned = 'resigned'`, `Terminated = 'terminated'`; `label(): string`; `isSeparated(): bool`)
- Create: `app/Enums/PayType.php` (`Daily`, `Weekly`, `Monthly`, `Custom`; `label(): string`)
- Create: `app/Models/Employee.php`, `EmployeeRate.php`, `EmployeeBonus.php`, `Shift.php`, `Schedule.php`
- Create: `database/seeders/ShiftSeeder.php`. Modify: `database/seeders/DatabaseSeeder.php` (call it after `ExpenseCategorySeeder`)
- Test: `tests/Feature/Staff/StaffSchemaTest.php`

**Interfaces:**
- Produces: the columns exactly as in the spec's Data section, plus these model relations and helpers:
  - `Employee::rates()`, `bonuses()`, `schedules()`, `user()`;
  - `EmployeeRate::replaces()`, `EmployeeBonus::replaces()`, `EmployeeBonus::isVoided()`;
  - `Schedule::employee()`, `shift()`, `isCancelled()`.
- Produces: `ShiftSeeder::DEFAULTS = [['Morning', '06:00', '14:00'], ['Afternoon', '14:00', '20:00'], ['Evening', '20:00', '04:00']]`. It only adds missing names (case-insensitive) and never changes an edited template.

- [ ] **Step 1: Write the failing tests**:
  - `test_the_database_refuses_overlapping_live_shifts`: raw `DB::table('schedules')->insert` of Nov 20 20:00 → Nov 21 04:00 (`+08:00`), then Nov 21 02:00 → 06:00 for the same employee, throws `QueryException` with SQLSTATE `23P01`. The same insert passes for another employee, for a back-to-back 04:00 → 06:00, and after the first row gets `cancelled_at`.
  - `test_shift_length_is_checked`: `ends_at = starts_at` and a 16 h 1 min shift both throw (check violation); exactly 16 h passes.
  - `test_rates_are_append_only`: `DB::table('employee_rates')->update` and `->delete` throw; `EmployeeRate::update()` throws `LogicException`.
  - `test_a_bonus_is_voided_once_and_never_edited`: same shape as `ExpenseSchemaTest::test_the_database_refuses_edits_deletes_and_bad_rows`, covering the void columns, a second void, an amount edit and a delete.
  - `test_the_shift_seeder_adds_three_templates_once`: run twice, 3 rows; edit Evening to 21:00, rerun, still 21:00.
- [ ] **Step 2: Run and watch them fail**: `php artisan test tests/Feature/Staff/StaffSchemaTest.php --compact`. Expected: FAIL (tables missing).
- [ ] **Step 3: Write the migration** (`CREATE EXTENSION IF NOT EXISTS btree_gist` first; the exclusion and check constraints as in the spec; a trigger function `employee_bonuses_void_once()` modelled on `expenses_void_once()`; `reject_append_only_change()` on `employee_rates`; indexes on every FK, `schedules(duty_date)`, `employee_bonuses(business_date)`). Write the models with `casts()` (dates, `decimal:2`, enums, `time` columns as strings `H:i`), the guards, and the seeder.
- [ ] **Step 4: Run and watch them pass** (same command).

### Task 2: Employees and pay rates (`EmployeeService`)

**Files:**
- Create: `app/Services/Staff/EmployeeService.php`
- Test: `tests/Feature/Staff/EmployeeServiceTest.php`

**Interfaces:**
- Produces:
  - `create(User $by, array $input): Employee`. `$input` has `full_name, phone?, email?, address?, position, employment_status, date_hired, date_separated?, emergency_contact_name?, emergency_contact_phone?, user_id?, pay_type, rate, custom_basis?`. It writes the employee and the first rate (`effective_from` = `date_hired`) and logs `employee.created` (no pay values) and `employee.rate_changed`.
  - `update(Employee $employee, User $by, array $input): Employee`: same keys without the pay keys. Logs `employee.updated` with the changed fields. The call that cancels later schedules on separation is added in Task 4 (`ScheduleService` does not exist yet).
  - `archive(Employee, User): void`, `restore(Employee, User): void`: these run `delete()`/`restore()` in a transaction, which logs `employee.archived` / `employee.restored`.
  - `addRate(Employee $employee, User $by, array $input): EmployeeRate`, where `$input` is `pay_type, rate, custom_basis?, effective_from`. Logs `employee.rate_changed` with old (the rate in force on `effective_from`) and new.
  - `editRate(EmployeeRate $rate, User $by, array $input): EmployeeRate`, where `$input` is the `addRate` keys plus `reason`. It writes a new row with `replaces_id` and logs `employee.rate_edited`. It refuses a row that is already replaced: "This rate was already changed. Edit the latest version."
  - `rateOn(Employee $employee, string $date): ?EmployeeRate`: among the rows that are not replaced, the latest `effective_from ≤ $date`, then the highest id.
- All writes need `employees.manage`.

- [ ] **Step 1: Write the failing tests**:
  - `test_create_writes_the_first_rate_and_logs_without_pay`: `employee.created` has no `rate` key; `rateOn($e, date_hired)` is the 600.00 daily rate.
  - `test_rate_history_by_date`: hired 2026-11-01 at 600 daily; addRate 650 from 2026-11-16; addRate 700 from 2026-12-01. `rateOn` gives 600 on 11-15, 650 on 11-16 and on 11-30, and 700 on 12-01.
  - `test_edit_rate_keeps_history`:
    - edit the 650 row to 660 from 2026-11-18 (reason "Typo");
    - `rateOn` 11-17 is 600 and 11-18 is 660;
    - the old row still exists;
    - editing the old row again is refused;
    - a missing reason is refused;
    - `employee.rate_edited` is logged with old and new.
  - `test_rate_on_a_day_before_any_rate_is_null` (Review Focus 5).
  - `test_custom_pay_needs_a_basis`; `test_rate_must_be_positive_money` (`'0'`, `'-1'`, `'12.345'` refused).
  - `test_separation_needs_a_date_not_before_hire`.
  - `test_a_login_links_to_one_employee` (Review Focus 4): the message is "This login already belongs to Ana Cruz."
  - `test_writes_need_employees_manage`: a Supervisor without it gets `AuthorizationException`.
- [ ] **Step 2: Run and watch them fail**: `php artisan test tests/Feature/Staff/EmployeeServiceTest.php --compact`.
- [ ] **Step 3: Implement `EmployeeService`.**
- [ ] **Step 4: Run and watch them pass.**

### Task 3: Night bonuses (`BonusService`)

**Files:**
- Create: `app/Services/Staff/BonusService.php`
- Test: `tests/Feature/Staff/BonusServiceTest.php`

**Interfaces:**
- Produces:
  - `give(User $by, array $input): EmployeeBonus`. `$input` has `employee_id, business_date?, amount, reason`; the date defaults to `BusinessDate::for(now())`. The employee must be `active`. Logs `employee.bonus_given`.
  - `edit(EmployeeBonus $bonus, User $by, array $input): EmployeeBonus`. `$input` has `employee_id, business_date, amount, reason, edit_reason`. It locks the row, refuses a voided one, voids the old row with `void_reason` = `edit_reason`, records the new one with `replaces_id`, and logs `employee.bonus_changed` with old and new. All in one transaction.
  - `void(EmployeeBonus $bonus, User $by, ?string $reason): EmployeeBonus`: locks the row, refuses a second void, logs `employee.bonus_voided`.
  - `forNight(string $date): Collection<EmployeeBonus>`: live bonuses with their employee.
- All need `employees.manage`.

- [ ] **Step 1: Write the failing tests**:
  - `test_give_and_list_a_night`: Ana 300.00 and Ben 200.00 on 2026-11-20; `forNight` totals 500.00; it is logged.
  - `test_edit_replaces_and_keeps_history`: edit Ana 300 → 350 (edit reason "Owner raised it"). `forNight` totals 550.00. The old row is voided with that reason, the new `replaces_id` points to it, and `employee.bonus_changed` is logged. Editing the old row is refused.
  - `test_void_once`; `test_future_date_refused` ("The date cannot be after today."); `test_only_active_employees`; `test_needs_employees_manage`.
- [ ] **Step 2: Run and watch them fail.**
- [ ] **Step 3: Implement `BonusService`.**
- [ ] **Step 4: Run and watch them pass.**

### Task 4: Shift templates and schedules (`ShiftService`, `ScheduleService`)

**Files:**
- Create: `app/Services/Staff/ShiftService.php`, `app/Services/Staff/ScheduleService.php`
- Modify: `app/Services/Staff/EmployeeService.php` (`update` calls `cancelAfter`)
- Test: `tests/Feature/Staff/ScheduleServiceTest.php`

**Interfaces:**
- Produces:
  - `ShiftService::update(Shift $shift, User $by, array $input): Shift`. `$input` has `name, start_time, end_time, is_active`; start ≠ end. Logs `shift.updated`. Needs `schedules.manage`.
  - `ScheduleService::times(string $dutyDate, string $start, string $end): array{0: CarbonImmutable, 1: CarbonImmutable}` (Asia/Manila):
    - start = date + time, plus 1 day if the time is before the cutoff;
    - end = start's date + time, plus 1 day if that is not after start.
  - `create(User $by, array $input): Schedule`. `$input` has `employee_id, duty_date, shift_id?, start_time?, end_time?, note?`. With `shift_id` and no times, the template's times are used. The employee must be `active`. Logs `schedule.created`.
  - `update(Schedule $schedule, User $by, array $input): Schedule`: same keys except `employee_id`, which is fixed. Refuses a cancelled schedule. Logs `schedule.changed` with old and new `starts_at, ends_at, shift, note`.
  - `cancel(Schedule $schedule, User $by, string $reason): Schedule`. Logs `schedule.cancelled`.
  - `copyWeek(User $by, string $fromWeek, string $toWeek): array{copied: int, skipped: list<array{employee: string, date: string, reason: string}>}`. Weeks are `YYYY-Www`. The skip reasons are "Overlaps another shift", "Not active" and "In the past". Logs `schedule.week_copied` with counts.
  - `cancelAfter(Employee $employee, string $date, User $by): int`: cancels live schedules with `duty_date > $date`, reason "Employee separated".
  - `week(string $isoWeek): array{days: list<string>, employees: Collection<Employee>, cells: array<int, array<string, list<Schedule>>>}`. Rows are the employees who are active or have a schedule that week, active first, then by name. `cells[employee_id][duty_date]`.
  - `day(string $date): Collection<Schedule>`: live schedules for that duty date, ordered by `starts_at`.
  - The overlap message, from both the pre-check and a caught `QueryException` with SQLSTATE `23P01`, is `":name already works :shift, :day :start–:end."`, for example "Ana Cruz already works Evening, Fri Nov 20 20:00–04:00." It is keyed `start_time`.
- Create, update, cancel and copy need `schedules.manage`.

- [ ] **Step 1: Write the failing tests**:
  - `test_times_cross_midnight_and_follow_the_cutoff`:
    - (`2026-11-20`, 20:00, 04:00) runs Nov 20 20:00 → Nov 21 04:00;
    - (`2026-11-20`, 02:00, 05:00) runs Nov 21 02:00 → Nov 21 05:00;
    - (`2026-11-20`, 06:00, 14:00) runs on Nov 20.
  - `test_overlap_rules`:
    - refused: Ana Evening on 11-20, then 11-20 02:00–05:00 (that is Nov 21 02:00) with the exact message;
    - refused: the same day, overlapping evening times;
    - allowed: back-to-back 04:00–06:00;
    - allowed: Ben at the same time;
    - allowed: the slot after the first shift is cancelled.
  - `test_sunday_overnight_crosses_the_week` (Review Focus 2):
    - a Sunday 2026-11-22 Evening shows in `week('2026-W47')` under 11-22;
    - a shift on Mon 2026-11-23 starting 02:00 (which falls on Nov 24) does not clash;
    - a shift on Sun 2026-11-22 at 02:00–05:00 (Nov 23 02:00) clashes.
  - `test_an_edit_does_not_clash_with_itself` (Review Focus 1); `test_an_edit_into_an_overlap_is_refused`.
  - `test_history_on_change`: create, change from Evening to custom 21:00–03:00, then cancel. Three log entries, each with the user; `schedule.changed` old `starts_at` is 20:00 and new is 21:00.
  - `test_copy_week`:
    - copies 3 shifts from W47 to W48 with the same weekday and times;
    - skips 1 that overlaps an existing W48 shift and 1 for an employee now on leave;
    - the reasons are in `skipped`, and the log has counts.
  - `test_a_template_edit_leaves_existing_schedules`.
  - `test_separation_cancels_later_schedules`: `EmployeeService::update` sets `resigned` with `date_separated` 2026-11-21. The 11-22 shift is cancelled and the 11-20 shift is kept.
  - `test_cashier_cannot_schedule`.
- [ ] **Step 2: Run and watch them fail.**
- [ ] **Step 3: Implement both services and the `EmployeeService::update` seam.**
- [ ] **Step 4: Run all Staff tests and watch them pass**: `php artisan test tests/Feature/Staff --compact`.

### Task 5: API

**Files:**
- Create Form Requests in `app/Http/Requests/Staff/`:
  - `EmployeeRequest`: one class for store and update; on update the pay keys are prohibited;
  - `RateRequest`: `reason` is required on edit;
  - `BonusRequest`: `edit_reason` is required on edit;
  - `VoidBonusRequest`, `ShiftRequest`, `ScheduleRequest`, `CancelScheduleRequest`, `CopyWeekRequest`;
  - `ScheduleFilterRequest`: `week` regex `^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$` and a real ISO week; `date` as `date_format:Y-m-d`; `employee_id`.
- Create resources in `app/Http/Resources/V1/`:
  - `EmployeeResource`: the pay fields `pay_type, rate, custom_basis` (current rate) only when `$request->user()->can('employees.manage')`;
  - `EmployeeRateResource`, `EmployeeBonusResource`, `ShiftResource`, `ScheduleResource`.
- Create controllers in `app/Http/Controllers/Api/V1/`: `EmployeeController`, `EmployeeRateController`, `EmployeeBonusController`, `ShiftController`, `ScheduleController`.
- Modify: `routes/api.php`, adding the routes in the spec's API section:
  - views: `can:employees.view` / `can:schedules.view`;
  - writes: `can:employees.manage` / `can:schedules.manage`;
  - rates and bonuses routes: `can:employees.manage`, including the index routes, since they are pay.
- Test: `tests/Feature/Staff/StaffApiTest.php`

**Interfaces:**
- Consumes: the Task 2–4 services.
- Produces: the route names `api.v1.employees.*`, `api.v1.employee-rates.update`, `api.v1.employee-bonuses.update|void`, `api.v1.shifts.*` and `api.v1.schedules.index|store|update|cancel|copy-week`.

- [ ] **Step 1: Write the failing tests**:
  - `test_employee_crud_and_pay_privacy`: the Owner sees `data.rate`; a Supervisor given only `employees.view` gets no `rate` key and a 403 on `employees/{id}/rates`.
  - `test_schedule_api_week_and_overlap`: store, then a 422 with the overlap message on `start_time`; the index with `week=2026-W47` lists the schedule.
  - `test_rate_and_bonus_edits_over_the_api`.
  - `test_copy_week_returns_the_summary`.
  - `test_bad_week_and_date_are_refused` (Review Focus 3): `week=2026-W99` and `date=2026-02-30` return 422.
  - `test_cashier_gets_403_everywhere` (one call per route group).
- [ ] **Step 2: Run and watch them fail**: `php artisan test tests/Feature/Staff/StaffApiTest.php --compact`.
- [ ] **Step 3: Implement the requests, resources, controllers and routes.**
- [ ] **Step 4: Run and watch them pass.**

### Task 6: Employee pages

**Files:**
- Create: `app/Http/Controllers/Web/EmployeeController.php` (index, create, store, show, edit, update, archive, restore, storeRate, updateRate, storeBonus, updateBonus, voidBonus)
- Create: `resources/views/employees/index.blade.php`, `form.blade.php`, `show.blade.php` (details, current rate, rate history with "Replaced" rows and the edit dialog, bonuses with edit and void dialogs, "See their week" link)
- Modify: `routes/web.php`, `app/Support/Navigation.php` (add "Employees", icon `users`, `Permission::EmployeesView`, after "Profit & loss"), `tests/Feature/Ui/NavigationTest.php`
- Test: `tests/Feature/Staff/EmployeeWebTest.php`

- [ ] **Step 1: Write the failing tests**:
  - `test_employee_pages_and_pay_privacy`: the Owner sees "₱600.00 a day"; an `employees.view`-only user sees the page but not "₱600.00".
  - `test_rate_edit_dialog_keeps_history`: after the edit, the page shows the new rate and a "Replaced" row.
  - `test_bonus_form_warns_and_edits`: the bonus form shows "Paid in cash tonight. Do not also record it as an expense."; edit and void work.
  - `test_cashier_cannot_open_employees` (403).
- [ ] **Step 2: Run and watch them fail.**
- [ ] **Step 3: Build the pages.**
- [ ] **Step 4: Run and watch them pass.**

### Task 7: Schedule grid, day view and templates

**Files:**
- Create: `app/Http/Controllers/Web/ScheduleController.php` (week, day, store, update, cancel, copyWeek), `app/Http/Controllers/Web/ShiftController.php` (index, update)
- Create: `resources/views/schedule/week.blade.php`, `day.blade.php`, `_dialog.blade.php` (shared assign/edit dialog: template buttons, custom start/end, note, history list when editing, cancel with reason), `resources/views/shifts/index.blade.php`
- Modify: `routes/web.php`, `app/Support/Navigation.php` (add "Schedule", icon `clipboard-list`, `Permission::SchedulesView`, after "Employees"), `tests/Feature/Ui/NavigationTest.php`
- Test: `tests/Feature/Staff/ScheduleWebTest.php`

**Interfaces:**
- Consumes: `ScheduleService::week()`, `day()`, `create()`, `update()`, `cancel()`, `copyWeek()`; `BonusService::forNight()`; `ShiftService::update()`.
- Routes:
  - `schedule.week` (`GET /schedule?week=`, default the current business week);
  - `schedule.day` (`GET /schedule/day?date=`);
  - `schedule.store`, `schedule.update`, `schedule.cancel`, `schedule.copy`;
  - `shifts.index`, `shifts.update`.

- [ ] **Step 1: Write the failing tests**:
  - `test_week_grid_shows_shifts_and_greys_inactive`: it shows "Evening", "20:00–04:00", the week label "Nov 16 – Nov 22, 2026" and links to the previous and next week.
  - `test_assign_edit_and_cancel_from_the_grid`: an overlap redirects back with the message and the dialog reopened (the same pattern as the expense void dialog: `$errors` plus `x-init showModal`).
  - `test_copy_week_flashes_the_summary`: "Copied 3 shifts. Skipped 1: Ben Reyes, Sun Nov 29 – Not active."
  - `test_day_view_lists_shifts_and_bonuses_only_for_managers`.
  - `test_templates_page_edits_times`.
  - `test_schedule_view_only_user_sees_no_assign_controls`; `test_cashier_cannot_open_schedule` (403).
- [ ] **Step 2: Run and watch them fail.**
- [ ] **Step 3: Load the `ui-ux-pro-max` skill and read MASTER.md, then build the views.**
  - Portrait: the grid scrolls inside its card with a sticky name column.
  - Each cell is at least 48 px tall; an empty cell is a button labelled "Assign shift, Ana Cruz, Fri Nov 20".
  - Templates are big buttons with the times under the name.
- [ ] **Step 4: Run and watch them pass, then the whole suite**: `php artisan test --compact`.
- [ ] **Step 5: Screens.** Use `npm run build` and the dev server, and capture the week grid, the dialog, the day view and an employee page at 768×1024 and 1024×768 into `docs/screenshots/step-19/` (needs the owner login from the user), with the 48 px audit.
- [ ] **Step 6: Docs.**
  - `docs/PROGRESS.md`: tick Employee records and Scheduling, and add a Step 19 section.
  - `docs/MODULES.md`: add a Staff section.
  - `docs/ARCHITECTURE.md` §3.13: change the link to `employees.user_id`.
  - Run `graphify update .`.

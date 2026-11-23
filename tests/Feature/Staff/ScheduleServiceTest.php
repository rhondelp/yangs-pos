<?php

namespace Tests\Feature\Staff;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use App\Services\Staff\EmployeeService;
use App\Services\Staff\ScheduleService;
use App\Services\Staff\ShiftService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleService $schedules;

    private User $owner;

    private Employee $ana;

    private Employee $ben;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ShiftSeeder::class]);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 21:00', 'Asia/Manila'));
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->schedules = app(ScheduleService::class);
        $this->ana = $this->hire('Ana Cruz');
        $this->ben = $this->hire('Ben Reyes');
    }

    private function hire(string $name): Employee
    {
        return app(EmployeeService::class)->create($this->owner, ['full_name' => $name, 'position' => 'Bartender',
            'employment_status' => 'active', 'date_hired' => '2026-11-01', 'pay_type' => 'daily', 'rate' => '600']);
    }

    private function evening(Employee $employee, string $date): Schedule
    {
        return $this->schedules->create($this->owner, ['employee_id' => $employee->id, 'duty_date' => $date, 'shift_id' => Shift::where('name', 'Evening')->value('id')]);
    }

    private function custom(Employee $employee, string $date, string $start, string $end): Schedule
    {
        return $this->schedules->create($this->owner, ['employee_id' => $employee->id, 'duty_date' => $date, 'start_time' => $start, 'end_time' => $end]);
    }

    private function span(Schedule $s): string
    {
        return $s->starts_at->setTimezone('Asia/Manila')->format('Y-m-d H:i').' → '.$s->ends_at->setTimezone('Asia/Manila')->format('Y-m-d H:i');
    }

    private function refused(callable $call, string $key, ?string $message = null): void
    {
        try {
            $call();
            $this->fail("Expected a validation error on {$key}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
            $message === null || $this->assertSame($message, $e->errors()[$key][0]);
        }
    }

    public function test_times_cross_midnight_and_follow_the_cutoff(): void
    {
        $fmt = fn (array $t) => $t[0]->format('Y-m-d H:i').' → '.$t[1]->format('Y-m-d H:i');

        $this->assertSame('2026-11-20 20:00 → 2026-11-21 04:00', $fmt($this->schedules->times('2026-11-20', '20:00', '04:00')));
        $this->assertSame('2026-11-21 02:00 → 2026-11-21 05:00', $fmt($this->schedules->times('2026-11-20', '02:00', '05:00')));
        $this->assertSame('2026-11-20 06:00 → 2026-11-20 14:00', $fmt($this->schedules->times('2026-11-20', '06:00', '14:00')));
        $this->assertSame('2026-11-20', $this->custom($this->ana, '2026-11-20', '02:00', '05:00')->duty_date->toDateString());
    }

    public function test_overlap_rules(): void
    {
        $evening = $this->evening($this->ana, '2026-11-20');

        $this->refused(fn () => $this->custom($this->ana, '2026-11-20', '02:00', '05:00'), 'start_time', 'Ana Cruz already works Evening, Fri Nov 20 20:00–04:00.');
        $this->refused(fn () => $this->custom($this->ana, '2026-11-20', '18:00', '21:00'), 'start_time');
        $this->assertSame('2026-11-21 04:00 → 2026-11-21 06:00', $this->span($this->custom($this->ana, '2026-11-20', '04:00', '06:00'))); // back to back
        $this->evening($this->ben, '2026-11-20');

        $this->schedules->cancel($evening, $this->owner, 'Swapped');
        $this->assertSame('2026-11-20 22:00 → 2026-11-21 02:00', $this->span($this->custom($this->ana, '2026-11-20', '22:00', '02:00')));
    }

    public function test_sunday_overnight_crosses_the_week(): void
    {
        $sunday = $this->evening($this->ana, '2026-11-22');

        $this->assertSame([$sunday->id], collect($this->schedules->week('2026-W47')['cells'][$this->ana->id]['2026-11-22'])->pluck('id')->all());
        $this->custom($this->ana, '2026-11-23', '02:00', '05:00'); // Nov 24 02:00: no clash
        $this->refused(fn () => $this->custom($this->ana, '2026-11-22', '02:00', '05:00'), 'start_time'); // Nov 23 02:00
        $this->refused(fn () => $this->custom($this->ana, '2026-11-23', '03:00', '05:00'), 'start_time'); // Nov 24 03:00 clashes with the one above
    }

    public function test_an_edit_does_not_clash_with_itself(): void
    {
        $shift = $this->evening($this->ana, '2026-11-20');

        $edited = $this->schedules->update($shift, $this->owner, ['duty_date' => '2026-11-20', 'shift_id' => $shift->shift_id, 'note' => 'Bring keys']);
        $this->assertSame('Bring keys', $edited->note);
        $this->assertSame('2026-11-20 20:00 → 2026-11-21 04:00', $this->span($edited));
    }

    public function test_an_edit_into_an_overlap_is_refused(): void
    {
        $this->evening($this->ana, '2026-11-20');
        $morning = $this->custom($this->ana, '2026-11-21', '06:00', '14:00');

        $this->refused(fn () => $this->schedules->update($morning, $this->owner, ['duty_date' => '2026-11-20', 'start_time' => '03:00', 'end_time' => '10:00']), 'start_time');
    }

    public function test_history_on_change(): void
    {
        $shift = $this->evening($this->ana, '2026-11-20');
        $this->schedules->update($shift, $this->owner, ['duty_date' => '2026-11-20', 'start_time' => '21:00', 'end_time' => '03:00']);
        $this->schedules->cancel($shift->fresh(), $this->owner, 'Closed early');

        $logs = ActivityLog::where('subject_type', $shift->getMorphClass())->where('subject_id', $shift->id)->orderBy('id')->get();
        $this->assertSame(['schedule.created', 'schedule.changed', 'schedule.cancelled'], $logs->pluck('action')->all());
        $this->assertSame([$this->owner->id], $logs->pluck('user_id')->unique()->values()->all());
        $changed = $logs[1];
        $this->assertSame(['2026-11-20 20:00', '2026-11-20 21:00'], [$changed->old_values['starts_at'], $changed->new_values['starts_at']]);
        $this->assertSame(['Evening', 'Custom'], [$changed->old_values['shift'], $changed->new_values['shift']]);
        $this->assertSame('Closed early', $logs[2]->new_values['reason']);
        $this->refused(fn () => $this->schedules->update($shift->fresh(), $this->owner, ['duty_date' => '2026-11-20', 'start_time' => '21:00', 'end_time' => '03:00']), 'schedule');
    }

    public function test_copy_week(): void
    {
        $cara = $this->hire('Cara Lim');
        $this->evening($this->ana, '2026-11-20');
        $this->evening($this->ana, '2026-11-21');
        $this->evening($this->ben, '2026-11-20');
        $this->evening($this->ben, '2026-11-22');
        $this->evening($cara, '2026-11-22');
        $this->custom($this->ana, '2026-11-27', '22:00', '02:00'); // already in W48
        app(EmployeeService::class)->update($cara, $this->owner, ['employment_status' => 'on_leave']);

        $result = $this->schedules->copyWeek($this->owner, '2026-W47', '2026-W48');

        $this->assertSame(3, $result['copied']);
        $this->assertEqualsCanonicalizing([
            ['employee' => 'Ana Cruz', 'date' => '2026-11-27', 'reason' => 'Overlaps another shift'],
            ['employee' => 'Cara Lim', 'date' => '2026-11-29', 'reason' => 'Not active'],
        ], $result['skipped']);
        $this->assertSame('2026-11-28 20:00 → 2026-11-29 04:00', $this->span(Schedule::where('employee_id', $this->ana->id)->whereDate('duty_date', '2026-11-28')->sole()));
        $this->assertSame('Evening', Schedule::where('employee_id', $this->ben->id)->whereDate('duty_date', '2026-11-29')->sole()->shift->name);
        $this->assertSame(['copied' => 3, 'skipped' => 2], ActivityLog::where('action', 'schedule.week_copied')->sole()->new_values);
    }

    public function test_copy_into_the_past_is_skipped(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 21:00', 'Asia/Manila'));
        $this->evening($this->ana, '2026-11-16');
        $this->evening($this->ana, '2026-11-20');

        $result = $this->schedules->copyWeek($this->owner, '2026-W47', '2026-W48');

        $this->assertSame(1, $result['copied']); // Fri Nov 27
        $this->assertSame([['employee' => 'Ana Cruz', 'date' => '2026-11-23', 'reason' => 'In the past']], $result['skipped']);
    }

    public function test_a_template_edit_leaves_existing_schedules(): void
    {
        $shift = $this->evening($this->ana, '2026-11-20');
        app(ShiftService::class)->update(Shift::where('name', 'Evening')->sole(), $this->owner, ['name' => 'Evening', 'start_time' => '21:00', 'end_time' => '05:00', 'is_active' => true]);

        $this->assertSame('2026-11-20 20:00 → 2026-11-21 04:00', $this->span($shift->fresh()));
        $this->assertSame('21:00', Shift::where('name', 'Evening')->sole()->start_time);
        $this->assertSame(1, ActivityLog::where('action', 'shift.updated')->count());
        $this->refused(fn () => app(ShiftService::class)->update(Shift::where('name', 'Evening')->sole(), $this->owner, ['name' => 'Morning', 'start_time' => '21:00', 'end_time' => '05:00', 'is_active' => true]), 'name');
    }

    public function test_saving_a_template_shift_keeps_its_own_times(): void
    {
        $shift = $this->evening($this->ana, '2026-11-20');
        app(ShiftService::class)->update(Shift::where('name', 'Evening')->sole(), $this->owner, ['name' => 'Evening', 'start_time' => '21:00', 'end_time' => '05:00', 'is_active' => true]);

        $saved = $this->schedules->update($shift->fresh(), $this->owner, ['duty_date' => '2026-11-20', 'shift_id' => $shift->shift_id, 'note' => 'Bring keys']);

        $this->assertSame('2026-11-20 20:00 → 2026-11-21 04:00', $this->span($saved));
        $this->assertSame($shift->shift_id, $saved->shift_id);
        $this->assertSame(['note' => 'Bring keys'], ActivityLog::where('action', 'schedule.changed')->sole()->new_values);
    }

    public function test_a_template_save_logs_only_real_changes(): void
    {
        app(ShiftService::class)->update(Shift::where('name', 'Evening')->sole(), $this->owner, ['name' => 'Evening', 'start_time' => '20:00', 'end_time' => '04:00', 'is_active' => false]);

        $this->assertSame(['is_active' => false], ActivityLog::where('action', 'shift.updated')->sole()->new_values);
    }

    public function test_a_template_over_16_hours_is_refused(): void
    {
        $this->refused(fn () => app(ShiftService::class)->update(Shift::where('name', 'Morning')->sole(), $this->owner, ['name' => 'Morning', 'start_time' => '06:00', 'end_time' => '23:00', 'is_active' => true]),
            'end_time', 'A shift can be at most 16 hours.');
    }

    public function test_a_shift_over_16_hours_is_refused(): void
    {
        $this->refused(fn () => $this->custom($this->ana, '2026-11-20', '08:00', '08:00'), 'end_time', 'A shift can be at most 16 hours.');
    }

    public function test_separation_cancels_later_schedules(): void
    {
        $kept = $this->evening($this->ana, '2026-11-20');
        $later = $this->evening($this->ana, '2026-11-22');

        app(EmployeeService::class)->update($this->ana, $this->owner, ['employment_status' => 'resigned', 'date_separated' => '2026-11-21']);

        $this->assertFalse($kept->fresh()->isCancelled());
        $this->assertTrue($later->fresh()->isCancelled());
        $this->assertSame('Employee separated', $later->fresh()->cancel_reason);
        $this->refused(fn () => $this->evening($this->ana, '2026-11-25'), 'employee_id', 'Ana Cruz is not active.');
    }

    public function test_cashier_cannot_schedule(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->schedules->create(User::factory()->create()->assignRole('Cashier'), ['employee_id' => $this->ana->id, 'duty_date' => '2026-11-20', 'start_time' => '20:00', 'end_time' => '04:00']);
    }
}

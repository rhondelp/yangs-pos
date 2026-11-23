<?php

namespace Tests\Feature\Staff;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use App\Services\Staff\BonusService;
use App\Services\Staff\EmployeeService;
use App\Services\Staff\ScheduleService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleWebTest extends TestCase
{
    use RefreshDatabase;

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
        $hire = fn (string $name) => app(EmployeeService::class)->create($this->owner, ['full_name' => $name, 'position' => 'Bartender',
            'employment_status' => 'active', 'date_hired' => '2026-11-01', 'pay_type' => 'daily', 'rate' => '600']);
        $this->ana = $hire('Ana Cruz');
        $this->ben = $hire('Ben Reyes');
    }

    private function evening(Employee $employee, string $date): Schedule
    {
        return app(ScheduleService::class)->create($this->owner, ['employee_id' => $employee->id, 'duty_date' => $date, 'shift_id' => Shift::where('name', 'Evening')->value('id')]);
    }

    public function test_week_grid_shows_shifts_and_greys_inactive(): void
    {
        $this->evening($this->ana, '2026-11-20');
        $this->evening($this->ben, '2026-11-19');
        app(EmployeeService::class)->update($this->ben, $this->owner, ['employment_status' => 'on_leave']);

        $this->get(route('schedule.week'))->assertOk()
            ->assertSee(['Nov 16 – Nov 22, 2026', 'Ana Cruz', 'Evening', '20:00–04:00', 'Ben Reyes', 'On leave'])
            ->assertSee(route('schedule.week', ['week' => '2026-W46']), false)
            ->assertSee(route('schedule.week', ['week' => '2026-W48']), false)
            ->assertSee('Assign shift, Ana Cruz, Fri Nov 20');
        $this->get(route('schedule.week', ['week' => '2026-W99']))->assertSessionHasErrors('week');
    }

    public function test_assign_edit_and_cancel_from_the_grid(): void
    {
        $evening = Shift::where('name', 'Evening')->value('id');
        $this->post(route('schedule.store'), ['_form' => 'schedule', 'employee_id' => $this->ana->id, 'duty_date' => '2026-11-20', 'shift_id' => $evening])
            ->assertRedirect(route('schedule.week', ['week' => '2026-W47']));
        $shift = Schedule::sole();

        $this->from(route('schedule.week'))
            ->post(route('schedule.store'), ['_form' => 'schedule', 'employee_id' => $this->ana->id, 'duty_date' => '2026-11-20', 'start_time' => '02:00', 'end_time' => '05:00'])
            ->assertRedirect(route('schedule.week'))->assertSessionHasErrors(['start_time' => 'Ana Cruz already works Evening, Fri Nov 20 20:00–04:00.']);
        $this->followingRedirects()->from(route('schedule.week'))
            ->post(route('schedule.store'), ['_form' => 'schedule', 'employee_id' => $this->ana->id, 'duty_date' => '2026-11-20', 'start_time' => '02:00', 'end_time' => '05:00'])
            ->assertSee('Ana Cruz already works Evening')->assertSee('showModal()', false);

        $this->put(route('schedule.update', $shift), ['_form' => 'schedule', 'duty_date' => '2026-11-20', 'start_time' => '21:00', 'end_time' => '03:00', 'note' => 'Late start'])->assertRedirect();
        $this->assertSame('21:00', $shift->fresh()->starts_at->setTimezone('Asia/Manila')->format('H:i'));
        $this->get(route('schedule.week'))->assertSee(['Custom', '21:00–03:00', 'Late start']);

        $this->post(route('schedule.cancel', $shift), ['reason' => 'Sick'])->assertRedirect();
        $this->assertTrue($shift->fresh()->isCancelled());
    }

    public function test_edit_dialog_shows_history(): void
    {
        $shift = $this->evening($this->ana, '2026-11-20');
        app(ScheduleService::class)->update($shift, $this->owner, ['duty_date' => '2026-11-20', 'start_time' => '21:00', 'end_time' => '03:00']);

        $this->get(route('schedule.week'))->assertSee(['Changed by', $this->owner->name, '20:00', '21:00']);
    }

    public function test_copy_week_flashes_the_summary(): void
    {
        $this->evening($this->ana, '2026-11-20');
        $this->evening($this->ana, '2026-11-21');
        $this->evening($this->ben, '2026-11-20');
        $this->evening($this->ben, '2026-11-22');
        app(EmployeeService::class)->update($this->ben, $this->owner, ['employment_status' => 'on_leave']);

        $this->post(route('schedule.copy'), ['from_week' => '2026-W47', 'to_week' => '2026-W48'])->assertRedirect(route('schedule.week', ['week' => '2026-W48']))
            ->assertSessionHas('status', 'Copied 2 shifts. Skipped 2: Ben Reyes, Fri Nov 27 – Not active; Ben Reyes, Sun Nov 29 – Not active.');
    }

    public function test_copy_dialog_names_the_week(): void
    {
        $this->evening($this->ana, '2026-11-20');

        $this->get(route('schedule.week'))->assertSee('Every shift of Nov 16 – Nov 22, 2026 goes to');
    }

    public function test_refusals_are_shown_on_the_page(): void
    {
        $this->followingRedirects()->from(route('schedule.week'))->get(route('schedule.week', ['week' => '2026-W99']))->assertSee('Choose a week like 2026-W47.');
        $this->followingRedirects()->from(route('schedule.day'))->get(route('schedule.day', ['date' => '2026-02-30']))->assertSee('Choose a real date.');

        $shift = $this->evening($this->ana, '2026-11-20');
        app(ScheduleService::class)->cancel($shift, $this->owner, 'Sick');
        $this->followingRedirects()->from(route('schedule.week'))->post(route('schedule.cancel', $shift), ['reason' => 'Again'])->assertSee('This shift was already cancelled.');
    }

    public function test_day_view_lists_shifts_and_bonuses_only_for_managers(): void
    {
        $this->evening($this->ana, '2026-11-18');
        app(BonusService::class)->give($this->owner, ['employee_id' => $this->ana->id, 'amount' => '300', 'reason' => 'Busy night']);

        $this->get(route('schedule.day', ['date' => '2026-11-18']))->assertOk()->assertSee(['Wed, Nov 18, 2026', 'Ana Cruz', '20:00–04:00', '8 h', 'Night bonuses', '₱300.00', 'Give bonus']);

        Role::findByName('Supervisor')->givePermissionTo('schedules.view');
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'));
        $this->get(route('schedule.day', ['date' => '2026-11-18']))->assertOk()->assertSee('Ana Cruz')->assertDontSee(['Night bonuses', '₱300.00']);
        $this->get(route('schedule.day', ['date' => '2026-02-30']))->assertSessionHasErrors('date');
    }

    public function test_templates_page_edits_times(): void
    {
        $this->get(route('shifts.index'))->assertOk()->assertSee(['Morning', 'Afternoon', 'Evening']);
        $evening = Shift::where('name', 'Evening')->sole();
        $this->put(route('shifts.update', $evening), ['name' => 'Evening', 'start_time' => '21:00', 'end_time' => '05:00', 'is_active' => '1'])->assertRedirect(route('shifts.index'));
        $this->assertSame('21:00', $evening->fresh()->start_time);
    }

    public function test_schedule_view_only_user_sees_no_assign_controls(): void
    {
        $this->evening($this->ana, '2026-11-20');
        Role::findByName('Supervisor')->givePermissionTo('schedules.view');
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'));

        $this->get(route('schedule.week'))->assertOk()->assertSee('Evening')->assertDontSee(['Assign shift', 'Copy this week']);
        $this->post(route('schedule.store'), [])->assertForbidden();
        $this->get(route('shifts.index'))->assertForbidden();
    }

    public function test_cashier_cannot_open_schedule(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Cashier'));

        $this->get(route('schedule.week'))->assertForbidden();
        $this->get(route('schedule.day'))->assertForbidden();
    }
}

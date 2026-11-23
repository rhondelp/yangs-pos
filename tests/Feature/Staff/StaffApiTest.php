<?php

namespace Tests\Feature\Staff;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeRate;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, ShiftSeeder::class]);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 21:00', 'Asia/Manila'));
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner, 'sanctum');
    }

    private function hire(string $name = 'Ana Cruz'): int
    {
        return $this->postJson(route('api.v1.employees.store'), ['full_name' => $name, 'position' => 'Bartender', 'employment_status' => 'active',
            'date_hired' => '2026-11-01', 'pay_type' => 'daily', 'rate' => 600])->assertCreated()->json('data.id');
    }

    public function test_employee_crud_and_pay_privacy(): void
    {
        $id = $this->hire();
        $this->getJson(route('api.v1.employees.show', $id))->assertOk()->assertJsonPath('data.rate', '600.00')->assertJsonPath('data.pay_type', 'daily');
        $this->putJson(route('api.v1.employees.update', $id), ['position' => 'Head bartender'])->assertOk()->assertJsonPath('data.position', 'Head bartender');
        $this->putJson(route('api.v1.employees.update', $id), ['rate' => '900'])->assertUnprocessable()->assertJsonValidationErrors('rate');
        $this->postJson(route('api.v1.employees.archive', $id))->assertOk();
        $this->getJson(route('api.v1.employees.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson(route('api.v1.employees.restore', $id))->assertOk();

        Role::findByName('Supervisor', 'web')->givePermissionTo('employees.view');
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'), 'sanctum');
        $this->getJson(route('api.v1.employees.show', $id))->assertOk()->assertJsonMissingPath('data.rate')->assertJsonPath('data.full_name', 'Ana Cruz');
        $this->getJson(route('api.v1.employees.rates.index', $id))->assertForbidden();
        $this->putJson(route('api.v1.employees.update', $id), ['position' => 'x'])->assertForbidden();
    }

    public function test_schedule_api_week_and_overlap(): void
    {
        $ana = $this->hire();
        $evening = Shift::where('name', 'Evening')->value('id');

        $this->postJson(route('api.v1.schedules.store'), ['employee_id' => $ana, 'duty_date' => '2026-11-20', 'shift_id' => $evening])->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-11-20T20:00:00+08:00')->assertJsonPath('data.ends_at', '2026-11-21T04:00:00+08:00')->assertJsonPath('data.shift', 'Evening');
        $this->postJson(route('api.v1.schedules.store'), ['employee_id' => $ana, 'duty_date' => '2026-11-20', 'start_time' => '02:00', 'end_time' => '05:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['start_time' => 'Ana Cruz already works Evening, Fri Nov 20 20:00–04:00.']);
        $this->getJson(route('api.v1.schedules.index', ['week' => '2026-W47']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.employee', 'Ana Cruz');
        $this->getJson(route('api.v1.schedules.index', ['date' => '2026-11-21']))->assertOk()->assertJsonCount(0, 'data');

        $id = Schedule::sole()->id;
        $this->putJson(route('api.v1.schedules.update', $id), ['duty_date' => '2026-11-20', 'start_time' => '21:00', 'end_time' => '03:00'])->assertOk()->assertJsonPath('data.shift', null);
        $this->postJson(route('api.v1.schedules.cancel', $id), ['reason' => 'Closed'])->assertOk()->assertJsonPath('data.cancelled', true);
        $this->getJson(route('api.v1.shifts.index'))->assertOk()->assertJsonCount(3, 'data');
        $this->putJson(route('api.v1.shifts.update', $evening), ['name' => 'Evening', 'start_time' => '21:00', 'end_time' => '05:00', 'is_active' => true])
            ->assertOk()->assertJsonPath('data.start_time', '21:00');
    }

    public function test_rate_and_bonus_edits_over_the_api(): void
    {
        $ana = $this->hire();
        $rate = EmployeeRate::sole()->id;
        $this->putJson(route('api.v1.employee-rates.update', $rate), ['pay_type' => 'daily', 'rate' => '650', 'effective_from' => '2026-11-01', 'reason' => 'Typo'])
            ->assertOk()->assertJsonPath('data.rate', '650.00')->assertJsonPath('data.replaces_id', $rate);
        $this->postJson(route('api.v1.employees.rates.store', $ana), ['pay_type' => 'daily', 'rate' => '700', 'effective_from' => '2026-12-01'])->assertCreated();
        $this->getJson(route('api.v1.employees.rates.index', $ana))->assertOk()->assertJsonCount(3, 'data');

        $bonus = $this->postJson(route('api.v1.employees.bonuses.store', $ana), ['amount' => '300', 'reason' => 'Busy night'])->assertCreated()->json('data.id');
        $this->putJson(route('api.v1.employee-bonuses.update', $bonus), ['business_date' => '2026-11-18', 'amount' => '350', 'reason' => 'Busy night', 'edit_reason' => 'More'])
            ->assertOk()->assertJsonPath('data.amount', '350.00')->assertJsonPath('data.replaces_id', $bonus);
        $this->postJson(route('api.v1.employee-bonuses.void', EmployeeBonus::whereNull('voided_at')->sole()->id), ['reason' => 'Wrong'])->assertOk()->assertJsonPath('data.voided', true);
        $this->getJson(route('api.v1.employees.bonuses.index', $ana))->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_copy_week_returns_the_summary(): void
    {
        $ana = $this->hire();
        $this->postJson(route('api.v1.schedules.store'), ['employee_id' => $ana, 'duty_date' => '2026-11-20', 'start_time' => '20:00', 'end_time' => '04:00'])->assertCreated();

        $this->postJson(route('api.v1.schedules.copy-week'), ['from_week' => '2026-W47', 'to_week' => '2026-W48'])->assertOk()
            ->assertJsonPath('data.copied', 1)->assertJsonPath('data.skipped', []);
        $this->postJson(route('api.v1.schedules.copy-week'), ['from_week' => '2026-W47', 'to_week' => '2026-W47'])->assertUnprocessable()->assertJsonValidationErrors('to_week');
    }

    public function test_bad_week_and_date_are_refused(): void
    {
        $this->getJson(route('api.v1.schedules.index', ['week' => '2026-W99']))->assertUnprocessable()->assertJsonValidationErrors('week');
        $this->getJson(route('api.v1.schedules.index', ['week' => '2025-W53']))->assertUnprocessable()->assertJsonValidationErrors('week'); // 2025 has 52
        $this->getJson(route('api.v1.schedules.index', ['date' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson(route('api.v1.schedules.copy-week'), ['from_week' => 'next', 'to_week' => '2026-W48'])->assertUnprocessable()->assertJsonValidationErrors('from_week');
    }

    public function test_cashier_gets_403_everywhere(): void
    {
        $ana = $this->hire();
        $this->actingAs(User::factory()->create()->assignRole('Cashier'), 'sanctum');

        $this->getJson(route('api.v1.employees.index'))->assertForbidden();
        $this->postJson(route('api.v1.employees.store'), [])->assertForbidden();
        $this->getJson(route('api.v1.employees.bonuses.index', $ana))->assertForbidden();
        $this->getJson(route('api.v1.schedules.index'))->assertForbidden();
        $this->postJson(route('api.v1.schedules.store'), [])->assertForbidden();
        $this->getJson(route('api.v1.shifts.index'))->assertForbidden();
        $this->assertSame(1, Employee::count());
    }
}

<?php

namespace Tests\Feature\Staff;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeRate;
use App\Models\User;
use App\Services\Staff\BonusService;
use App\Services\Staff\EmployeeService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeWebTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Employee $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 21:00', 'Asia/Manila'));
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->ana = app(EmployeeService::class)->create($this->owner, ['full_name' => 'Ana Cruz', 'position' => 'Bartender', 'employment_status' => 'active',
            'date_hired' => '2026-11-01', 'pay_type' => 'daily', 'rate' => '600', 'phone' => '0917 123 4567']);
    }

    public function test_employee_pages_and_pay_privacy(): void
    {
        $this->get(route('employees.index'))->assertOk()->assertSee(['Ana Cruz', 'Bartender', 'Active', '₱600.00 a day']);
        $this->get(route('employees.show', $this->ana))->assertOk()->assertSee(['0917 123 4567', '₱600.00 a day', 'Pay history', 'Night bonuses']);
        $this->get(route('employees.create'))->assertOk()->assertSee(['Full name', 'Pay type', 'Rate (₱)']);
        $this->post(route('employees.store'), ['full_name' => 'Ben Reyes', 'position' => 'Server', 'employment_status' => 'active', 'date_hired' => '2026-11-10',
            'pay_type' => 'weekly', 'rate' => '3500'])->assertRedirect();
        $this->assertSame('3500.00', EmployeeRate::where('employee_id', Employee::where('full_name', 'Ben Reyes')->value('id'))->value('rate'));
        $this->put(route('employees.update', $this->ana), ['full_name' => 'Ana Cruz', 'position' => 'Head bartender', 'employment_status' => 'active', 'date_hired' => '2026-11-01'])
            ->assertRedirect(route('employees.show', $this->ana));
        $this->assertSame('Head bartender', $this->ana->fresh()->position);

        Role::findByName('Supervisor')->givePermissionTo('employees.view');
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'));
        $this->get(route('employees.index'))->assertOk()->assertSee('Ana Cruz')->assertDontSee('₱600.00');
        $this->get(route('employees.show', $this->ana))->assertOk()->assertSee('0917 123 4567')->assertDontSee(['₱600.00', 'Pay history', 'Night bonuses']);
        $this->get(route('employees.create'))->assertForbidden();
    }

    public function test_rate_edit_dialog_keeps_history(): void
    {
        $this->post(route('employees.rates.store', $this->ana), ['pay_type' => 'daily', 'rate' => '650', 'effective_from' => '2026-11-16'])->assertRedirect();
        $raise = EmployeeRate::where('rate', '650')->sole();

        $this->put(route('employee-rates.update', $raise), ['_form' => "rate-{$raise->id}", 'pay_type' => 'daily', 'rate' => '660', 'effective_from' => '2026-11-16', 'reason' => 'Typo'])
            ->assertRedirect(route('employees.show', $this->ana));
        $this->get(route('employees.show', $this->ana))->assertSee(['₱660.00 a day', 'Replaced', '₱650.00']);

        $this->from(route('employees.show', $this->ana))
            ->put(route('employee-rates.update', $raise), ['_form' => "rate-{$raise->id}", 'pay_type' => 'daily', 'rate' => '670', 'effective_from' => '2026-11-16', 'reason' => 'Again'])
            ->assertRedirect(route('employees.show', $this->ana))->assertSessionHasErrors('rate');
    }

    public function test_bonus_form_warns_and_edits(): void
    {
        $this->get(route('employees.show', $this->ana))->assertSee('Paid in cash tonight. Do not also record it as an expense.');
        $this->post(route('employees.bonuses.store', $this->ana), ['amount' => '300', 'reason' => 'Busy night'])->assertRedirect(route('employees.show', $this->ana));
        $bonus = EmployeeBonus::sole();

        $this->put(route('employee-bonuses.update', $bonus), ['business_date' => '2026-11-20', 'amount' => '350', 'reason' => 'Busy night', 'edit_reason' => 'Raised'])->assertRedirect();
        $this->assertSame('350.00', EmployeeBonus::whereNull('voided_at')->sole()->amount);
        $this->post(route('employee-bonuses.void', EmployeeBonus::whereNull('voided_at')->sole()), ['reason' => 'Wrong night'])->assertRedirect();
        $this->assertSame(0, app(BonusService::class)->forNight('2026-11-20')->count());
        $this->get(route('employees.show', $this->ana))->assertSee(['Replaced', 'Voided', 'Wrong night']);
    }

    public function test_archive_and_restore(): void
    {
        $this->delete(route('employees.archive', $this->ana))->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted($this->ana);
        $this->post(route('employees.restore', $this->ana))->assertRedirect();
        $this->assertNotSoftDeleted($this->ana);
    }

    public function test_pay_entries_in_the_activity_log_need_employees_manage(): void
    {
        $pay = ActivityLog::where('action', 'employee.rate_changed')->sole();
        Role::findByName('Supervisor')->givePermissionTo('logs.view');
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)->get(route('activity-logs.index'))->assertOk()->assertDontSee('employee.rate_changed')->assertSee('employee.created');
        $this->get(route('activity-logs.show', $pay))->assertNotFound();
        $this->actingAs($supervisor, 'sanctum')->getJson(route('api.v1.activity-logs.index'))->assertOk()->assertJsonMissing(['action' => 'employee.rate_changed']);
        $this->actingAs($this->owner)->get(route('activity-logs.show', $pay))->assertOk();
    }

    public function test_editing_keeps_a_deactivated_login_and_links_the_week(): void
    {
        $login = User::factory()->create(['name' => 'Ana Login']);
        app(EmployeeService::class)->update($this->ana, $this->owner, ['user_id' => $login->id]);
        $login->forceFill(['is_active' => false])->save();

        $this->get(route('employees.edit', $this->ana))->assertOk()->assertSee('Ana Login');
        $this->get(route('employees.show', $this->ana))->assertSee(route('schedule.week'), false)->assertSee('See their week');
    }

    public function test_cashier_cannot_open_employees(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Cashier'));

        $this->get(route('employees.index'))->assertForbidden();
        $this->get(route('employees.show', $this->ana))->assertForbidden();
    }
}

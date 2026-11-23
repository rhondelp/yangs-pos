<?php

namespace Tests\Feature\Staff;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\User;
use App\Services\Staff\BonusService;
use App\Services\Staff\EmployeeService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BonusServiceTest extends TestCase
{
    use RefreshDatabase;

    private BonusService $bonuses;

    private User $owner;

    private Employee $ana;

    private Employee $ben;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 23:00', 'Asia/Manila'));
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->bonuses = app(BonusService::class);
        $hire = fn (string $name) => app(EmployeeService::class)->create($this->owner, ['full_name' => $name, 'position' => 'Bartender',
            'employment_status' => 'active', 'date_hired' => '2026-11-01', 'pay_type' => 'daily', 'rate' => '600']);
        $this->ana = $hire('Ana Cruz');
        $this->ben = $hire('Ben Reyes');
    }

    private function give(Employee $employee, string $amount, array $extra = []): EmployeeBonus
    {
        return $this->bonuses->give($this->owner, ['employee_id' => $employee->id, 'amount' => $amount, 'reason' => 'Busy night', ...$extra]);
    }

    private function total(string $date): string
    {
        return $this->bonuses->forNight($date)->reduce(fn (string $sum, EmployeeBonus $b) => Money::add($sum, $b->amount), '0.00');
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

    public function test_give_and_list_a_night(): void
    {
        $this->give($this->ana, '300');
        $this->give($this->ben, '200');

        $this->assertSame('500.00', $this->total('2026-11-20'));
        $this->assertSame('2026-11-20', EmployeeBonus::first()->business_date->toDateString()); // today's business date by default
        $this->assertSame(2, ActivityLog::where('action', 'employee.bonus_given')->count());
    }

    public function test_edit_replaces_and_keeps_history(): void
    {
        $old = $this->give($this->ana, '300');
        $this->give($this->ben, '200');

        $new = $this->bonuses->edit($old, $this->owner, ['employee_id' => $this->ana->id, 'business_date' => '2026-11-20', 'amount' => '350',
            'reason' => 'Busy night', 'edit_reason' => 'Owner raised it']);

        $this->assertSame('550.00', $this->total('2026-11-20'));
        $this->assertSame($old->id, $new->replaces_id);
        $this->assertSame('Owner raised it', $old->fresh()->void_reason);
        $log = ActivityLog::where('action', 'employee.bonus_changed')->sole();
        $this->assertSame(['300.00', '350.00'], [$log->old_values['amount'], $log->new_values['amount']]);
        $this->refused(fn () => $this->bonuses->edit($old->fresh(), $this->owner, ['employee_id' => $this->ana->id, 'business_date' => '2026-11-20',
            'amount' => '400', 'reason' => 'x', 'edit_reason' => 'Again']), 'amount', 'This bonus was already changed or voided.');
        $this->refused(fn () => $this->bonuses->edit($new, $this->owner, ['employee_id' => $this->ana->id, 'business_date' => '2026-11-20',
            'amount' => '400', 'reason' => 'x', 'edit_reason' => '']), 'edit_reason');
    }

    public function test_an_edit_without_a_date_keeps_the_night(): void
    {
        $bonus = $this->give($this->ana, '300');
        $this->travelTo(CarbonImmutable::parse('2026-11-23 21:00', 'Asia/Manila'));

        $new = $this->bonuses->edit($bonus, $this->owner, ['amount' => '350', 'reason' => 'Busy night', 'edit_reason' => 'Typo', 'employee_id' => $this->ana->id]);

        $this->assertSame('2026-11-20', $new->business_date->toDateString());
    }

    public function test_a_bonus_can_be_corrected_after_the_employee_goes_on_leave(): void
    {
        $bonus = $this->give($this->ana, '300');
        app(EmployeeService::class)->update($this->ana, $this->owner, ['employment_status' => 'on_leave']);

        $new = $this->bonuses->edit($bonus, $this->owner, ['employee_id' => $this->ana->id, 'business_date' => '2026-11-20', 'amount' => '350', 'reason' => 'Busy night', 'edit_reason' => 'Typo']);
        $this->assertSame('350.00', $new->amount);
    }

    public function test_void_once(): void
    {
        $bonus = $this->give($this->ana, '300');
        $this->bonuses->void($bonus, $this->owner, 'Wrong person');

        $this->assertSame('0.00', $this->total('2026-11-20'));
        $this->refused(fn () => $this->bonuses->void($bonus->fresh(), $this->owner, 'Again'), 'reason', 'This bonus was already changed or voided.');
        $this->refused(fn () => $this->bonuses->void($this->give($this->ben, '100'), $this->owner, ' '), 'reason');
        $this->assertSame(1, ActivityLog::where('action', 'employee.bonus_voided')->count());
    }

    public function test_future_date_refused(): void
    {
        $this->refused(fn () => $this->give($this->ana, '300', ['business_date' => '2026-11-21']), 'business_date', 'The date cannot be after today.');
        $this->refused(fn () => $this->give($this->ana, '0'), 'amount');
    }

    public function test_only_active_employees(): void
    {
        app(EmployeeService::class)->update($this->ana, $this->owner, ['employment_status' => 'on_leave']);

        $this->refused(fn () => $this->give($this->ana, '300'), 'employee_id', 'Ana Cruz is not active.');
    }

    public function test_needs_employees_manage(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->bonuses->give(User::factory()->create()->assignRole('Supervisor'), ['employee_id' => $this->ana->id, 'amount' => '300', 'reason' => 'x']);
    }
}

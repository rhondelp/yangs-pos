<?php

namespace Tests\Feature\Staff;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeRate;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\ShiftSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class StaffSchemaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function employee(string $name = 'Ana Cruz'): Employee
    {
        return Employee::forceCreate(['full_name' => $name, 'position' => 'Bartender', 'employment_status' => 'active', 'date_hired' => '2026-11-01']);
    }

    private function schedule(Employee $employee, string $start, string $end, array $extra = []): int
    {
        return DB::table('schedules')->insertGetId([
            'employee_id' => $employee->id, 'starts_at' => $start, 'ends_at' => $end, 'duty_date' => substr($start, 0, 10),
            'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
    }

    private function sqlState(callable $write): ?string
    {
        try {
            DB::transaction(fn () => $write()); // a savepoint, so a refused write leaves the test transaction usable
        } catch (QueryException $e) {
            return $e->getCode();
        }

        return null;
    }

    public function test_the_database_refuses_overlapping_live_shifts(): void
    {
        $ana = $this->employee();
        $evening = $this->schedule($ana, '2026-11-20 20:00:00+08', '2026-11-21 04:00:00+08');

        $this->assertSame('23P01', $this->sqlState(fn () => $this->schedule($ana, '2026-11-21 02:00:00+08', '2026-11-21 06:00:00+08')));
        $this->assertNull($this->sqlState(fn () => $this->schedule($this->employee('Ben Reyes'), '2026-11-21 02:00:00+08', '2026-11-21 06:00:00+08')));
        $this->assertNull($this->sqlState(fn () => $this->schedule($ana, '2026-11-21 04:00:00+08', '2026-11-21 06:00:00+08'))); // back to back

        DB::table('schedules')->where('id', $evening)->update(['cancelled_at' => now(), 'cancelled_by' => $this->user->id, 'cancel_reason' => 'Sick']);
        $this->assertNull($this->sqlState(fn () => $this->schedule($ana, '2026-11-20 22:00:00+08', '2026-11-21 02:00:00+08')));
    }

    public function test_shift_length_is_checked(): void
    {
        $ana = $this->employee();

        $this->assertSame('23514', $this->sqlState(fn () => $this->schedule($ana, '2026-11-20 20:00:00+08', '2026-11-20 20:00:00+08')));
        $this->assertSame('23514', $this->sqlState(fn () => $this->schedule($ana, '2026-11-20 08:00:00+08', '2026-11-21 00:01:00+08')));
        $this->assertNull($this->sqlState(fn () => $this->schedule($ana, '2026-11-20 08:00:00+08', '2026-11-21 00:00:00+08')));
    }

    public function test_rates_are_append_only(): void
    {
        $rate = EmployeeRate::forceCreate(['employee_id' => $this->employee()->id, 'pay_type' => 'daily', 'rate' => '600', 'effective_from' => '2026-11-01', 'created_by' => $this->user->id]);

        $this->assertNotNull($this->sqlState(fn () => DB::table('employee_rates')->where('id', $rate->id)->update(['rate' => '1'])));
        $this->assertNotNull($this->sqlState(fn () => DB::table('employee_rates')->where('id', $rate->id)->delete()));
        $this->assertThrows(fn () => $rate->forceFill(['rate' => '1'])->save(), LogicException::class);
        $this->assertThrows(fn () => $rate->delete(), LogicException::class);
        $this->assertNotNull($this->sqlState(fn () => EmployeeRate::forceCreate(['employee_id' => $rate->employee_id, 'pay_type' => 'daily', 'rate' => '0', 'effective_from' => '2026-11-02', 'created_by' => $this->user->id])));
    }

    public function test_a_bonus_is_voided_once_and_never_edited(): void
    {
        $bonus = EmployeeBonus::forceCreate(['employee_id' => $this->employee()->id, 'business_date' => '2026-11-20', 'amount' => '300', 'reason' => 'Busy night', 'given_by' => $this->user->id]);

        $this->assertThrows(fn () => $bonus->forceFill(['amount' => '1.00'])->save(), LogicException::class);
        $this->assertThrows(fn () => $bonus->fresh()->delete(), LogicException::class);
        $this->assertNotNull($this->sqlState(fn () => DB::table('employee_bonuses')->where('id', $bonus->id)->update(['amount' => '1.00'])));
        $this->assertNotNull($this->sqlState(fn () => DB::table('employee_bonuses')->where('id', $bonus->id)->delete()));

        $bonus->fresh()->forceFill(['voided_at' => now(), 'voided_by' => $this->user->id, 'void_reason' => 'Wrong person'])->save();
        $this->assertTrue($bonus->fresh()->isVoided());
        $this->assertThrows(fn () => $bonus->fresh()->forceFill(['void_reason' => 'Other'])->save(), LogicException::class);
        $this->assertNotNull($this->sqlState(fn () => DB::table('employee_bonuses')->where('id', $bonus->id)->update(['void_reason' => 'Other'])));
    }

    public function test_the_shift_seeder_adds_three_templates_once(): void
    {
        $this->seed(ShiftSeeder::class);
        $this->seed(ShiftSeeder::class);
        $this->assertSame(['Morning', 'Afternoon', 'Evening'], Shift::orderBy('id')->pluck('name')->all());

        Shift::where('name', 'Evening')->sole()->update(['start_time' => '21:00']);
        $this->seed(ShiftSeeder::class);
        $evening = Shift::where('name', 'Evening')->sole();
        $this->assertSame(['21:00', '04:00'], [$evening->start_time, $evening->end_time]);
    }
}

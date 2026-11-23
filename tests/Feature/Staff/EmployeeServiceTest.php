<?php

namespace Tests\Feature\Staff;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\User;
use App\Services\Staff\EmployeeService;
use App\Services\Staff\ScheduleService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ShiftSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeService $employees;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->employees = app(EmployeeService::class);
    }

    private function hire(array $input = []): Employee
    {
        return $this->employees->create($this->owner, ['full_name' => 'Ana Cruz', 'position' => 'Bartender', 'employment_status' => 'active',
            'date_hired' => '2026-11-01', 'pay_type' => 'daily', 'rate' => '600', ...$input]);
    }

    private function refused(callable $call, string $key, ?string $message = null): void
    {
        try {
            $call();
            $this->fail("Expected a validation error on {$key}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
            if ($message !== null) {
                $this->assertSame($message, $e->errors()[$key][0]);
            }
        }
    }

    public function test_create_writes_the_first_rate_and_logs_without_pay(): void
    {
        $ana = $this->hire(['phone' => ' 0917 123 4567 ']);

        $this->assertSame('0917 123 4567', $ana->phone);
        $rate = $this->employees->rateOn($ana, '2026-11-01');
        $this->assertSame(['daily', '600.00', '2026-11-01'], [$rate->pay_type->value, $rate->rate, $rate->effective_from->toDateString()]);
        $created = ActivityLog::where('action', 'employee.created')->sole();
        $this->assertArrayNotHasKey('rate', $created->new_values);
        $this->assertSame('600.00', ActivityLog::where('action', 'employee.rate_changed')->sole()->new_values['rate']);
    }

    public function test_rate_history_by_date(): void
    {
        $ana = $this->hire();
        $this->employees->addRate($ana, $this->owner, ['pay_type' => 'daily', 'rate' => '650', 'effective_from' => '2026-11-16']);
        $this->employees->addRate($ana, $this->owner, ['pay_type' => 'daily', 'rate' => '700', 'effective_from' => '2026-12-01']);

        foreach (['2026-11-15' => '600.00', '2026-11-16' => '650.00', '2026-11-30' => '650.00', '2026-12-01' => '700.00'] as $date => $rate) {
            $this->assertSame($rate, $this->employees->rateOn($ana, $date)->rate, $date);
        }
        $log = ActivityLog::where('action', 'employee.rate_changed')->latest('id')->first();
        $this->assertSame(['650.00', '700.00'], [$log->old_values['rate'], $log->new_values['rate']]);
    }

    public function test_edit_rate_keeps_history(): void
    {
        $ana = $this->hire();
        $raise = $this->employees->addRate($ana, $this->owner, ['pay_type' => 'daily', 'rate' => '650', 'effective_from' => '2026-11-16']);

        $edited = $this->employees->editRate($raise, $this->owner, ['pay_type' => 'daily', 'rate' => '660', 'effective_from' => '2026-11-18', 'reason' => 'Typo']);

        $this->assertSame($raise->id, $edited->replaces_id);
        $this->assertSame('600.00', $this->employees->rateOn($ana, '2026-11-17')->rate);
        $this->assertSame('660.00', $this->employees->rateOn($ana, '2026-11-18')->rate);
        $this->assertNotNull($raise->fresh()); // kept as history
        $this->refused(fn () => $this->employees->editRate($raise->fresh(), $this->owner, ['pay_type' => 'daily', 'rate' => '670', 'effective_from' => '2026-11-18', 'reason' => 'Again']),
            'rate', 'This rate was already changed. Edit the latest version.');
        $this->refused(fn () => $this->employees->editRate($edited, $this->owner, ['pay_type' => 'daily', 'rate' => '670', 'effective_from' => '2026-11-18', 'reason' => ' ']), 'reason');
        $log = ActivityLog::where('action', 'employee.rate_edited')->sole();
        $this->assertSame(['650.00', '660.00', 'Typo'], [$log->old_values['rate'], $log->new_values['rate'], $log->new_values['reason']]);
    }

    public function test_rate_on_a_day_before_any_rate_is_null(): void
    {
        $this->assertNull($this->employees->rateOn($this->hire(), '2026-10-31'));
    }

    public function test_custom_pay_needs_a_basis(): void
    {
        $this->refused(fn () => $this->hire(['pay_type' => 'custom', 'rate' => '1500']), 'custom_basis');
        $this->assertSame('per event', $this->employees->rateOn($this->hire(['pay_type' => 'custom', 'rate' => '1500', 'custom_basis' => 'per event']), '2026-11-01')->custom_basis);
    }

    public function test_rate_must_be_positive_money(): void
    {
        foreach (['0', '-1', '12.345', 'abc'] as $rate) {
            $this->refused(fn () => $this->hire(['rate' => $rate]), 'rate');
        }
    }

    public function test_separation_needs_a_date_not_before_hire(): void
    {
        $ana = $this->hire();

        $this->refused(fn () => $this->employees->update($ana, $this->owner, ['employment_status' => 'resigned']), 'date_separated');
        $this->refused(fn () => $this->employees->update($ana, $this->owner, ['employment_status' => 'resigned', 'date_separated' => '2026-10-01']), 'date_separated');
        $this->employees->update($ana, $this->owner, ['employment_status' => 'resigned', 'date_separated' => '2026-11-21']);
        $this->assertSame('resigned', $ana->fresh()->employment_status->value);
        $this->assertEquals(['employment_status' => 'resigned', 'date_separated' => '2026-11-21'], ActivityLog::where('action', 'employee.updated')->sole()->new_values);
    }

    public function test_a_login_links_to_one_employee(): void
    {
        $login = User::factory()->create();
        $this->hire(['user_id' => $login->id]);

        $this->refused(fn () => $this->hire(['full_name' => 'Ben Reyes', 'user_id' => $login->id]), 'user_id', 'This login already belongs to Ana Cruz.');
    }

    public function test_archive_and_restore_are_logged(): void
    {
        $ana = $this->hire();
        $this->employees->archive($ana, $this->owner);
        $this->assertSoftDeleted($ana);
        $this->employees->restore($ana->fresh(), $this->owner);
        $this->assertSame(['employee.archived', 'employee.restored'], ActivityLog::whereIn('action', ['employee.archived', 'employee.restored'])->orderBy('id')->pluck('action')->all());
    }

    public function test_one_live_rate_per_start_date(): void
    {
        $ana = $this->hire();
        $raise = $this->employees->addRate($ana, $this->owner, ['pay_type' => 'daily', 'rate' => '650', 'effective_from' => '2026-12-01']);

        $this->refused(fn () => $this->employees->addRate($ana, $this->owner, ['pay_type' => 'daily', 'rate' => '700', 'effective_from' => '2026-12-01']),
            'effective_from', 'A rate already starts on Dec 1, 2026. Edit it instead.');
        $first = $this->employees->rateOn($ana, '2026-11-01');
        $this->refused(fn () => $this->employees->editRate($first, $this->owner, ['pay_type' => 'daily', 'rate' => '600', 'effective_from' => '2026-12-01', 'reason' => 'Move']), 'effective_from');
        $this->employees->editRate($raise, $this->owner, ['pay_type' => 'daily', 'rate' => '660', 'effective_from' => '2026-12-01', 'reason' => 'Typo']); // its own date is fine
        $this->assertSame('660.00', $this->employees->rateOn($ana, '2026-12-01')->rate);
    }

    public function test_archiving_cancels_future_shifts(): void
    {
        $this->seed(ShiftSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 21:00', 'Asia/Manila'));
        $ana = $this->hire();
        $schedule = fn (string $date) => app(ScheduleService::class)->create($this->owner, ['employee_id' => $ana->id, 'duty_date' => $date, 'start_time' => '20:00', 'end_time' => '04:00']);
        $past = $schedule('2026-11-19');
        $future = $schedule('2026-11-22');

        $this->employees->archive($ana, $this->owner);

        $this->assertFalse($past->fresh()->isCancelled());
        $this->assertSame('Employee archived', $future->fresh()->cancel_reason);
    }

    public function test_writes_need_employees_manage(): void
    {
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $ana = $this->hire();

        $this->expectException(AuthorizationException::class);
        $this->employees->addRate($ana, $supervisor, ['pay_type' => 'daily', 'rate' => '650', 'effective_from' => '2026-11-16']);
    }
}

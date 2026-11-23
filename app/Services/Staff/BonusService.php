<?php

namespace App\Services\Staff;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\BusinessDate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Night bonuses: extra cash the owner hands over that night. Payroll counts them in gross pay as already paid, so
 * they are payroll cost once and never an expense too. An edit voids the old row and records a new one.
 */
class BonusService
{
    public function __construct(private ActivityLogger $logger) {}

    /** @param  array{employee_id: mixed, business_date?: mixed, amount: mixed, reason: mixed}  $input */
    public function give(User $by, array $input): EmployeeBonus
    {
        $this->authorize($by);
        $data = $this->validated($input);

        return DB::transaction(function () use ($by, $data) {
            $bonus = $this->write($data, $by);
            $this->logger->log('employee.bonus_given', $bonus, [], $this->values($bonus), $by);

            return $bonus;
        });
    }

    /** @param  array{employee_id: mixed, business_date: mixed, amount: mixed, reason: mixed, edit_reason: mixed}  $input */
    public function edit(EmployeeBonus $bonus, User $by, array $input): EmployeeBonus
    {
        $this->authorize($by);
        $input += ['employee_id' => $bonus->employee_id, 'business_date' => $bonus->business_date->toDateString()]; // unsent: keep them
        // Correcting cash already handed over works even after the employee left; moving it to someone else needs them active.
        $data = $this->validated($input, requireActive: (int) $input['employee_id'] !== $bonus->employee_id);
        $why = $this->reason($input['edit_reason'] ?? null, 'edit_reason', 'Say why this bonus is changed (up to 200 characters).');

        return DB::transaction(function () use ($bonus, $by, $data, $why) {
            $old = $this->lockLive($bonus, 'amount');
            $old->forceFill(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $why])->save();
            $new = $this->write([...$data, 'replaces_id' => $old->id], $by);
            $this->logger->log('employee.bonus_changed', $new, $this->values($old), [...$this->values($new), 'edit_reason' => $why], $by);

            return $new;
        });
    }

    public function void(EmployeeBonus $bonus, User $by, ?string $reason): EmployeeBonus
    {
        $this->authorize($by);
        $reason = $this->reason($reason, 'reason', 'Say why this bonus is voided (up to 200 characters).');

        return DB::transaction(function () use ($bonus, $by, $reason) {
            $locked = $this->lockLive($bonus, 'reason');
            $locked->forceFill(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason])->save();
            $this->logger->log('employee.bonus_voided', $locked, [], ['reason' => $reason, 'amount' => $locked->amount], $by);

            return $locked;
        });
    }

    /** @return Collection<int, EmployeeBonus> live bonuses of one business night */
    public function forNight(string $date): Collection
    {
        return EmployeeBonus::whereDate('business_date', $date)->whereNull('voided_at')->with('employee', 'givenBy')->orderBy('id')->get();
    }

    private function validated(array $input, bool $requireActive = true): array
    {
        $input['reason'] = trim((string) ($input['reason'] ?? ''));
        $input['business_date'] = ($input['business_date'] ?? null) ?: BusinessDate::for(now());
        $input['amount'] = is_int($input['amount'] ?? null) || is_float($input['amount'] ?? null) ? (string) $input['amount'] : ($input['amount'] ?? null);
        $data = Validator::make($input, [
            'employee_id' => ['required', 'integer'],
            'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BusinessDate::for(now())],
            'amount' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'reason' => ['required', 'string', 'max:200'],
        ], [
            'business_date.before_or_equal' => 'The date cannot be after today.',
            'amount.regex' => 'Enter the amount in pesos, up to 2 decimals.',
            'amount.not_regex' => 'The amount must be more than ₱0.00.',
            'reason.required' => 'Say what the bonus is for.',
        ])->validate();

        $employee = Employee::find($data['employee_id']);
        if (! $employee) {
            throw ValidationException::withMessages(['employee_id' => 'Choose an employee.']);
        }
        if ($requireActive && $employee->employment_status !== EmploymentStatus::Active) {
            throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} is not active."]);
        }

        return $data;
    }

    private function write(array $data, User $by): EmployeeBonus
    {
        return EmployeeBonus::forceCreate([
            'employee_id' => (int) $data['employee_id'], 'business_date' => $data['business_date'], 'amount' => $data['amount'],
            'reason' => $data['reason'], 'replaces_id' => $data['replaces_id'] ?? null, 'given_by' => $by->id,
        ])->fresh();
    }

    /** Locks the row; refuses one already voided (or replaced, which voids it). */
    private function lockLive(EmployeeBonus $bonus, string $key): EmployeeBonus
    {
        $locked = EmployeeBonus::whereKey($bonus->getKey())->lockForUpdate()->firstOrFail();
        if ($locked->isVoided()) {
            throw ValidationException::withMessages([$key => 'This bonus was already changed or voided.']);
        }

        return $locked;
    }

    private function reason(mixed $value, string $key, string $message): string
    {
        $reason = trim((string) $value);
        if ($reason === '' || mb_strlen($reason) > 200) {
            throw ValidationException::withMessages([$key => $message]);
        }

        return $reason;
    }

    private function values(EmployeeBonus $bonus): array
    {
        return ['employee' => $bonus->employee->full_name, 'business_date' => $bonus->business_date->toDateString(), 'amount' => $bonus->amount, 'reason' => $bonus->reason];
    }

    private function authorize(User $by): void
    {
        if (! $by->can('employees.manage')) {
            throw new AuthorizationException('You cannot give or change bonuses.');
        }
    }
}

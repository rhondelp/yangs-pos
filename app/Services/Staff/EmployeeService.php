<?php

namespace App\Services\Staff;

use App\Enums\EmploymentStatus;
use App\Enums\PayType;
use App\Models\Employee;
use App\Models\EmployeeRate;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Employee records and their pay history (ARCHITECTURE §3.13). Pay is a payroll record: a rate is never changed in
 * place, an edit adds a version that replaces it. Pay values never go into employee.updated.
 */
class EmployeeService
{
    public const FIELDS = ['full_name', 'phone', 'email', 'address', 'position', 'employment_status', 'date_hired', 'date_separated',
        'emergency_contact_name', 'emergency_contact_phone', 'user_id'];

    private const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

    public function __construct(private ActivityLogger $logger, private ScheduleService $schedules) {}

    /** @param  array<string, mixed>  $input  FIELDS plus pay_type, rate, custom_basis */
    public function create(User $by, array $input): Employee
    {
        $this->authorize($by);
        $data = $this->validateDetails($this->clean($input), null);
        $pay = $this->validatePay([...$input, 'effective_from' => $data['date_hired']]);

        return DB::transaction(function () use ($by, $data, $pay) {
            $employee = Employee::forceCreate($data);
            $this->logger->log('employee.created', $employee, [], $this->loggable($employee, self::FIELDS), $by);
            $this->writeRate($employee, $by, $pay);

            return $employee;
        });
    }

    /** @param  array<string, mixed>  $input  any of FIELDS; pay changes go through addRate / editRate */
    public function update(Employee $employee, User $by, array $input): Employee
    {
        $this->authorize($by);
        $data = $this->validateDetails([...$this->loggable($employee, self::FIELDS), ...$this->clean(array_intersect_key($input, array_flip(self::FIELDS)))], $employee);

        return DB::transaction(function () use ($employee, $by, $data) {
            $locked = Employee::whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            $locked->forceFill($data);
            $changed = array_keys($locked->getDirty());
            $old = $this->loggable($locked, $changed, $locked->getOriginal());
            $locked->save();
            if ($changed !== []) {
                $this->logger->log('employee.updated', $locked, $old, $this->loggable($locked, $changed), $by);
            }
            if ($locked->employment_status->isSeparated()) { // no shifts after the last day of work
                $this->schedules->cancelAfter($locked, $locked->date_separated->toDateString(), $by);
            }
            $employee->setRawAttributes($locked->getAttributes(), true);

            return $employee;
        });
    }

    /** Archives the employee and cancels their shifts after today, so they leave the schedule. */
    public function archive(Employee $employee, User $by): void
    {
        $this->authorize($by);
        DB::transaction(function () use ($employee, $by) {
            $employee->delete(); // logs employee.archived
            $this->schedules->cancelAfter($employee, BusinessDate::for(now()), $by, 'Employee archived');
        });
    }

    public function restore(Employee $employee, User $by): void
    {
        $this->authorize($by);
        DB::transaction(fn () => $employee->restore()); // logs employee.restored
    }

    /** @param  array{pay_type: mixed, rate: mixed, custom_basis?: mixed, effective_from: mixed}  $input */
    public function addRate(Employee $employee, User $by, array $input): EmployeeRate
    {
        $this->authorize($by);
        $pay = $this->validatePay($input);

        return DB::transaction(function () use ($employee, $by, $pay) {
            Employee::whereKey($employee->getKey())->lockForUpdate()->firstOrFail(); // one pay change at a time
            $this->refuseTakenDate($employee->id, $pay['effective_from']);

            return $this->writeRate($employee, $by, $pay);
        });
    }

    /** A corrected version of $rate (amount, type, basis or start date). The old row stays as history. */
    public function editRate(EmployeeRate $rate, User $by, array $input): EmployeeRate
    {
        $this->authorize($by);
        $pay = $this->validatePay($input);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 200) {
            throw ValidationException::withMessages(['reason' => 'Say why this rate is changed (up to 200 characters).']);
        }

        return DB::transaction(function () use ($rate, $by, $pay, $reason) {
            Employee::whereKey($rate->employee_id)->lockForUpdate()->firstOrFail();
            if (EmployeeRate::where('replaces_id', $rate->id)->exists()) {
                throw ValidationException::withMessages(['rate' => 'This rate was already changed. Edit the latest version.']);
            }
            $this->refuseTakenDate($rate->employee_id, $pay['effective_from'], $rate->id);
            $new = EmployeeRate::forceCreate([...$pay, 'employee_id' => $rate->employee_id, 'replaces_id' => $rate->id, 'created_by' => $by->id]);
            $this->logger->log('employee.rate_edited', $rate->employee, $this->payValues($rate), [...$this->payValues($new), 'reason' => $reason], $by);

            return $new;
        });
    }

    /** The pay in force on $date: the latest version not replaced by another, starting on or before $date. */
    public function rateOn(Employee $employee, string $date): ?EmployeeRate
    {
        return EmployeeRate::where('employee_id', $employee->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('employee_rates as newer')->whereColumn('newer.replaces_id', 'employee_rates.id'))
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->first();
    }

    /** One live rate per start date, so which rate wins never depends on which was saved last. */
    private function refuseTakenDate(int $employeeId, string $date, ?int $editing = null): void
    {
        $taken = EmployeeRate::where('employee_id', $employeeId)->whereDate('effective_from', $date)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('employee_rates as newer')->whereColumn('newer.replaces_id', 'employee_rates.id'))
            ->when($editing, fn ($q) => $q->whereKeyNot($editing))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['effective_from' => 'A rate already starts on '.CarbonImmutable::parse($date)->format('M j, Y').'. Edit it instead.']);
        }
    }

    private function writeRate(Employee $employee, User $by, array $pay): EmployeeRate
    {
        $before = $this->rateOn($employee, $pay['effective_from']);
        $rate = EmployeeRate::forceCreate([...$pay, 'employee_id' => $employee->id, 'created_by' => $by->id]);
        $this->logger->log('employee.rate_changed', $employee, $before ? $this->payValues($before) : [], $this->payValues($rate), $by);

        return $rate;
    }

    private function validateDetails(array $data, ?Employee $current): array
    {
        $statuses = array_column(EmploymentStatus::cases(), 'value');
        $separated = [EmploymentStatus::Resigned->value, EmploymentStatus::Terminated->value];
        $validated = Validator::make($data, [
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:300'],
            'position' => ['required', 'string', 'max:60'],
            'employment_status' => ['required', Rule::in($statuses)],
            'date_hired' => ['required', 'date_format:Y-m-d'],
            'date_separated' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_hired', Rule::requiredIf(in_array($data['employment_status'] ?? null, $separated, true))],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ], [
            'date_separated.required' => 'Enter the last day of work.',
            'date_separated.after_or_equal' => 'The last day of work cannot be before the hire date.',
        ])->validate();

        if (($validated['user_id'] ?? null) !== null) {
            $holder = Employee::withTrashed()->where('user_id', $validated['user_id'])->when($current, fn ($q) => $q->whereKeyNot($current->id))->first();
            if ($holder) {
                throw ValidationException::withMessages(['user_id' => "This login already belongs to {$holder->full_name}."]);
            }
        }
        if (! in_array($validated['employment_status'], $separated, true)) {
            $validated['date_separated'] = null; // back from leave or rehired: no separation date
        }

        return $validated;
    }

    /** @return array{pay_type: string, rate: string, custom_basis: ?string, effective_from: string} */
    private function validatePay(array $input): array
    {
        $input['custom_basis'] = trim((string) ($input['custom_basis'] ?? '')) ?: null;
        $input['rate'] = is_int($input['rate'] ?? null) || is_float($input['rate'] ?? null) ? (string) $input['rate'] : ($input['rate'] ?? null);
        $data = Validator::make($input, [
            'pay_type' => ['required', Rule::in(array_column(PayType::cases(), 'value'))],
            'rate' => ['required', 'string', 'regex:'.self::MONEY, 'not_regex:/^0+(\.0+)?$/'],
            'custom_basis' => ['nullable', 'string', 'max:80', Rule::requiredIf(($input['pay_type'] ?? null) === PayType::Custom->value)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
        ], [
            'rate.regex' => 'Enter the rate in pesos, up to 2 decimals.',
            'rate.not_regex' => 'The rate must be more than ₱0.00.',
            'custom_basis.required' => 'Say what the custom rate pays for, e.g. "per event".',
        ])->validate();
        if ($data['pay_type'] !== PayType::Custom->value) {
            $data['custom_basis'] = null;
        }

        return $data;
    }

    /** Trimmed strings; blanks become null. */
    private function clean(array $input): array
    {
        return array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, array_intersect_key($input, array_flip(self::FIELDS)));
    }

    /** Plain values for the log (enums as values, dates as Y-m-d). */
    private function loggable(Employee $employee, array $keys, ?array $raw = null): array
    {
        $values = [];
        foreach ($keys as $key) {
            $value = $raw !== null ? ($raw[$key] ?? null) : $employee->{$key};
            $values[$key] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                default => $value,
            };
        }

        return $values;
    }

    private function payValues(EmployeeRate $rate): array
    {
        return ['pay_type' => $rate->pay_type->value, 'rate' => $rate->rate, 'custom_basis' => $rate->custom_basis, 'effective_from' => $rate->effective_from->toDateString()];
    }

    private function authorize(User $by): void
    {
        if (! $by->can('employees.manage')) {
            throw new AuthorizationException('You cannot manage employees.');
        }
    }
}

<?php

namespace App\Services\Staff;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Employee shifts as real moments (ARCHITECTURE §3.7). A cell is one employee on one business day; times before the
 * cutoff belong to the next calendar day. The schedules_no_overlap constraint is the backstop; this checks first so
 * the message names the clash. Changes are logged with old and new values; shifts are cancelled, never deleted.
 */
class ScheduleService
{
    public const TZ = 'Asia/Manila';

    private const MAX_HOURS = 16;

    public function __construct(private ActivityLogger $logger) {}

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} start and end of a shift entered on business day $dutyDate */
    public function times(string $dutyDate, string $start, string $end): array
    {
        $startsAt = CarbonImmutable::parse("{$dutyDate} {$start}", self::TZ);
        if ($start < substr(config('pos.business_day_cutoff'), 0, 5)) {
            $startsAt = $startsAt->addDay(); // 02:00 on the Nov 18 business day is Nov 19 02:00
        }
        $endsAt = CarbonImmutable::parse($startsAt->toDateString()." {$end}", self::TZ);

        return [$startsAt, $endsAt->lessThanOrEqualTo($startsAt) ? $endsAt->addDay() : $endsAt];
    }

    /** @param  array{employee_id: mixed, duty_date: mixed, shift_id?: mixed, start_time?: mixed, end_time?: mixed, note?: mixed}  $input */
    public function create(User $by, array $input): Schedule
    {
        $this->authorize($by);
        $data = $this->validated($input);
        $employee = $this->activeEmployee($data['employee_id']);

        return $this->guardOverlap($employee, $data, function () use ($by, $data, $employee) {
            $schedule = $this->insert($employee, $data, $by);
            $this->logger->log('schedule.created', $schedule, [], $this->values($schedule), $by);

            return $schedule;
        });
    }

    /** Moves, retimes or re-notes a live shift; the employee stays. */
    public function update(Schedule $schedule, User $by, array $input): Schedule
    {
        $this->authorize($by);
        $keep = null;
        if (($input['start_time'] ?? null) === null && ($input['end_time'] ?? null) === null && $schedule->shift_id !== null
            && (int) ($input['shift_id'] ?? 0) === $schedule->shift_id) {
            // Same template, no times sent: keep this shift's own times (a later template edit must not move it).
            $input['start_time'] = $schedule->starts_at->setTimezone(self::TZ)->format('H:i');
            $input['end_time'] = $schedule->ends_at->setTimezone(self::TZ)->format('H:i');
            $keep = $schedule->shift_id;
        }
        $data = $this->validated([...$input, 'employee_id' => $schedule->employee_id], $keep);

        return $this->guardOverlap($schedule->employee, $data, function () use ($schedule, $by, $data) {
            $locked = Schedule::whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['schedule' => 'This shift was cancelled.']);
            }
            $old = $this->values($locked);
            $locked->forceFill(['shift_id' => $data['shift_id'], 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
                'duty_date' => $data['duty_date'], 'note' => $data['note'], 'updated_by' => $by->id])->save();
            $new = $this->values($locked->load('shift'));
            if ($old !== $new) {
                $changed = array_keys(array_diff_assoc($new, $old));
                $this->logger->log('schedule.changed', $locked, array_intersect_key($old, array_flip($changed)), array_intersect_key($new, array_flip($changed)), $by);
            }

            return $locked;
        }, $schedule->id);
    }

    public function cancel(Schedule $schedule, User $by, ?string $reason): Schedule
    {
        $this->authorize($by);
        $reason = trim((string) $reason);
        if ($reason === '' || mb_strlen($reason) > 200) {
            throw ValidationException::withMessages(['reason' => 'Say why this shift is cancelled (up to 200 characters).']);
        }

        return DB::transaction(fn () => $this->cancelRow($schedule, $by, $reason));
    }

    /**
     * Copies every live shift of $fromWeek to the same weekday and times of $toWeek (ISO weeks, "2026-W47").
     *
     * @return array{copied: int, skipped: list<array{employee: string, date: string, reason: string}>}
     */
    public function copyWeek(User $by, string $fromWeek, string $toWeek): array
    {
        $this->authorize($by);
        $from = self::monday($fromWeek);
        $shiftDays = (int) $from->diffInDays(self::monday($toWeek));
        $today = BusinessDate::for(now());

        return DB::transaction(function () use ($by, $from, $shiftDays, $today, $fromWeek) {
            $copied = 0;
            $skipped = [];
            $source = Schedule::whereNull('cancelled_at')->whereBetween('duty_date', [$from->toDateString(), $from->addDays(6)->toDateString()])
                ->with('employee', 'shift')->orderBy('starts_at')->get();
            foreach ($source as $shift) {
                $date = CarbonImmutable::parse($shift->duty_date->toDateString())->addDays($shiftDays)->toDateString();
                [$startsAt, $endsAt] = $this->times($date, $shift->starts_at->setTimezone(self::TZ)->format('H:i'), $shift->ends_at->setTimezone(self::TZ)->format('H:i'));
                $employee = Employee::whereKey($shift->employee_id)->lockForUpdate()->first();
                $reason = match (true) {
                    $employee?->employment_status !== EmploymentStatus::Active => 'Not active',
                    $date < $today => 'In the past',
                    $this->clash($employee->id, $startsAt, $endsAt) !== null => 'Overlaps another shift',
                    default => null,
                };
                if ($reason !== null) {
                    $skipped[] = ['employee' => $shift->employee->full_name, 'date' => $date, 'reason' => $reason];

                    continue;
                }
                $copy = $this->insert($employee, ['shift_id' => $shift->shift_id, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'duty_date' => $date, 'note' => $shift->note], $by);
                $this->logger->log('schedule.created', $copy, [], [...$this->values($copy), 'copied_from' => $shift->id], $by);
                $copied++;
            }
            $this->logger->log('schedule.week_copied', null, ['week' => $fromWeek], ['copied' => $copied, 'skipped' => count($skipped)], $by);

            return ['copied' => $copied, 'skipped' => $skipped];
        });
    }

    /** Cancels an employee's live shifts after their last day. Called inside EmployeeService's transaction. */
    public function cancelAfter(Employee $employee, string $date, User $by, string $reason = 'Employee separated'): int
    {
        $later = Schedule::where('employee_id', $employee->id)->whereNull('cancelled_at')->whereDate('duty_date', '>', $date)->get();
        $later->each(fn (Schedule $s) => $this->cancelRow($s, $by, $reason));

        return $later->count();
    }

    /**
     * The grid for one ISO week: Monday to Sunday, employees who are active or have a shift that week (active first).
     *
     * @return array{days: list<string>, employees: Collection<int, Employee>, cells: array<int, array<string, list<Schedule>>>}
     */
    public function week(string $isoWeek): array
    {
        $monday = self::monday($isoWeek);
        $days = array_map(fn (int $i) => $monday->addDays($i)->toDateString(), range(0, 6));
        $shifts = Schedule::whereNull('cancelled_at')->whereBetween('duty_date', [$days[0], $days[6]])->with('shift')->orderBy('starts_at')->get();
        $employees = Employee::withTrashed()
            ->where(fn ($q) => $q->where(fn ($a) => $a->whereNull('deleted_at')->where('employment_status', EmploymentStatus::Active->value))
                ->orWhereIn('id', $shifts->pluck('employee_id')))
            ->get()
            ->sortBy([fn ($a, $b) => ($a->employment_status !== EmploymentStatus::Active) <=> ($b->employment_status !== EmploymentStatus::Active), ['full_name', 'asc']])
            ->values();
        $cells = [];
        foreach ($shifts as $shift) {
            $cells[$shift->employee_id][$shift->duty_date->toDateString()][] = $shift;
        }

        return ['days' => $days, 'employees' => $employees, 'cells' => $cells];
    }

    /** @return Collection<int, Schedule> live shifts of one business day, earliest first */
    public function day(string $date): Collection
    {
        return Schedule::whereNull('cancelled_at')->whereDate('duty_date', $date)->with('employee', 'shift')->orderBy('starts_at')->get();
    }

    /** Monday 00:00 of an ISO week ("2026-W47"), independent of the locale's first weekday. */
    public static function monday(string $isoWeek): CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $isoWeek, $m)) {
            throw new \InvalidArgumentException("Not an ISO week: {$isoWeek}");
        }

        return CarbonImmutable::now(self::TZ)->setISODate((int) $m[1], (int) $m[2], 1)->startOfDay();
    }

    /** The ISO week ("2026-W47") a business date belongs to. */
    public static function weekOf(string $date): string
    {
        return CarbonImmutable::parse($date, self::TZ)->isoFormat('GGGG-[W]WW');
    }

    /** Runs $write in a transaction holding the employee's row, after checking for a clash; a race still gets the message. */
    private function guardOverlap(Employee $employee, array $data, callable $write, ?int $ignore = null): Schedule
    {
        try {
            return DB::transaction(function () use ($employee, $data, $write, $ignore) {
                Employee::whereKey($employee->id)->lockForUpdate()->first(); // one writer per employee
                if ($clash = $this->clash($employee->id, $data['starts_at'], $data['ends_at'], $ignore)) {
                    $this->refuseClash($employee, $clash);
                }

                return $write();
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01' && $clash = $this->clash($employee->id, $data['starts_at'], $data['ends_at'], $ignore)) {
                $this->refuseClash($employee, $clash);
            }
            throw $e;
        }
    }

    private function clash(int $employeeId, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $ignore = null): ?Schedule
    {
        return Schedule::where('employee_id', $employeeId)->whereNull('cancelled_at')
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->with('shift')->first();
    }

    private function refuseClash(Employee $employee, Schedule $clash): never
    {
        $start = $clash->starts_at->setTimezone(self::TZ);
        throw ValidationException::withMessages(['start_time' => sprintf('%s already works %s, %s %s–%s.', $employee->full_name,
            $clash->shift?->name ?? 'a custom shift', $clash->duty_date->format('D M j'), $start->format('H:i'), $clash->ends_at->setTimezone(self::TZ)->format('H:i'))]);
    }

    /** @return array{employee_id: int, shift_id: ?int, duty_date: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, note: ?string} */
    private function validated(array $input, ?int $keepShift = null): array
    {
        $input['note'] = trim((string) ($input['note'] ?? '')) ?: null;
        $data = Validator::make($input, [
            'employee_id' => ['required', 'integer'],
            'duty_date' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'start_time' => ['nullable', 'required_without:shift_id', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_without:shift_id', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'start_time.required_without' => 'Choose a shift or enter a start time.',
            'end_time.required_without' => 'Enter an end time.',
            'shift_id.exists' => 'Choose a shift template.',
        ])->validate();

        $template = isset($data['shift_id']) ? Shift::find($data['shift_id']) : null;
        $start = $data['start_time'] ?? $template->start_time;
        $end = $data['end_time'] ?? $template->end_time;
        if (($data['start_time'] ?? null) === null xor ($data['end_time'] ?? null) === null) {
            throw ValidationException::withMessages([($data['start_time'] ?? null) === null ? 'start_time' : 'end_time' => 'Enter both times, or neither to use the template.']);
        }
        [$startsAt, $endsAt] = $this->times($data['duty_date'], $start, $end);
        if ($startsAt->diffInMinutes($endsAt) > self::MAX_HOURS * 60) {
            throw ValidationException::withMessages(['end_time' => 'A shift can be at most '.self::MAX_HOURS.' hours.']);
        }

        return [
            'employee_id' => (int) $data['employee_id'],
            // Times that differ from the template make it a custom shift.
            'shift_id' => $keepShift ?? ($template && $template->start_time === $start && $template->end_time === $end ? $template->id : null),
            'duty_date' => $data['duty_date'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'note' => $data['note'],
        ];
    }

    private function activeEmployee(int $id): Employee
    {
        $employee = Employee::find($id) ?? throw ValidationException::withMessages(['employee_id' => 'Choose an employee.']);
        if ($employee->employment_status !== EmploymentStatus::Active) {
            throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} is not active."]);
        }

        return $employee;
    }

    private function insert(Employee $employee, array $data, User $by): Schedule
    {
        return Schedule::forceCreate([
            'employee_id' => $employee->id, 'shift_id' => $data['shift_id'], 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
            'duty_date' => BusinessDate::for($data['starts_at']), 'note' => $data['note'], 'created_by' => $by->id,
        ])->load('shift', 'employee');
    }

    private function cancelRow(Schedule $schedule, User $by, string $reason): Schedule
    {
        $locked = Schedule::whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();
        if ($locked->isCancelled()) {
            throw ValidationException::withMessages(['reason' => 'This shift was already cancelled.']);
        }
        $locked->forceFill(['cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason])->save();
        $this->logger->log('schedule.cancelled', $locked, $this->values($locked->load('shift', 'employee')), ['reason' => $reason], $by);

        return $locked;
    }

    /** Readable values for the log, in Manila time. */
    private function values(Schedule $schedule): array
    {
        return [
            'employee' => $schedule->employee->full_name,
            'duty_date' => $schedule->duty_date->toDateString(),
            'shift' => $schedule->shift?->name ?? 'Custom',
            'starts_at' => $schedule->starts_at->setTimezone(self::TZ)->format('Y-m-d H:i'),
            'ends_at' => $schedule->ends_at->setTimezone(self::TZ)->format('Y-m-d H:i'),
            'note' => $schedule->note,
        ];
    }

    private function authorize(User $by): void
    {
        if (! $by->can('schedules.manage')) {
            throw new AuthorizationException('You cannot change schedules.');
        }
    }
}

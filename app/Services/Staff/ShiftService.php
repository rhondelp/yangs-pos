<?php

namespace App\Services\Staff;

use App\Models\Shift;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Shift templates. Schedules keep their own times, so an edit here never moves an existing shift. */
class ShiftService
{
    public function __construct(private ActivityLogger $logger) {}

    /** @param  array{name: mixed, start_time: mixed, end_time: mixed, is_active: mixed}  $input */
    public function update(Shift $shift, User $by, array $input): Shift
    {
        if (! $by->can('schedules.manage')) {
            throw new AuthorizationException('You cannot change shift templates.');
        }
        $input['name'] = trim((string) ($input['name'] ?? ''));
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:40', Rule::unique('shifts', 'name')->ignore($shift->id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'is_active' => ['required', 'boolean'],
        ], ['end_time.different' => 'The end time must differ from the start time.', 'name.unique' => 'Another template already has this name.'])->validate();

        [$from, $to] = array_map(fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2), [$data['start_time'], $data['end_time']]);
        if (($to > $from ? $to - $from : $to + 1440 - $from) > 16 * 60) { // same cap as a schedule
            throw ValidationException::withMessages(['end_time' => 'A shift can be at most 16 hours.']);
        }

        return DB::transaction(function () use ($shift, $by, $data) {
            // Compare as H:i (the model already trims the database seconds), so an untouched time is not logged.
            $old = ['name' => $shift->name, 'start_time' => $shift->start_time, 'end_time' => $shift->end_time, 'is_active' => $shift->is_active];
            $new = [...$data, 'is_active' => (bool) $data['is_active']];
            $changed = array_keys(array_filter($new, fn ($v, $k) => $old[$k] !== $v, ARRAY_FILTER_USE_BOTH));
            $shift->fill($new)->save();
            if ($changed !== []) {
                $this->logger->log('shift.updated', $shift, array_intersect_key($old, array_flip($changed)), array_intersect_key($new, array_flip($changed)), $by);
            }

            return $shift;
        });
    }
}

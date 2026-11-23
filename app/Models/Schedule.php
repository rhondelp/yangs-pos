<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One employee's shift, as real start and end moments. Written by ScheduleService; cancelled, never deleted. */
class Schedule extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'duty_date' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}

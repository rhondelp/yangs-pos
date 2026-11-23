<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** A shift template. Schedules copy its times, so editing a template never moves existing shifts. */
#[Fillable(['name', 'start_time', 'end_time', 'is_active'])]
class Shift extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** "20:00", not the database's "20:00:00". */
    protected function startTime(): Attribute
    {
        return Attribute::get(fn (?string $v) => $v === null ? null : substr($v, 0, 5));
    }

    protected function endTime(): Attribute
    {
        return Attribute::get(fn (?string $v) => $v === null ? null : substr($v, 0, 5));
    }
}

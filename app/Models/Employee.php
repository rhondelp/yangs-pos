<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Models\Concerns\HasAuditedSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A person on the payroll. Written by EmployeeService; pay lives in employee_rates. */
class Employee extends Model
{
    use HasAuditedSoftDeletes;

    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'date_hired' => 'date',
            'date_separated' => 'date',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(EmployeeRate::class);
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(EmployeeBonus::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

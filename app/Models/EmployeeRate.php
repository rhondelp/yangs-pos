<?php

namespace App\Models;

use App\Enums\PayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Pay from a date on. Append-only (trigger too): an edit is a new row whose replaces_id points here. */
class EmployeeRate extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Pay rates are never changed in place; edit adds a new version.'));
        static::deleting(fn () => throw new LogicException('Pay rates are never deleted.'));
    }

    protected function casts(): array
    {
        return ['pay_type' => PayType::class, 'rate' => 'decimal:2', 'effective_from' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

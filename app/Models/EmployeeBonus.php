<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Extra cash handed over on one night. The only later change is one void (trigger too); an edit voids and re-records. */
class EmployeeBonus extends Model
{
    private const VOID_COLUMNS = ['voided_at', 'voided_by', 'void_reason', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (EmployeeBonus $bonus) {
            if ($bonus->getOriginal('voided_at') !== null || array_diff(array_keys($bonus->getDirty()), self::VOID_COLUMNS) !== []) {
                throw new LogicException('Bonuses are not changed in place; edit records a new one.');
            }
        });
        static::deleting(fn () => throw new LogicException('Bonuses are never deleted; void them instead.'));
    }

    protected function casts(): array
    {
        return ['business_date' => 'date', 'amount' => 'decimal:2', 'voided_at' => 'datetime'];
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_id');
    }

    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}

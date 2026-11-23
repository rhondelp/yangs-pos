<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An operating expense. Written once by ExpenseService; the only later change is one void.
 * The expenses_void_once() trigger enforces the same rule in the database.
 */
class Expense extends Model
{
    private const VOID_COLUMNS = ['voided_at', 'voided_by', 'void_reason', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (Expense $expense) {
            if ($expense->getOriginal('voided_at') !== null || array_diff(array_keys($expense->getDirty()), self::VOID_COLUMNS) !== []) {
                throw new LogicException('Expenses cannot be edited; void and record again.');
            }
        });
        static::deleting(fn () => throw new LogicException('Expenses are never deleted; void them instead.'));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}

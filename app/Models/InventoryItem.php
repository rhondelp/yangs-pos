<?php

namespace App\Models;

use App\Models\Concerns\HasAuditedSoftDeletes;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// on_hand and avg_unit_cost are not fillable: only InventoryService changes them, together with a ledger row.
#[Fillable(['name', 'sku', 'base_unit', 'low_stock_level', 'category_id'])]
class InventoryItem extends Model
{
    use HasAuditedSoftDeletes;

    /** Stock states from best to worst; a move to a higher rank is a crossing (StockLevelCrossed). */
    public const STATUS_RANK = ['in_stock' => 0, 'low_stock' => 1, 'out_of_stock' => 2, 'negative_stock' => 3];

    protected $attributes = ['on_hand' => '0', 'avg_unit_cost' => '0', 'low_stock_level' => '0'];

    protected function casts(): array
    {
        return [
            'on_hand' => 'decimal:3',
            'avg_unit_cost' => 'decimal:6',
            'low_stock_level' => 'decimal:3',
        ];
    }

    /** Badge key: archived, negative_stock, out_of_stock, low_stock (at or below the level) or in_stock. */
    public function stockStatus(): string
    {
        return $this->trashed() ? 'archived' : self::statusFor((string) $this->on_hand, (string) $this->low_stock_level);
    }

    /** The stock state of a quantity against a low-stock level. InventoryReportService repeats these rules in SQL. */
    public static function statusFor(string $onHand, string $lowLevel): string
    {
        return match (true) {
            bccomp($onHand, '0', 3) < 0 => 'negative_stock',
            bccomp($onHand, '0', 3) === 0 => 'out_of_stock',
            bccomp($onHand, $lowLevel, 3) <= 0 => 'low_stock',
            default => 'in_stock',
        };
    }

    /** What the stock on hand is worth at the average cost; stock below zero is worth nothing (ARCHITECTURE §3.6). */
    public function stockValue(): string
    {
        return bccomp((string) $this->on_hand, '0', 3) > 0 ? Money::round(bcmul((string) $this->on_hand, (string) $this->avg_unit_cost, 10)) : '0.00';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class)->orderBy('factor');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->orderByDesc('id');
    }
}

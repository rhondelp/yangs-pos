<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** The stock ledger. Σ quantity per item = inventory_items.on_hand. Written only by InventoryService. */
class InventoryMovement extends Model
{
    use AppendOnly;

    /** Matches the inventory_movements_type_check constraint. */
    public const TYPES = ['receipt', 'receipt_void', 'sale', 'void_return', 'adjustment'];

    /** @return array<string, string> type => label */
    public static function typeLabels(): array
    {
        return ['receipt' => __('Stock-in'), 'receipt_void' => __('Stock-in voided'), 'sale' => __('Sale'), 'void_return' => __('Returned'), 'adjustment' => __('Stock-out')];
    }

    /** @return array<string, string> stock-out reason => label (InventoryService::STOCK_OUT_REASONS) */
    public static function reasonLabels(): array
    {
        return ['damaged' => __('Damaged'), 'expired' => __('Expired'), 'missing' => __('Missing'), 'internal_use' => __('Internal use'), 'other' => __('Other')];
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:2',
            'balance_after' => 'decimal:3',
            'business_date' => 'date',
            'is_unusual' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

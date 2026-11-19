<?php

namespace App\Models;

use App\Models\Concerns\HasAuditedSoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// on_hand and avg_unit_cost are not fillable: only InventoryService changes them, together with a ledger row.
#[Fillable(['name', 'sku', 'base_unit', 'low_stock_level'])]
class InventoryItem extends Model
{
    use HasAuditedSoftDeletes;

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
        return match (true) {
            $this->trashed() => 'archived',
            bccomp((string) $this->on_hand, '0', 3) < 0 => 'negative_stock',
            bccomp((string) $this->on_hand, '0', 3) === 0 => 'out_of_stock',
            bccomp((string) $this->on_hand, (string) $this->low_stock_level, 3) <= 0 => 'low_stock',
            default => 'in_stock',
        };
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

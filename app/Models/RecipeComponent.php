<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Base units of one inventory item used by one sale of a product (or one of its options). */
#[Fillable(['product_id', 'product_variant_id', 'inventory_item_id', 'quantity'])]
class RecipeComponent extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }
}

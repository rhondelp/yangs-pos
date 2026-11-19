<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Another unit of an item, e.g. case = 24 bottles. The base unit has no row (factor 1). */
#[Fillable(['inventory_item_id', 'name', 'factor'])]
class InventoryUnit extends Model
{
    protected function casts(): array
    {
        return ['factor' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

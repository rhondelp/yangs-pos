<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Quantities are 3-decimal strings in the base unit; avg_unit_cost is a 6-decimal string per base unit. @mixin \App\Models\InventoryItem */
class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'base_unit' => $this->base_unit,
            'on_hand' => $this->on_hand,
            'avg_unit_cost' => $this->avg_unit_cost,
            'low_stock_level' => $this->low_stock_level,
            'stock_status' => $this->stockStatus(),
            'units' => $this->whenLoaded('units', fn () => $this->units->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'factor' => $u->factor])->values()),
            'archived' => $this->trashed(),
        ];
    }
}

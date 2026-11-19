<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RecipeComponent */
class RecipeComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'item' => ['id' => $this->item->id, 'name' => $this->item->name, 'base_unit' => $this->item->base_unit],
            'quantity' => $this->quantity,
        ];
    }
}

<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POS menu: what a cashier may sell, prices only (no cost or margin).
 *
 * @mixin \App\Models\Category
 */
class MenuCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'products' => $this->products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->value,
                'price' => $product->price,
                'image_url' => $product->imageUrl(),
                'variants' => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price' => $variant->price,
                ])->all(),
            ])->all(),
        ];
    }
}

<?php

namespace App\Http\Resources\V1;

use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => ['id' => $this->category_id, 'name' => $this->whenLoaded('category', fn () => $this->category->name)],
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'price' => $this->price,
            // Cost and margin are profit data: catalogue managers only.
            $this->mergeWhen((bool) $request->user()?->can(Permission::ProductsManage->value), fn () => [
                'cost' => $this->cost,
                'margin' => $this->margin(),
            ]),
            'image_url' => $this->imageUrl(),
            'is_available' => $this->is_available,
            'track_inventory' => $this->track_inventory,
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
            'archived' => $this->trashed(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

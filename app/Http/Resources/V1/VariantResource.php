<?php

namespace App\Http\Resources\V1;

use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            $this->mergeWhen((bool) $request->user()?->can(Permission::ProductsManage->value), fn () => [
                'cost' => $this->cost,
                'margin' => $this->margin(),
            ]),
        ];
    }
}

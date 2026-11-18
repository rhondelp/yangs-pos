<?php

namespace App\Http\Resources\V1;

use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            'variant_name' => $this->variant_name,
            'unit_price' => $this->unit_price,
            // Cost is profit data: catalogue managers only.
            $this->mergeWhen((bool) $request->user()?->can(Permission::ProductsManage->value), fn () => ['unit_cost' => $this->unit_cost]),
            'quantity' => $this->quantity,
            'discount' => $this->discount,
            'line_total' => $this->line_total,
            'status' => $this->status->value,
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
        ];
    }
}

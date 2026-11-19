<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderVoid */
class OrderVoidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'voided_at' => $this->created_at->toIso8601String(),
        ];
    }
}

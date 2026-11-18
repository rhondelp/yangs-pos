<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'table_session_id' => $this->table_session_id,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

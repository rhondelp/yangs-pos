<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->whenLoaded('method', fn () => $this->method->name),
            'amount' => $this->amount,
            'tendered' => $this->tendered,
            'change_given' => $this->change_given,
            'reference_no' => $this->reference_no,
            'paid_at' => $this->paid_at->toIso8601String(),
        ];
    }
}

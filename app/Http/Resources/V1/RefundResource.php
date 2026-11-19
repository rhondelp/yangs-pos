<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Refund */
class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'method' => $this->method->name,
            'amount' => $this->amount,
            'reference_no' => $this->reference_no,
            'reason' => $this->reason,
            'refunded_at' => $this->created_at->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The payment's method and reference from this correction on. @mixin \App\Models\PaymentCorrection */
class PaymentCorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'method' => $this->newMethod->name,
            'reference_no' => $this->new_reference_no,
            'reason' => $this->reason,
            'corrected_at' => $this->created_at->toIso8601String(),
        ];
    }
}

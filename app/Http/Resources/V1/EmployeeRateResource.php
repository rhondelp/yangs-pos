<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One version of an employee's pay. replaced = a later edit took its place (history only). @mixin \App\Models\EmployeeRate */
class EmployeeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pay_type' => $this->pay_type->value,
            'rate' => $this->rate,
            'custom_basis' => $this->custom_basis,
            'effective_from' => $this->effective_from->toDateString(),
            'replaces_id' => $this->replaces_id,
            'replaced' => $this->replacedBy()->exists(),
            'created_by' => $this->createdBy?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

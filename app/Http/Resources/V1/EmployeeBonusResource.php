<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A night bonus, paid in cash that night. A voided row was removed or replaced by an edit. @mixin \App\Models\EmployeeBonus */
class EmployeeBonusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee?->full_name,
            'business_date' => $this->business_date->toDateString(),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'given_by' => $this->givenBy?->name,
            'replaces_id' => $this->replaces_id,
            'voided' => $this->isVoided(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
        ];
    }
}

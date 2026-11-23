<?php

namespace App\Http\Resources\V1;

use App\Services\Staff\EmployeeService;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** An employee. Pay (today's rate) only for users with employees.manage. @mixin \App\Models\Employee */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'position' => $this->position,
            'employment_status' => $this->employment_status->value,
            'status_label' => $this->employment_status->label(),
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'date_hired' => $this->date_hired->toDateString(),
            'date_separated' => $this->date_separated?->toDateString(),
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'user_id' => $this->user_id,
            'archived' => $this->trashed(),
            $this->mergeWhen((bool) $request->user()?->can('employees.manage'), function () {
                $rate = app(EmployeeService::class)->rateOn($this->resource, BusinessDate::for(now()));

                return ['pay_type' => $rate?->pay_type->value, 'rate' => $rate?->rate, 'custom_basis' => $rate?->custom_basis];
            }),
        ];
    }
}

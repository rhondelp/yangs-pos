<?php

namespace App\Http\Resources\V1;

use App\Services\Staff\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One shift. Times in Manila (ISO 8601); duty_date is the business day it starts on. shift null = custom. @mixin \App\Models\Schedule */
class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee?->full_name,
            'shift_id' => $this->shift_id,
            'shift' => $this->shift?->name,
            'duty_date' => $this->duty_date->toDateString(),
            'starts_at' => $this->starts_at->setTimezone(ScheduleService::TZ)->toIso8601String(),
            'ends_at' => $this->ends_at->setTimezone(ScheduleService::TZ)->toIso8601String(),
            'note' => $this->note,
            'cancelled' => $this->isCancelled(),
            'cancel_reason' => $this->cancel_reason,
        ];
    }
}

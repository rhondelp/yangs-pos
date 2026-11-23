<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:schedules.manage. Name and time rules live in ShiftService.
class ShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }

    /** @return array<string, mixed> */
    public function shiftData(): array
    {
        return [...$this->only(['name', 'start_time', 'end_time']), 'is_active' => $this->boolean('is_active')];
    }
}

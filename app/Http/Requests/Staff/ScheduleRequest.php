<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:schedules.manage. Time, overlap and status rules live in ScheduleService.
class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['employee_id' => [$this->route('schedule') ? 'prohibited' : 'required', 'integer']];
    }

    public function messages(): array
    {
        return ['employee_id.prohibited' => 'A shift stays with its employee; cancel it and assign a new one instead.', 'employee_id.required' => 'Choose an employee.'];
    }

    /** @return array<string, mixed> blank times count as "use the template" */
    public function scheduleData(): array
    {
        return array_map(fn ($v) => $v === '' ? null : $v, $this->only(['employee_id', 'duty_date', 'shift_id', 'start_time', 'end_time', 'note']));
    }
}

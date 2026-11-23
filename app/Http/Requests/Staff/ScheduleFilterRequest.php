<?php

namespace App\Http\Requests\Staff;

use App\Rules\IsoWeek;
use App\Services\Staff\ScheduleService;
use App\Support\BusinessDate;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:schedules.view.
class ScheduleFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week' => ['nullable', new IsoWeek],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['date.date_format' => 'Choose a real date.'];
    }

    /** This business week when neither is given. */
    public function businessWeek(): string
    {
        return $this->validated('week') ?? ScheduleService::weekOf($this->validated('date') ?? BusinessDate::for(now()));
    }

    public function businessDate(): string
    {
        return $this->validated('date') ?? BusinessDate::for(now());
    }
}

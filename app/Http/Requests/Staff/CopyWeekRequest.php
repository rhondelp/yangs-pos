<?php

namespace App\Http\Requests\Staff;

use App\Rules\IsoWeek;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:schedules.manage.
class CopyWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_week' => ['required', new IsoWeek],
            'to_week' => ['required', new IsoWeek, 'different:from_week'],
        ];
    }

    public function messages(): array
    {
        return ['to_week.different' => 'Choose another week to copy to.'];
    }
}

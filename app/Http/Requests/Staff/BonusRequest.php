<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:employees.manage. Amount and date rules live in BonusService; an edit (PUT) needs a reason.
class BonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->isMethod('put') ? ['edit_reason' => ['required', 'string', 'max:200']] : [];
    }

    public function messages(): array
    {
        return ['edit_reason.required' => 'Say why this bonus is changed.'];
    }

    /** @return array<string, mixed> */
    public function bonusData(): array
    {
        return $this->only(['employee_id', 'business_date', 'amount', 'reason', 'edit_reason']);
    }
}

<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:employees.manage. Pay rules live in EmployeeService; an edit (PUT) needs a reason.
class RateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->isMethod('put') ? ['reason' => ['required', 'string', 'max:200']] : [];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Say why this rate is changed.'];
    }

    /** @return array<string, mixed> */
    public function rateData(): array
    {
        return $this->only(['pay_type', 'rate', 'custom_basis', 'effective_from', 'reason']);
    }
}

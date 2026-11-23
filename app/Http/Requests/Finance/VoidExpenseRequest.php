<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:expenses.manage.
class VoidExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:300']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Say why this expense is voided.'];
    }
}

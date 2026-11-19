<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:orders.discount. Range rules (≤ subtotal, ≤ 100%, not below paid) live in BillService.
class ApplyDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:amount,percent'],
            'value' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.*' => 'Choose an amount or a percent.',
            'value.regex' => 'Enter the discount as a number with up to two decimals.',
            'reason.required' => 'Give a reason for the discount.',
        ];
    }
}

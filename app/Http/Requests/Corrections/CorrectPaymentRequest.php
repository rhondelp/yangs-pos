<?php

namespace App\Http\Requests\Corrections;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:corrections.create. Method rules live in PaymentCorrectionService.
class CorrectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method_id' => ['required', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'reason' => ['required', 'string', 'max:200'],
            // Checked by ApprovalService when the require_approval_pin setting is on.
            'approver_username' => ['nullable', 'string', 'max:50'],
            'approver_pin' => ['nullable', 'string', 'max:6'],
        ];
    }
}

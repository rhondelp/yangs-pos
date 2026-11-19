<?php

namespace App\Http\Requests\Corrections;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:payments.refund. The amount cap and the tab state live in RefundService.
class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'idempotency_key' => ['required', 'uuid'],
            'method_id' => ['nullable', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'reason' => ['required', 'string', 'max:200'],
            // Checked by ApprovalService when the require_approval_pin setting is on.
            'approver_username' => ['nullable', 'string', 'max:50'],
            'approver_pin' => ['nullable', 'string', 'max:6'],
        ];
    }
}

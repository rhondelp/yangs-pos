<?php

namespace App\Http\Requests\Corrections;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:orders.void. Limits and the tab state live in VoidService.
class VoidItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'reason' => ['required', 'string', 'max:200'],
            'idempotency_key' => ['required', 'uuid'],
            'return_to_stock' => ['sometimes', 'boolean'], // "Put back in stock?"; default yes
            // Checked by ApprovalService when the require_approval_pin setting is on.
            'approver_username' => ['nullable', 'string', 'max:50'],
            'approver_pin' => ['nullable', 'string', 'max:6'],
        ];
    }
}

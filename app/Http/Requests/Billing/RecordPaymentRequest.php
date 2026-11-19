<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:payments.record. Balance, cash and reference rules live in PaymentService.
class RecordPaymentRequest extends FormRequest
{
    private const MONEY = 'regex:/^\d{1,10}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'uuid'],
            'amount' => ['nullable', self::MONEY],
            'tendered' => ['nullable', self::MONEY],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter the amount with up to two decimals.',
            'tendered.regex' => 'Enter the cash received with up to two decimals.',
            'idempotency_key.*' => 'This payment is missing its request key. Reload the page and try again.',
        ];
    }

    /** Input for PaymentService::record, money as strings. */
    public function payment(): array
    {
        $data = $this->validated();

        return [
            'method_id' => (int) $data['method_id'],
            'idempotency_key' => $data['idempotency_key'],
            'amount' => isset($data['amount']) ? (string) $data['amount'] : null,
            'tendered' => isset($data['tendered']) ? (string) $data['tendered'] : null,
            'reference_no' => $data['reference_no'] ?? null,
        ];
    }
}

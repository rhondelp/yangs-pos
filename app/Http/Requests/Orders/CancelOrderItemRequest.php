<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:orders.cancel-item.
class CancelOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:200'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

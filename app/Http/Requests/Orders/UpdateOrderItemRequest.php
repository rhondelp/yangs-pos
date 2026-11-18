<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// Authorization: route can:pos.create-order; the discount permission is checked in OrderService.
class UpdateOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'discount' => ['sometimes', 'decimal:0,2', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if (! $this->hasAny(['quantity', 'discount', 'notes'])) {
                $v->errors()->add('quantity', 'Send a quantity, discount or notes to change.');
            }
        }];
    }

    /** Changes with a real int quantity and a string discount, as the service expects. */
    public function changes(): array
    {
        $changes = $this->validated();
        if (isset($changes['quantity'])) {
            $changes['quantity'] = (int) $changes['quantity'];
        }
        if (isset($changes['discount'])) {
            $changes['discount'] = (string) $changes['discount'];
        }

        return $changes;
    }
}

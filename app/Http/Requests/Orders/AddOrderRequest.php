<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:pos.create-order. Menu rules (sellable product, option of that product) live in OrderService.
class AddOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.variant_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'lines.*.notes' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** Lines with real ints: the service rejects numeric strings. */
    public function lines(): array
    {
        return array_map(fn (array $line) => [
            'product_id' => (int) $line['product_id'],
            'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
            'quantity' => (int) $line['quantity'],
            'notes' => $line['notes'] ?? null,
        ], array_values($this->validated('lines')));
    }
}

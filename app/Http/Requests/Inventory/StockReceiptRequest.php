<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:inventory.stock-in. Item, unit, quantity and cost rules live in InventoryService::receive.
class StockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer'],
            'invoice_no' => ['nullable', 'string', 'max:60'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_item_id' => ['required', 'integer'],
            'lines.*.inventory_unit_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required'],
            'lines.*.line_cost' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one item.',
            'lines.*.inventory_item_id.required' => 'Choose an item.',
            'lines.*.quantity.required' => 'Enter the quantity.',
            'lines.*.line_cost.required' => 'Enter what the line cost.',
        ];
    }
}

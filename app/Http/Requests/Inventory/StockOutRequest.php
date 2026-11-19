<?php

namespace App\Http\Requests\Inventory;

use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Authorization: route can:inventory.adjust. Quantity, unit and stock rules live in InventoryService::stockOut.
class StockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'string', 'max:20'],
            'inventory_unit_id' => ['nullable', 'integer'],
            'reason' => ['required', Rule::in(InventoryService::STOCK_OUT_REASONS)],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return ['reason.*' => 'Choose why the stock is going out.'];
    }
}

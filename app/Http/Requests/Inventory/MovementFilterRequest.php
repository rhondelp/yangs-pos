<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:inventory.view.
class MovementFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['nullable', 'integer', 'exists:inventory_items,id'], // archived items count: their history stays
            'type' => ['nullable', 'in:'.implode(',', InventoryMovement::TYPES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['to.after_or_equal' => 'The end date cannot be before the start date.'];
    }

    /** Dates are business dates (the day cut off at 06:00). @return array{item_id: ?int, type: ?string, from: ?string, to: ?string, user_id: ?int} */
    public function filters(): array
    {
        $int = fn (string $key) => $this->validated($key) === null ? null : (int) $this->validated($key);

        return [
            'item_id' => $int('item_id'),
            'type' => $this->validated('type'),
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
            'user_id' => $int('user_id'),
        ];
    }
}

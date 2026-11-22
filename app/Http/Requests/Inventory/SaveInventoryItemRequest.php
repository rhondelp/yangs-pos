<?php

namespace App\Http\Requests\Inventory;

use App\Models\Category;
use App\Models\InventoryItem;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:inventory.manage. Stock and cost are never accepted here.
class SaveInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $unique = fn (string $column, string $message) => function (string $attribute, mixed $value, Closure $fail) use ($item, $column, $message) {
            $taken = InventoryItem::whereRaw("lower({$column}) = ?", [mb_strtolower(trim((string) $value))])
                ->when($item, fn ($q) => $q->whereKeyNot($item->getKey()))
                ->exists();
            $taken && $fail($message);
        };

        return [
            'name' => [$item ? 'sometimes' : 'required', 'required', 'string', 'max:120', $unique('name', 'An item with this name already exists.')],
            'sku' => ['nullable', 'string', 'max:60', $unique('sku', 'Another item uses this SKU.')],
            'base_unit' => [$item ? 'sometimes' : 'required', 'required', 'string', 'max:20'],
            'low_stock_level' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,3})?$/'],
            // An archived category may stay on an item that already uses it, but cannot be newly chosen.
            'category_id' => ['bail', 'nullable', 'integer', 'exists:categories,id', function (string $attribute, mixed $value, Closure $fail) use ($item) {
                $archived = Category::onlyTrashed()->whereKey($value)->exists();
                $archived && (int) $value !== $item?->category_id && $fail('Choose a category that is in use.');
            }],
        ];
    }

    public function messages(): array
    {
        return ['low_stock_level.regex' => 'Enter the low-stock level as a number, up to 3 decimals.'];
    }

    public function itemData(): array
    {
        $data = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $this->validated());
        if (array_key_exists('low_stock_level', $data)) {
            $data['low_stock_level'] ??= '0';
        }
        if (isset($data['category_id'])) {
            $data['category_id'] = (int) $data['category_id'];
        }
        if (isset($data['base_unit'])) {
            $data['base_unit'] = mb_strtolower($data['base_unit']);
        }

        return $data;
    }
}

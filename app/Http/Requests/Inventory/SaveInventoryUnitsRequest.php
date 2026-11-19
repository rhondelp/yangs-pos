<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:inventory.manage. Name and factor rules live in InventoryItemService::saveUnits.
class SaveInventoryUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'units' => ['present', 'array', 'max:20'],
            'units.*.id' => ['nullable', 'integer'],
            'units.*.name' => ['nullable', 'string', 'max:30'],
            'units.*.factor' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** Rows left completely blank on the form are ignored. */
    public function units(): array
    {
        return array_values(array_filter($this->validated('units') ?? [], fn (array $u) => trim((string) ($u['name'] ?? '')) !== '' || trim((string) ($u['factor'] ?? '')) !== ''));
    }
}

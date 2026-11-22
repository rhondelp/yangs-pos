<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:inventory.view.
class InventoryLevelFilterRequest extends FormRequest
{
    /** attention = low, out or below zero. */
    public const STATUSES = ['attention', 'low', 'out', 'negative'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'], // archived categories still filter
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'archived' => ['nullable', 'boolean'],
        ];
    }

    /** @return array{search: ?string, category_id: ?int, status: ?string, archived: bool} */
    public function filters(): array
    {
        return [
            'search' => trim((string) $this->validated('search')) ?: null,
            'category_id' => $this->validated('category_id') === null ? null : (int) $this->validated('category_id'),
            'status' => $this->validated('status'),
            'archived' => $this->boolean('archived'),
        ];
    }
}

<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ProductFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route can:products.view
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,archived,all'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array{q?: ?string, category_id?: ?int, status?: ?string} */
    public function filters(): array
    {
        return $this->safe()->only(['q', 'category_id', 'status']);
    }
}

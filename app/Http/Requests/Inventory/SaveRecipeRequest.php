<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:inventory.manage. Item and quantity rules live in RecipeService.
class SaveRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['nullable', 'integer'],
            'components' => ['present', 'array', 'max:20'],
            'components.*.inventory_item_id' => ['nullable', 'integer'],
            'components.*.quantity' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** Rows with no item chosen are ignored, so a blank form row never blocks saving. */
    public function components(): array
    {
        return array_values(array_filter($this->validated('components') ?? [], fn (array $c) => ! empty($c['inventory_item_id'])));
    }
}

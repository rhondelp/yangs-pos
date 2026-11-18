<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

// Create (POST) and update (PUT/PATCH; fields optional), web and API. Authorization: route can:products.manage.
class SaveProductRequest extends FormRequest
{
    private const MONEY = ['decimal:0,2', 'min:0', 'max:9999999999.99'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_available', 'track_inventory', 'remove_image'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => $this->boolean($flag)]);
            }
        }

        // The web form always sends its option rows; with every row removed the browser sends none.
        if ($this->boolean('variants_sent') && ! $this->has('variants')) {
            $this->merge(['variants' => []]);
        }
    }

    public function rules(): array
    {
        $required = $this->route('product') ? ['sometimes', 'required'] : ['required'];

        return [
            'category_id' => [...$required, 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'name' => [...$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => [...$required, Rule::enum(ProductType::class)],
            'price' => [...$required, ...self::MONEY],
            'cost' => ['nullable', ...self::MONEY],
            'is_available' => ['sometimes', 'boolean'],
            'track_inventory' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'variants' => ['sometimes', 'array', 'max:20'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'variants.*.price' => ['required', ...self::MONEY],
            'variants.*.cost' => ['nullable', ...self::MONEY],
        ];
    }

    public function attributes(): array
    {
        return [
            'variants.*.name' => 'option name',
            'variants.*.price' => 'option price',
            'variants.*.cost' => 'option cost',
            'category_id' => 'category',
        ];
    }

    /** Validated product fields for ProductService (image handled separately). */
    public function productData(): array
    {
        return Arr::except($this->validated(), ['image', 'remove_image']);
    }
}

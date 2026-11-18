<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:products.manage.
class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $required = $category ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:80', function (string $attribute, mixed $value, Closure $fail) use ($category) {
                $taken = Category::whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($category, fn ($q) => $q->whereKeyNot($category->getKey()))
                    ->exists();
                $taken && $fail('A category with this name already exists.');
            }],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function categoryData(): array
    {
        $data = $this->validated();
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            unset($data['sort_order']);
        }

        return $data;
    }
}

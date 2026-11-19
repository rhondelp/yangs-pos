<?php

namespace App\Http\Requests\Inventory;

use App\Models\Supplier;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:inventory.manage.
class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'name' => ['required', 'string', 'max:120', function (string $attribute, mixed $value, Closure $fail) use ($supplier) {
                $taken = Supplier::whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($supplier, fn ($q) => $q->whereKeyNot($supplier->getKey()))
                    ->exists();
                $taken && $fail('A supplier with this name already exists.');
            }],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function supplierData(): array
    {
        return array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $this->validated());
    }
}

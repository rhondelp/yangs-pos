<?php

namespace App\Http\Requests\Billing;

use App\Models\PaymentMethod;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:settings.manage.
class SavePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_reference' => $this->boolean('requires_reference'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    public function rules(): array
    {
        $method = $this->route('paymentMethod');

        return [
            'name' => ['required', 'string', 'max:60', function (string $attribute, mixed $value, Closure $fail) use ($method) {
                $taken = PaymentMethod::whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($method, fn ($q) => $q->whereKeyNot($method->getKey()))
                    ->exists();
                $taken && $fail('A payment method with this name already exists.');
            }],
            'requires_reference' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function methodData(): array
    {
        $data = $this->validated();
        $data['name'] = trim($data['name']);
        $data['sort_order'] ??= 0;

        return $data;
    }
}

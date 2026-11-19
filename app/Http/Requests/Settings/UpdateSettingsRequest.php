<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:settings.manage.
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['clean_table_after_payment' => $this->boolean('clean_table_after_payment')]);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:120'],
            'business_address' => ['nullable', 'string', 'max:300'],
            'business_tin' => ['nullable', 'string', 'max:30'],
            'receipt_footer' => ['nullable', 'string', 'max:300'],
            'clean_table_after_payment' => ['boolean'],
        ];
    }

    /** Validated values with blanks as empty strings, the way settings store them. */
    public function settings(): array
    {
        return array_map(fn ($v) => $v ?? '', $this->validated());
    }
}

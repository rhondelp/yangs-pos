<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

// Voiding a bonus or cancelling a shift. Authorization: the route's can: middleware.
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:200']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Give a reason.'];
    }
}

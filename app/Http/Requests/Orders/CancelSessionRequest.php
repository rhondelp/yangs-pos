<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:pos.cancel-session.
class CancelSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:200']];
    }
}

<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:pos.open-session. Whether the table can take a tab is decided by TableSessionService.
class OpenSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['notes' => ['nullable', 'string', 'max:500']];
    }
}

<?php

namespace App\Http\Requests\Tables;

use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Authorization: route can:tables.update-status. Which changes are allowed is decided by TableStatusService.
class ChangeTableStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TableStatus::class)],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function targetStatus(): TableStatus
    {
        return TableStatus::from($this->validated('status'));
    }
}

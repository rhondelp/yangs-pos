<?php

namespace App\Http\Requests\Tables;

use App\Enums\Permission;
use App\Enums\TableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TableFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route can:tables.view
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TableStatus::class)],
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }

    public function status(): ?TableStatus
    {
        return TableStatus::tryFrom((string) $this->validated('status'));
    }

    /** Only table managers may see tables that are out of use. */
    public function includeInactive(): bool
    {
        return $this->boolean('include_inactive') && $this->user()->can(Permission::TablesManage->value);
    }
}

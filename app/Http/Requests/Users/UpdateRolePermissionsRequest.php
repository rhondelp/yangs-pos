<?php

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Web: the whole matrix, permissions[roleId][] = name. API: one role, permissions[] = name.
class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware can:roles.manage
    }

    public function rules(): array
    {
        $known = Rule::in(array_column(Permission::cases(), 'value'));

        if ($this->route('role')) {
            return [
                'permissions' => ['present', 'array'],
                'permissions.*' => ['string', $known],
            ];
        }

        return [
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string', $known],
        ];
    }
}

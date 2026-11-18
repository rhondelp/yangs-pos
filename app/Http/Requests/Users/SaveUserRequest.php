<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

// Create (POST) and update (PUT) of a user account, web and API.
class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => mb_strtolower(trim((string) $this->input('username')))]);

        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($target)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'password' => [$target ? 'nullable' : 'required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in(self::assignableRoles($this->user()))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Use lowercase letters, numbers, dots, dashes or underscores only.',
            'role.in' => 'Choose one of the listed roles.',
        ];
    }

    /** Role names this user may hand out. Only an Owner may grant the Owner role. */
    public static function assignableRoles(User $actor): array
    {
        return Role::orderBy('name')->pluck('name')
            ->reject(fn (string $name) => $name === RoleName::Owner->value && ! $actor->hasRole(RoleName::Owner->value))
            ->values()
            ->all();
    }
}

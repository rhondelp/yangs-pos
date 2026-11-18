<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * Replace one role's permissions. The Owner role is locked to every permission.
     *
     * @param list<string> $permissions validated permission names
     */
    public function sync(Role $role, array $permissions): Role
    {
        if ($role->name === RoleName::Owner->value) {
            throw ValidationException::withMessages(['permissions' => 'The Owner role always has every permission and cannot be changed.']);
        }

        return DB::transaction(function () use ($role, $permissions) {
            $old = $role->permissions->pluck('name')->sort()->values()->all();
            $new = collect($permissions)->unique()->sort()->values()->all();

            if ($old !== $new) {
                $role->syncPermissions($new);
                $this->logger->log('role.permissions_updated', $role, ['permissions' => $old], ['permissions' => $new]);
            }

            return $role->load('permissions');
        });
    }

    /**
     * Save the whole matrix from the web form. A role missing from $matrix has every box unticked.
     *
     * @param array<int|string, list<string>> $matrix role id => permission names
     */
    public function syncMatrix(array $matrix): void
    {
        DB::transaction(function () use ($matrix) {
            $roles = Role::with('permissions')->get()->keyBy('id');
            $owner = $roles->firstWhere('name', RoleName::Owner->value);

            foreach (array_keys($matrix) as $roleId) {
                if (! $roles->has($roleId) || (int) $roleId === $owner?->id) {
                    throw ValidationException::withMessages(['permissions' => 'The Owner role cannot be changed, and every role in the form must exist.']);
                }
            }

            foreach ($roles as $role) {
                if ($role->isNot($owner)) {
                    $this->sync($role, $matrix[$role->id] ?? []);
                }
            }
        });
    }
}

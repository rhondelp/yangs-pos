<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Safe for production and re-runs: adds missing permissions and roles, re-grants Owner everything,
// and leaves the permissions the owner set on other roles untouched.
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value);
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value);

            if ($roleName === RoleName::Owner) {
                $role->syncPermissions(Permission::cases());
            } elseif ($roleName === RoleName::Cashier && $role->wasRecentlyCreated) {
                $role->syncPermissions(Permission::cashierDefaults());
            }
        }
    }
}

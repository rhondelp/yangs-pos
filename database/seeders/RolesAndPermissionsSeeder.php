<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Safe for production and re-runs: adds missing permissions and roles, re-grants Owner everything,
// and leaves the permissions the owner set on other roles untouched. A Cashier default that is new
// in this release is granted to an existing Cashier role once, when the permission is first created.
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $new = [];
        foreach (Permission::cases() as $permission) {
            if (PermissionModel::findOrCreate($permission->value)->wasRecentlyCreated) {
                $new[] = $permission;
            }
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value);

            if ($roleName === RoleName::Owner) {
                $role->syncPermissions(Permission::cases());
            } elseif ($roleName === RoleName::Cashier) {
                $role->wasRecentlyCreated
                    ? $role->syncPermissions(Permission::cashierDefaults())
                    : $role->givePermissionTo(array_values(array_filter($new, fn (Permission $p) => in_array($p, Permission::cashierDefaults(), true))));
            }
        }
    }
}

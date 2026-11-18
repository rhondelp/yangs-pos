<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;

/**
 * Sidebar items for the management layout. Add an entry when a module's index page ships;
 * an item shows only if the user holds its permission (null = any signed-in user).
 */
class Navigation
{
    /** @return list<array{label: string, route: string, active: string, icon: string, permission: ?Permission}> */
    private static function all(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home', 'permission' => null],
            ['label' => 'Products', 'route' => 'products.index', 'active' => 'products.*', 'icon' => 'cube', 'permission' => Permission::ProductsView],
            ['label' => 'Categories', 'route' => 'categories.index', 'active' => 'categories.*', 'icon' => 'folder', 'permission' => Permission::ProductsManage],
            ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'users', 'permission' => Permission::UsersManage],
            ['label' => 'Roles', 'route' => 'roles.index', 'active' => 'roles.*', 'icon' => 'shield-check', 'permission' => Permission::RolesManage],
            ['label' => 'Activity log', 'route' => 'activity-logs.index', 'active' => 'activity-logs.*', 'icon' => 'clipboard-list', 'permission' => Permission::LogsView],
        ];
    }

    /** @return list<array{label: string, route: string, active: string, icon: string, permission: ?Permission}> */
    public static function items(User $user): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $item) => $item['permission'] === null || $user->can($item['permission']->value),
        ));
    }
}

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
            ['label' => 'Floor', 'route' => 'floor', 'active' => 'floor', 'icon' => 'map', 'permission' => Permission::TablesView],
            ['label' => 'Transactions', 'route' => 'transactions.index', 'active' => 'transactions.*', 'icon' => 'receipt', 'permission' => Permission::OrdersViewOwn],
            ['label' => 'Tables', 'route' => 'tables.index', 'active' => 'tables.*', 'icon' => 'squares', 'permission' => Permission::TablesManage],
            ['label' => 'Products', 'route' => 'products.index', 'active' => 'products.*', 'icon' => 'cube', 'permission' => Permission::ProductsView],
            ['label' => 'Categories', 'route' => 'categories.index', 'active' => 'categories.*', 'icon' => 'folder', 'permission' => Permission::ProductsManage],
            ['label' => 'Inventory', 'route' => 'inventory.index', 'active' => 'inventory.*', 'icon' => 'archive', 'permission' => Permission::InventoryView],
            ['label' => 'Stock-in', 'route' => 'stock-receipts.index', 'active' => 'stock-receipts.*', 'icon' => 'inbox', 'permission' => Permission::InventoryView],
            ['label' => 'Expenses', 'route' => 'expenses.index', 'active' => 'expenses.*', 'icon' => 'banknotes', 'permission' => Permission::ExpensesView],
            ['label' => 'Profit & loss', 'route' => 'finance.index', 'active' => 'finance.*', 'icon' => 'chart', 'permission' => Permission::FinanceView],
            ['label' => 'Employees', 'route' => 'employees.index', 'active' => 'employees.*', 'icon' => 'user', 'permission' => Permission::EmployeesView],
            ['label' => 'Schedule', 'route' => 'schedule.week', 'active' => 'schedule.*', 'icon' => 'clipboard-list', 'permission' => Permission::SchedulesView],
            ['label' => 'Suppliers', 'route' => 'suppliers.index', 'active' => 'suppliers.*', 'icon' => 'folder', 'permission' => Permission::InventoryManage],
            ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'users', 'permission' => Permission::UsersManage],
            ['label' => 'Roles', 'route' => 'roles.index', 'active' => 'roles.*', 'icon' => 'shield-check', 'permission' => Permission::RolesManage],
            ['label' => 'Activity log', 'route' => 'activity-logs.index', 'active' => 'activity-logs.*', 'icon' => 'clipboard-list', 'permission' => Permission::LogsView],
            ['label' => 'Payment methods', 'route' => 'payment-methods.index', 'active' => 'payment-methods.*', 'icon' => 'banknotes', 'permission' => Permission::SettingsManage],
            ['label' => 'Settings', 'route' => 'settings.edit', 'active' => 'settings.*', 'icon' => 'cog', 'permission' => Permission::SettingsManage],
        ];
    }

    /** Whether the user has pages beyond the dashboard, the floor and transactions (the POS header links those), i.e. a reason to leave the POS layout. */
    public static function hasManagementPages(User $user): bool
    {
        return collect(self::items($user))->whereNotIn('route', ['dashboard', 'floor', 'transactions.index'])->isNotEmpty();
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

<?php

namespace App\Enums;

/**
 * The permission catalogue. Code checks these names; RolesAndPermissionsSeeder syncs them to the database,
 * and the owner assigns them to roles in the permission matrix. Add a case here when a feature needs a new one.
 */
enum Permission: string
{
    case DashboardOwner = 'dashboard.owner';
    case DashboardCashier = 'dashboard.cashier';

    case TablesView = 'tables.view';
    case TablesUpdateStatus = 'tables.update-status';
    case TablesManage = 'tables.manage';

    case PosOpenSession = 'pos.open-session';
    case PosCreateOrder = 'pos.create-order';
    case PosTransferTab = 'pos.transfer-tab';
    case PosCancelSession = 'pos.cancel-session';

    case OrdersCancelItem = 'orders.cancel-item';
    case OrdersDiscount = 'orders.discount';
    case OrdersViewOwn = 'orders.view-own';
    case OrdersViewAll = 'orders.view-all';

    case PaymentsRecord = 'payments.record';
    case PaymentsRefund = 'payments.refund';
    case ReceiptsView = 'receipts.view';

    case CashDrawerManageOwn = 'cash-drawer.manage-own';
    case CashDrawerViewAll = 'cash-drawer.view-all';

    case ApprovalsGrant = 'approvals.grant';

    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';

    case InventoryView = 'inventory.view';
    case InventoryStockIn = 'inventory.stock-in';
    case InventoryAdjust = 'inventory.adjust';
    case InventoryManage = 'inventory.manage';
    case InventoryAlerts = 'inventory.alerts';

    case ExpensesView = 'expenses.view';
    case ExpensesManage = 'expenses.manage';
    case FinanceView = 'finance.view';

    case EmployeesView = 'employees.view';
    case EmployeesManage = 'employees.manage';
    case SchedulesView = 'schedules.view';
    case SchedulesManage = 'schedules.manage';
    case AttendanceManage = 'attendance.manage';
    case PayrollView = 'payroll.view';
    case PayrollManage = 'payroll.manage';

    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case LogsView = 'logs.view';
    case SettingsManage = 'settings.manage';

    /** @return list<self> */
    public static function cashierDefaults(): array
    {
        return [
            self::DashboardCashier, self::TablesView, self::TablesUpdateStatus, self::PosOpenSession, self::PosCreateOrder,
            self::OrdersViewOwn, self::PaymentsRecord, self::ReceiptsView, self::CashDrawerManageOwn,
        ];
    }

    /** Module heading in the permission matrix, e.g. "Cash drawer". */
    public function group(): string
    {
        $module = strstr($this->value, '.', true);

        return $module === 'pos' ? 'POS' : ucfirst(str_replace('-', ' ', $module));
    }

    /** Row label in the permission matrix, e.g. "Create order". */
    public function label(): string
    {
        return ucfirst(str_replace('-', ' ', substr(strstr($this->value, '.'), 1)));
    }
}

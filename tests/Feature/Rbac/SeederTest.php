<?php

namespace Tests\Feature\Rbac;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    private const CASHIER_PERMISSIONS = [
        'cash-drawer.manage-own',
        'dashboard.cashier',
        'orders.view-own',
        'payments.record',
        'pos.create-order',
        'pos.open-session',
        'receipts.view',
        'tables.update-status',
        'tables.view',
    ];

    public function test_seeder_creates_all_permissions_and_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(array_column(Permission::cases(), 'value'), PermissionModel::pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            ['Owner', 'Cashier', 'Supervisor', 'Bartender', 'Staff', 'Inventory Manager'],
            Role::pluck('name')->all(),
        );
        foreach (['pos.create-order', 'orders.cancel-item', 'payments.record', 'payments.refund', 'products.manage', 'inventory.manage', 'finance.view', 'employees.manage', 'payroll.manage', 'reports.view', 'users.manage', 'logs.view', 'settings.manage'] as $name) {
            $this->assertNotNull(Permission::tryFrom($name), "missing permission $name");
        }
    }

    public function test_owner_role_has_every_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertCount(count(Permission::cases()), Role::findByName(RoleName::Owner->value)->permissions);
    }

    public function test_cashier_role_has_only_pos_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(self::CASHIER_PERMISSIONS, Role::findByName('Cashier')->permissions->pluck('name')->all());
    }

    public function test_other_roles_start_empty(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['Supervisor', 'Bartender', 'Staff', 'Inventory Manager'] as $name) {
            $this->assertCount(0, Role::findByName($name)->permissions, $name);
        }
    }

    public function test_seeder_is_idempotent_and_keeps_owner_edits_but_resyncs_owner(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::findByName('Cashier')->givePermissionTo('reports.view');
        Role::findByName('Owner')->revokePermissionTo('logs.view');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(Permission::cases()), PermissionModel::count());
        $this->assertSame(6, Role::count());
        $this->assertTrue(Role::findByName('Cashier')->hasPermissionTo('reports.view'));
        $this->assertTrue(Role::findByName('Owner')->hasPermissionTo('logs.view'));
    }

    public function test_a_newly_added_cashier_default_reaches_an_existing_cashier_role_once(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $cashier = Role::findByName('Cashier');
        // Simulate a release that introduces tables.update-status after the shop was set up.
        PermissionModel::findByName('tables.update-status')->delete();
        $cashier->revokePermissionTo('receipts.view'); // an owner edit that must survive

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertTrue($cashier->fresh()->hasPermissionTo('tables.update-status'));
        $this->assertFalse($cashier->fresh()->hasPermissionTo('receipts.view'));

        $cashier->revokePermissionTo('tables.update-status'); // owner removes it later
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertFalse($cashier->fresh()->hasPermissionTo('tables.update-status'));
    }

    public function test_owner_seeder_creates_owner_from_config(): void
    {
        config(['auth.owner' => ['name' => 'Yang Owner', 'username' => 'Yang', 'email' => null, 'password' => 'secret-pass-123']]);

        $this->seed([RolesAndPermissionsSeeder::class, OwnerSeeder::class]);

        $owner = User::where('username', 'yang')->sole();
        $this->assertSame('Yang Owner', $owner->name);
        $this->assertTrue(Hash::check('secret-pass-123', $owner->password));
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->hasRole('Owner'));
    }

    public function test_owner_seeder_refuses_empty_or_short_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([null, '', 'short'] as $password) {
            config(['auth.owner' => ['name' => 'Owner', 'username' => 'owner', 'email' => null, 'password' => $password]]);
            try {
                $this->seed(OwnerSeeder::class);
                $this->fail('seeded an owner without a valid password');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('OWNER_PASSWORD', $e->getMessage());
            }
        }

        $this->assertSame(0, User::count());
    }

    public function test_owner_seeder_does_not_reset_existing_password(): void
    {
        config(['auth.owner' => ['name' => 'Owner', 'username' => 'owner', 'email' => null, 'password' => 'first-password']]);
        $this->seed([RolesAndPermissionsSeeder::class, OwnerSeeder::class]);

        config(['auth.owner.password' => 'second-password']);
        $this->seed(OwnerSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('first-password', User::sole()->password));
    }
}

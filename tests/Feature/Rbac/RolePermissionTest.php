<?php

namespace Tests\Feature\Rbac;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
    }

    /** The matrix as the web form submits it: role id => permission names, every non-Owner role present. */
    private function matrix(array $changes = []): array
    {
        return Role::with('permissions')->where('name', '!=', 'Owner')->get()
            ->mapWithKeys(fn (Role $role) => [$role->id => $changes[$role->name] ?? $role->permissions->pluck('name')->all()])
            ->all();
    }

    private function cashierRole(): Role
    {
        return Role::findByName('Cashier', 'web'); // explicit: actingAs(..., 'sanctum') changes the default guard
    }

    public function test_owner_sees_the_permission_matrix(): void
    {
        $this->actingAs($this->owner)->get('/roles')
            ->assertOk()
            ->assertSee('Inventory Manager')
            ->assertSee('Cash drawer')
            ->assertSee('Create order')
            ->assertSee('name="permissions['.$this->cashierRole()->id.'][]"', false);
    }

    public function test_permission_changes_take_effect(): void
    {
        $this->actingAs($this->cashier)->get('/users')->assertForbidden();

        $granted = [...Permission::cashierDefaults(), Permission::UsersManage];
        $this->actingAs($this->owner)
            ->put('/roles', ['permissions' => $this->matrix(['Cashier' => array_column($granted, 'value')])])
            ->assertRedirect('/roles');

        $this->actingAs($this->cashier->fresh())->get('/users')->assertOk();

        $this->actingAs($this->owner)->put('/roles', ['permissions' => $this->matrix(['Cashier' => []])]);

        $this->actingAs($this->cashier->fresh())->get('/users')->assertForbidden();
        $this->assertCount(0, $this->cashierRole()->permissions);
    }

    public function test_api_can_replace_a_roles_permissions(): void
    {
        $role = Role::findByName('Bartender');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['tables.view', 'pos.create-order']])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bartender')
            ->assertJsonPath('data.permissions', ['pos.create-order', 'tables.view']);

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonFragment(['name' => 'Owner', 'is_locked' => true]);
    }

    public function test_the_owner_role_cannot_be_changed(): void
    {
        $ownerRole = Role::findByName('Owner');

        $this->actingAs($this->owner)
            ->put('/roles', ['permissions' => [$ownerRole->id => ['tables.view']] + $this->matrix()])
            ->assertSessionHasErrors('permissions');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/v1/roles/{$ownerRole->id}", ['permissions' => []])
            ->assertUnprocessable();

        $this->assertCount(count(Permission::cases()), $ownerRole->fresh()->permissions);
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->actingAs($this->owner)
            ->put('/roles', ['permissions' => $this->matrix(['Cashier' => ['tables.view', 'launch.missiles']])])
            ->assertSessionHasErrors();

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/v1/roles/{$this->cashierRole()->id}", ['permissions' => ['launch.missiles']])
            ->assertJsonValidationErrors('permissions.0');

        $this->assertCount(count(Permission::cashierDefaults()), $this->cashierRole()->permissions);
    }

    public function test_cashier_is_forbidden_from_the_matrix_on_web_and_api(): void
    {
        $this->actingAs($this->cashier)->get('/roles')->assertForbidden();
        $this->actingAs($this->cashier)->put('/roles', ['permissions' => $this->matrix(['Cashier' => ['users.manage']])])->assertForbidden();
        $this->actingAs($this->cashier, 'sanctum')->getJson('/api/v1/roles')->assertForbidden();
        $this->actingAs($this->cashier, 'sanctum')->putJson("/api/v1/roles/{$this->cashierRole()->id}", ['permissions' => ['users.manage']])->assertForbidden();

        $this->assertFalse($this->cashierRole()->hasPermissionTo('users.manage'));
    }

    public function test_changes_are_logged_with_old_and_new_permissions(): void
    {
        $this->actingAs($this->owner)
            ->put('/roles', ['permissions' => $this->matrix(['Staff' => ['tables.view']])]);

        $log = ActivityLog::where('action', 'role.permissions_updated')->sole();
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame(Role::findByName('Staff')->id, $log->subject_id);
        $this->assertSame(['permissions' => []], $log->old_values);
        $this->assertSame(['permissions' => ['tables.view']], $log->new_values);
    }
}

<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function labels(User $user): array
    {
        return array_column(Navigation::items($user), 'label');
    }

    public function test_items_follow_the_users_permissions(): void
    {
        $owner = User::factory()->create()->assignRole('Owner');
        $cashier = User::factory()->create()->assignRole('Cashier');
        Role::findByName('Supervisor')->givePermissionTo('logs.view');
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->assertSame(['Dashboard', 'Floor', 'Tables', 'Products', 'Categories', 'Users', 'Roles', 'Activity log'], $this->labels($owner));
        $this->assertSame(['Dashboard', 'Floor'], $this->labels($cashier));
        $this->assertFalse(Navigation::hasManagementPages($cashier));
        $this->assertTrue(Navigation::hasManagementPages($owner));
        $this->assertSame(['Dashboard', 'Activity log'], $this->labels($supervisor));
    }

    public function test_management_layout_renders_only_permitted_links(): void
    {
        Role::findByName('Supervisor')->givePermissionTo('logs.view');
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)->get('/activity-logs')
            ->assertOk()
            ->assertSee('href="'.route('activity-logs.index').'"', false)
            ->assertDontSee('href="'.route('users.index').'"', false)
            ->assertDontSee('href="'.route('roles.index').'"', false);
    }
}

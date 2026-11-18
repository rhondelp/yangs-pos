<?php

namespace Tests\Feature\Tables;

use App\Enums\TableStatus;
use App\Models\User;
use App\Services\Tables\DiningTableService;
use App\Services\Tables\TableStatusService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TableApiTest extends TestCase
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

        $this->actingAs($this->owner);
        $tables = app(DiningTableService::class);
        $tables->create(['label' => 'T1', 'capacity' => 4]);
        $t2 = $tables->create(['label' => 'T2']);
        app(TableStatusService::class)->change($t2, TableStatus::Reserved);
        $tables->setActive($tables->create(['label' => 'Old patio']), false);
    }

    public function test_cashier_lists_active_tables_with_status_labels_and_allowed_changes(): void
    {
        $this->actingAs($this->cashier, 'sanctum')->getJson('/api/v1/tables?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'T1')
            ->assertJsonPath('data.0.capacity', 4)
            ->assertJsonPath('data.0.status', 'available')
            ->assertJsonPath('data.0.status_label', 'Available')
            ->assertJsonPath('data.0.manual_targets', ['reserved', 'cleaning']);

        $this->getJson('/api/v1/tables?status=reserved')->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'T2');
    }

    public function test_managers_can_include_inactive_tables(): void
    {
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/tables?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.2.is_active', false);
    }

    public function test_status_changes_and_rejections(): void
    {
        $id = \App\Models\DiningTable::where('label', 'T1')->value('id');
        $this->actingAs($this->cashier, 'sanctum');

        $this->patchJson("/api/v1/tables/{$id}/status", ['status' => 'cleaning'])
            ->assertOk()->assertJsonPath('data.status', 'cleaning');
        $this->patchJson("/api/v1/tables/{$id}/status", ['status' => 'occupied'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson("/api/v1/tables/{$id}/status", ['status' => 'on_fire'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $inactive = \App\Models\DiningTable::where('label', 'Old patio')->value('id');
        $this->patchJson("/api/v1/tables/{$inactive}/status", ['status' => 'reserved'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_permissions_are_enforced(): void
    {
        $id = \App\Models\DiningTable::where('label', 'T1')->value('id');
        Role::findByName('Bartender')->givePermissionTo('tables.view');
        $bartender = User::factory()->create()->assignRole('Bartender');

        $this->actingAs($bartender, 'sanctum')->getJson('/api/v1/tables')->assertOk();
        $this->patchJson("/api/v1/tables/{$id}/status", ['status' => 'reserved'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/tables')->assertUnauthorized();
        $this->patchJson("/api/v1/tables/{$id}/status", ['status' => 'reserved'])->assertUnauthorized();
    }
}

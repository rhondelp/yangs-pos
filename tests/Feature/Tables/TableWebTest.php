<?php

namespace Tests\Feature\Tables;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\User;
use App\Services\Tables\DiningTableService;
use App\Services\Tables\TableStatusService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TableWebTest extends TestCase
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

    private function table(string $label, array $attrs = []): DiningTable
    {
        $this->actingAs($this->owner);

        return app(DiningTableService::class)->create(['label' => $label, ...$attrs]);
    }

    private function floorLabels($response): array
    {
        return $response->viewData('tables')->pluck('label')->all();
    }

    public function test_owner_creates_edits_and_deactivates_tables(): void
    {
        $this->actingAs($this->owner);

        $this->post('/tables', ['label' => 'T1', 'capacity' => 4])->assertRedirect('/tables');
        $this->post('/tables', ['label' => 't1'])->assertSessionHasErrors('label');
        $this->post('/tables', ['label' => 'T2', 'capacity' => 0])->assertSessionHasErrors('capacity');

        $table = DiningTable::sole();
        $this->get('/tables')->assertOk()->assertSee('T1');
        $this->put("/tables/{$table->id}", ['label' => 'Bar 1', 'capacity' => ''])->assertRedirect('/tables');
        $this->assertSame('Bar 1', $table->fresh()->label);
        $this->assertNull($table->fresh()->capacity);

        $this->patch("/tables/{$table->id}/active", ['is_active' => '0'])->assertRedirect();
        $this->assertFalse($table->fresh()->is_active);
        $this->patch("/tables/{$table->id}/active", ['is_active' => '1'])->assertRedirect();
        $this->assertTrue($table->fresh()->is_active);
    }

    public function test_a_table_with_a_tab_cannot_be_deactivated_from_the_screen(): void
    {
        $table = $this->table('T1');
        app(TableStatusService::class)->change($table, TableStatus::Occupied, system: true);

        $this->actingAs($this->owner)->from('/tables')->patch("/tables/{$table->id}/active", ['is_active' => '0'])
            ->assertRedirect('/tables')->assertSessionHasErrors('table');
        $this->assertTrue($table->fresh()->is_active);
    }

    public function test_cashier_cannot_manage_tables(): void
    {
        $table = $this->table('T1');

        $this->actingAs($this->cashier);
        $this->get('/tables')->assertForbidden();
        $this->post('/tables', ['label' => 'T9'])->assertForbidden();
        $this->patch("/tables/{$table->id}/active", ['is_active' => '0'])->assertForbidden();
    }

    public function test_floor_shows_active_tables_with_text_labels_and_hides_deactivated_ones(): void
    {
        $this->table('T1', ['capacity' => 4]);
        $t2 = $this->table('T2');
        $gone = $this->table('Old patio');
        app(TableStatusService::class)->change($t2, TableStatus::Reserved);
        app(DiningTableService::class)->setActive($gone, false);

        $response = $this->actingAs($this->cashier)->get('/floor')
            ->assertOk()
            ->assertSee('data-layout="pos"', false)
            ->assertSee('4 seats')
            ->assertSee('Available')
            ->assertSee('Reserved')
            ->assertDontSee('Old patio');

        $this->assertSame(['T1', 'T2'], $this->floorLabels($response));
        $this->assertSame(1, $response->viewData('counts')['reserved'] ?? null);
        $this->assertSame(2, array_sum($response->viewData('counts')));
    }

    public function test_floor_filters_by_status(): void
    {
        $this->table('T1');
        $t2 = $this->table('T2');
        app(TableStatusService::class)->change($t2, TableStatus::Cleaning);

        $this->assertSame(['T2'], $this->floorLabels($this->actingAs($this->cashier)->get('/floor?status=cleaning')));
        $this->get('/floor?status=nonsense')->assertSessionHasErrors('status');
    }

    public function test_cashier_changes_status_on_the_floor_and_invalid_changes_explain_why(): void
    {
        $table = $this->table('T1');
        $this->actingAs($this->cashier);

        $this->from('/floor')->patch("/tables/{$table->id}/status", ['status' => 'reserved'])
            ->assertRedirect('/floor')->assertSessionHas('status');
        $this->assertSame(TableStatus::Reserved, $table->fresh()->status);

        $this->from('/floor')->patch("/tables/{$table->id}/status", ['status' => 'occupied'])
            ->assertRedirect('/floor')->assertSessionHasErrors('status');
        $this->assertSame(TableStatus::Reserved, $table->fresh()->status);
    }

    public function test_view_only_users_see_the_floor_but_cannot_change_status(): void
    {
        Role::findByName('Bartender')->givePermissionTo('tables.view');
        $bartender = User::factory()->create()->assignRole('Bartender');
        $table = $this->table('T1');

        $this->actingAs($bartender)->get('/floor')->assertOk()->assertDontSee('Mark reserved');
        $this->patch("/tables/{$table->id}/status", ['status' => 'reserved'])->assertForbidden();
    }

    public function test_cashier_dashboard_is_the_floor(): void
    {
        $this->actingAs($this->cashier)->get('/dashboard')->assertRedirect('/floor');
        $this->get('/floor')->assertOk()->assertSee('No tables yet')->assertDontSee('Management');
    }
}

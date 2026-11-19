<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Catalog\ProductService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryApiTest extends TestCase
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

    public function test_cashiers_without_inventory_permissions_are_refused(): void
    {
        $this->actingAs($this->cashier, 'sanctum');
        $this->getJson('/api/v1/inventory-items')->assertForbidden();
        $this->getJson('/api/v1/suppliers')->assertForbidden();
        $this->getJson('/api/v1/stock-receipts')->assertForbidden();
        $this->postJson('/api/v1/stock-receipts', [])->assertForbidden();
        $this->postJson('/api/v1/inventory-items', ['name' => 'x', 'base_unit' => 'pc'])->assertForbidden();
    }

    public function test_the_owner_sets_up_and_receives_stock_over_the_api(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $supplier = $this->postJson('/api/v1/suppliers', ['name' => 'San Miguel Brewery'])->assertCreated()->json('data');
        $item = $this->postJson('/api/v1/inventory-items', ['name' => 'Red Horse 500ml', 'base_unit' => 'bottle', 'low_stock_level' => '24'])
            ->assertCreated()->assertJsonPath('data.on_hand', '0.000')->json('data');
        $case = $this->putJson("/api/v1/inventory-items/{$item['id']}/units", ['units' => [['name' => 'case', 'factor' => '24']]])
            ->assertOk()->assertJsonPath('data.units.0.name', 'case')->json('data.units.0.id');

        $receipt = $this->postJson('/api/v1/stock-receipts', [
            'supplier_id' => $supplier['id'], 'invoice_no' => 'INV-9', 'received_at' => now()->toIso8601String(),
            'lines' => [['inventory_item_id' => $item['id'], 'inventory_unit_id' => $case, 'quantity' => '2', 'line_cost' => '1440']],
        ])->assertCreated()
            ->assertJsonPath('data.total_cost', '1440.00')
            ->assertJsonPath('data.supplier.name', 'San Miguel Brewery')
            ->assertJsonPath('data.lines.0.base_quantity', '48.000')
            ->assertJsonPath('data.lines.0.base_unit_cost', '30.000000')
            ->json('data');

        $this->getJson("/api/v1/inventory-items/{$item['id']}")->assertOk()
            ->assertJsonPath('data.on_hand', '48.000')->assertJsonPath('data.avg_unit_cost', '30.000000')->assertJsonPath('data.stock_status', 'in_stock');
        $this->getJson("/api/v1/inventory-items/{$item['id']}/movements")->assertOk()
            ->assertJsonPath('data.0.type', 'receipt')->assertJsonPath('data.0.quantity', '48.000')->assertJsonPath('data.0.reference', $receipt['reference']);
        $this->getJson('/api/v1/stock-receipts')->assertOk()->assertJsonPath('data.0.reference', $receipt['reference']);
        $this->getJson("/api/v1/stock-receipts/{$receipt['id']}")->assertOk()->assertJsonCount(1, 'data.lines');
    }

    public function test_validation_errors_come_back_as_422(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $item = InventoryItem::create(['name' => 'Ice', 'base_unit' => 'kg']);

        $this->postJson('/api/v1/stock-receipts', ['received_at' => now()->toIso8601String(), 'lines' => [['inventory_item_id' => $item->id, 'quantity' => '0', 'line_cost' => '10']]])
            ->assertUnprocessable()->assertJsonValidationErrors('lines.0.quantity');
        $this->postJson('/api/v1/suppliers', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_recipes_are_read_and_replaced_over_the_api(): void
    {
        Role::findByName('Cashier')->givePermissionTo('inventory.view');
        $this->actingAs($this->owner);
        $product = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Drinks'])->id, 'name' => 'Rum Coke', 'type' => 'drink', 'price' => '120']);
        $rum = InventoryItem::create(['name' => 'Tanduay Rum', 'base_unit' => 'ml']);

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/products/{$product->id}/recipe", ['components' => [['inventory_item_id' => $rum->id, 'quantity' => '45']]])
            ->assertOk()->assertJsonPath('data.0.item.name', 'Tanduay Rum')->assertJsonPath('data.0.quantity', '45.000');
        $this->actingAs($this->cashier->fresh(), 'sanctum')->getJson("/api/v1/products/{$product->id}/recipe")
            ->assertOk()->assertJsonPath('data.0.product_variant_id', null);
        $this->actingAs($this->cashier->fresh(), 'sanctum')->putJson("/api/v1/products/{$product->id}/recipe", ['components' => []])->assertForbidden();
    }

    public function test_suppliers_archive_and_restore_over_the_api(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $supplier = Supplier::create(['name' => 'GSMI']);

        $this->deleteJson("/api/v1/suppliers/{$supplier->id}")->assertOk()->assertJsonPath('data.archived', true);
        $this->postJson("/api/v1/suppliers/{$supplier->id}/restore")->assertOk()->assertJsonPath('data.archived', false);
    }
}

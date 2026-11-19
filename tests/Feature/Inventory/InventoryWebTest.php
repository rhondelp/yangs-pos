<?php

namespace Tests\Feature\Inventory;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\RecipeComponent;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\SupplierService;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryWebTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private InventoryItem $beer;

    private Supplier $smb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($this->owner);
        $this->smb = app(SupplierService::class)->create(['name' => 'San Miguel Brewery']);
        $items = app(InventoryItemService::class);
        $this->beer = $items->saveUnits($items->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle', 'low_stock_level' => '24']), [['name' => 'case', 'factor' => '24']]);
    }

    public function test_inventory_pages_need_their_permissions(): void
    {
        $this->actingAs($this->cashier);
        foreach (['/inventory', "/inventory/{$this->beer->id}", '/suppliers', '/stock-receipts', '/stock-receipts/create'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/stock-receipts', [])->assertForbidden();

        Role::findByName('Cashier')->givePermissionTo('inventory.view');
        $this->actingAs($this->cashier->fresh())->get('/inventory')->assertOk()->assertDontSee('Add item');
        $this->actingAs($this->cashier->fresh())->get('/stock-receipts/create')->assertForbidden();
    }

    public function test_the_owner_manages_suppliers(): void
    {
        $this->post('/suppliers', ['name' => 'Ginebra San Miguel', 'phone' => '0917 000 0000'])->assertRedirect('/suppliers');
        $gsm = Supplier::where('name', 'Ginebra San Miguel')->sole();
        $this->put("/suppliers/{$gsm->id}", ['name' => 'Ginebra San Miguel Inc.', 'phone' => '0917 000 0000'])->assertRedirect('/suppliers');
        $this->delete("/suppliers/{$gsm->id}")->assertRedirect('/suppliers');
        $this->post('/suppliers', ['name' => 'san miguel brewery'])->assertSessionHasErrors('name');

        $this->get('/suppliers')->assertOk()->assertSee('Ginebra San Miguel Inc.')->assertSee('Archived');
        $this->assertTrue(Supplier::withTrashed()->find($gsm->id)->trashed());
    }

    public function test_the_owner_adds_an_item_and_its_units(): void
    {
        $this->post('/inventory', ['name' => 'Tanduay Rum', 'base_unit' => 'ml', 'low_stock_level' => '1500'])->assertRedirect();
        $rum = InventoryItem::where('name', 'Tanduay Rum')->sole();
        $this->put("/inventory/{$rum->id}/units", ['units' => [['name' => 'bottle', 'factor' => '750']]])->assertRedirect("/inventory/{$rum->id}");

        $this->get("/inventory/{$rum->id}")->assertOk()->assertSee('1 bottle = 750 ml')->assertSee('No stock movements yet');
        $this->put("/inventory/{$rum->id}/units", ['units' => [['name' => 'ml', 'factor' => '1']]])->assertSessionHasErrors('units.0.name');
    }

    public function test_stock_in_from_the_form_posts_a_receipt_and_shows_it(): void
    {
        $this->get('/stock-receipts/create')->assertOk()->assertSee('Red Horse 500ml')->assertSee('San Miguel Brewery');

        $response = $this->post('/stock-receipts', [
            'supplier_id' => $this->smb->id, 'invoice_no' => 'INV-77', 'received_at' => now()->format('Y-m-d\TH:i'),
            'lines' => [['inventory_item_id' => $this->beer->id, 'inventory_unit_id' => $this->beer->units()->value('id'), 'quantity' => '2', 'line_cost' => '1440']],
        ]);

        $receipt = StockReceipt::sole();
        $response->assertRedirect("/stock-receipts/{$receipt->id}");
        $this->get("/stock-receipts/{$receipt->id}")->assertOk()
            ->assertSee($receipt->reference)->assertSee('INV-77')->assertSee('2 case')->assertSee('48 bottle')->assertSee('₱1,440.00');
        $this->get("/inventory/{$this->beer->id}")->assertOk()->assertSee('48 bottle')->assertSee($receipt->reference)->assertSee('₱30.00');
        $this->get('/stock-receipts')->assertOk()->assertSee($receipt->reference);
    }

    public function test_a_failed_stock_in_keeps_the_lines_and_lists_the_errors(): void
    {
        $this->from('/stock-receipts/create')->post('/stock-receipts', [
            'received_at' => now()->format('Y-m-d\TH:i'),
            'lines' => [['inventory_item_id' => $this->beer->id, 'quantity' => '0', 'line_cost' => '100']],
        ])->assertRedirect('/stock-receipts/create')->assertSessionHasErrors('lines.0.quantity');

        $this->followingRedirects()->from('/stock-receipts/create')->post('/stock-receipts', [
            'received_at' => now()->format('Y-m-d\TH:i'),
            'lines' => [['inventory_item_id' => $this->beer->id, 'quantity' => '0', 'line_cost' => '123.45']],
        ])->assertSee('role="alert"', false)->assertSee('123.45');
        $this->assertSame(0, StockReceipt::count());
    }

    public function test_the_recipe_card_on_the_product_page_saves(): void
    {
        $product = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85',
            'variants' => [['name' => 'Bucket of 6', 'price' => '480']]]);
        $bucket = $product->variants()->sole();

        $this->get("/products/{$product->id}/edit")->assertOk()->assertSee('Recipe')->assertSee('Bucket of 6');
        $this->put("/products/{$product->id}/recipe", ['product_variant_id' => $bucket->id, 'components' => [['inventory_item_id' => $this->beer->id, 'quantity' => '6']]])
            ->assertRedirect("/products/{$product->id}/edit");

        $this->assertSame('6.000', RecipeComponent::where('product_variant_id', $bucket->id)->sole()->quantity);
        $this->assertSame(1, ActivityLog::where('action', 'product.recipe_changed')->count());
        $this->get("/products/{$product->id}/edit")->assertSee('6 bottle');

        $this->actingAs($this->cashier)->put("/products/{$product->id}/recipe", ['components' => []])->assertForbidden();
    }

    public function test_navigation_lists_inventory_pages_by_permission(): void
    {
        $labels = array_column(Navigation::items($this->owner), 'label');
        foreach (['Inventory', 'Stock-in', 'Suppliers'] as $label) {
            $this->assertContains($label, $labels);
        }
        $this->assertNotContains('Inventory', array_column(Navigation::items($this->cashier), 'label'));
    }
}

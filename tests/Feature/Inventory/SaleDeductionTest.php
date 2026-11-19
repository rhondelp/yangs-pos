<?php

namespace Tests\Feature\Inventory;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\RecipeService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Settings\SettingService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaleDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private TableSession $session;

    private InventoryItem $beer; // 24 bottles at ₱30

    private InventoryItem $rum; // 750 ml at ₱1.333333

    private InventoryItem $cola; // 10 cans at ₱25

    private Product $redHorse; // tracked; base = 1 bottle; "Bucket of 6" = 6 bottles

    private Product $rumCoke; // tracked; 45 ml rum + 1 cola

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);

        $items = app(InventoryItemService::class);
        $this->beer = $items->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle']);
        $this->rum = $items->create(['name' => 'Tanduay Rum', 'base_unit' => 'ml']);
        $this->cola = $items->create(['name' => 'Coke can', 'base_unit' => 'pc']);
        $this->stockIn($this->beer, '24', '720');
        $this->stockIn($this->rum, '750', '1000');
        $this->stockIn($this->cola, '10', '250');

        $catalog = app(ProductService::class);
        $drinks = Category::create(['name' => 'Drinks'])->id;
        $this->redHorse = $catalog->create(['category_id' => $drinks, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '40', 'track_inventory' => true,
            'variants' => [['name' => 'Bucket of 6', 'price' => '480', 'cost' => '200']]]);
        $this->rumCoke = $catalog->create(['category_id' => $drinks, 'name' => 'Rum Coke', 'type' => 'drink', 'price' => '120', 'cost' => '50', 'track_inventory' => true]);
        $recipes = app(RecipeService::class);
        $recipes->save($this->redHorse, null, [['inventory_item_id' => $this->beer->id, 'quantity' => '1']]);
        $recipes->save($this->redHorse, $this->redHorse->variants()->sole(), [['inventory_item_id' => $this->beer->id, 'quantity' => '6']]);
        $recipes->save($this->rumCoke, null, [['inventory_item_id' => $this->rum->id, 'quantity' => '45'], ['inventory_item_id' => $this->cola->id, 'quantity' => '1']]);

        $this->session = app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]), $this->owner);
    }

    private function stockIn(InventoryItem $item, string $quantity, string $cost): void
    {
        app(InventoryService::class)->receive($this->owner, ['received_at' => now()->toIso8601String(),
            'lines' => [['inventory_item_id' => $item->id, 'quantity' => $quantity, 'line_cost' => $cost]]]);
    }

    private function order(array $lines): Order
    {
        return app(OrderService::class)->addOrder($this->session, $this->owner, $lines);
    }

    private function line(Product $product, int $quantity, bool $bucket = false): array
    {
        return ['product_id' => $product->id, 'variant_id' => $bucket ? $product->variants()->value('id') : null, 'quantity' => $quantity];
    }

    private function onHand(InventoryItem $item): string
    {
        return $item->fresh()->on_hand;
    }

    private function assertLedgerMatchesCache(): void
    {
        foreach ([$this->beer, $this->rum, $this->cola] as $item) {
            $this->assertSame(0, bccomp((string) InventoryMovement::where('inventory_item_id', $item->id)->sum('quantity'), $this->onHand($item), 3), $item->name);
        }
    }

    public function test_a_sale_deducts_its_recipe_and_snapshots_the_cost(): void
    {
        $order = $this->order([$this->line($this->redHorse, 2), $this->line($this->redHorse, 1, bucket: true), $this->line($this->rumCoke, 3)]);

        $this->assertSame(['16.000', '615.000', '7.000'], [$this->onHand($this->beer), $this->onHand($this->rum), $this->onHand($this->cola)]); // 24 − 2 − 6, 750 − 135, 10 − 3
        [$bottle, $bucket, $cocktail] = $order->items()->orderBy('id')->get()->all();
        $this->assertSame(['30.00', '180.00', '85.00'], [$bottle->unit_cost, $bucket->unit_cost, $cocktail->unit_cost]); // 45 × 1.333333 + 25 = 84.999… → 85.00

        $sale = InventoryMovement::where('type', 'sale')->where('source_id', $cocktail->id)->where('inventory_item_id', $this->rum->id)->sole();
        $this->assertSame(['-135.000', '1.333333', '180.00', '615.000', $cocktail->getMorphClass()], [$sale->quantity, $sale->unit_cost, $sale->total_cost, $sale->balance_after, $sale->source_type]);
        $this->assertSame(4, InventoryMovement::where('type', 'sale')->count());
        $this->assertEquals([['inventory_item_id' => $this->rum->id, 'quantity' => '45.000', 'unit_cost' => '1.333333'], ['inventory_item_id' => $this->cola->id, 'quantity' => '1.000', 'unit_cost' => '25.000000']], $cocktail->stock_components);
        $this->assertLedgerMatchesCache();
    }

    public function test_untracked_products_and_options_without_a_recipe_keep_their_manual_cost(): void
    {
        $sisig = app(ProductService::class)->create(['category_id' => $this->redHorse->category_id, 'name' => 'Sisig', 'type' => 'food', 'price' => '220', 'cost' => '90']);
        $rumCokeDouble = app(ProductService::class)->update($this->rumCoke, ['variants' => [['name' => 'Double', 'price' => '180', 'cost' => '95']]]);

        $order = $this->order([['product_id' => $sisig->id, 'quantity' => 1], ['product_id' => $this->rumCoke->id, 'variant_id' => $rumCokeDouble->variants()->value('id'), 'quantity' => 1]]);

        $this->assertSame(['90.00', '95.00'], $order->items()->orderBy('id')->pluck('unit_cost')->all());
        $this->assertSame([null, null], $order->items()->orderBy('id')->pluck('stock_components')->all());
        $this->assertSame(0, InventoryMovement::where('type', 'sale')->count());
    }

    public function test_selling_more_than_is_on_hand_is_refused_and_rolls_back_the_whole_order(): void
    {
        try {
            $this->order([$this->line($this->rumCoke, 1), $this->line($this->redHorse, 5, bucket: true)]); // 30 bottles needed, 24 on hand
            $this->fail('oversold');
        } catch (ValidationException $e) {
            $this->assertSame('Only 24 bottle of Red Horse 500ml left.', $e->errors()['lines.1.quantity'][0]);
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, InventoryMovement::where('type', 'sale')->count()); // the Rum Coke's deduction rolled back too
        $this->assertSame(['24.000', '750.000', '10.000'], [$this->onHand($this->beer), $this->onHand($this->rum), $this->onHand($this->cola)]);
    }

    public function test_with_negative_stock_allowed_the_sale_goes_through_and_is_flagged_once(): void
    {
        app(SettingService::class)->set(['allow_negative_stock' => true]);

        $this->order([$this->line($this->redHorse, 5, bucket: true)]); // 24 − 30
        $this->order([$this->line($this->redHorse, 1)]); // already below zero: no second flag

        $this->assertSame('-7.000', $this->onHand($this->beer));
        $this->assertSame('negative_stock', $this->beer->fresh()->stockStatus());
        $log = ActivityLog::where('action', 'stock.went_negative')->sole();
        $this->assertEquals(['item' => 'Red Horse 500ml', 'on_hand' => '-6.000'], $log->new_values);
        $this->assertLedgerMatchesCache();
    }

    public function test_raising_a_quantity_deducts_the_extra_units_and_checks_stock_again(): void
    {
        $order = $this->order([$this->line($this->redHorse, 2)]);
        $line = $order->items->sole();

        app(OrderService::class)->updateItem($line, $this->owner, ['quantity' => 5]);
        $this->assertSame('19.000', $this->onHand($this->beer));
        $this->assertSame('30.00', $line->fresh()->unit_cost);

        try {
            app(OrderService::class)->updateItem($line, $this->owner, ['quantity' => 30]);
            $this->fail('oversold on a quantity raise');
        } catch (ValidationException $e) {
            $this->assertSame('Only 19 bottle of Red Horse 500ml left.', $e->errors()['quantity'][0]);
        }
        $this->assertSame(5, $line->fresh()->quantity);
        $this->assertLedgerMatchesCache();
    }
}

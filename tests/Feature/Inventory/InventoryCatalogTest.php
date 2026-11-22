<?php

namespace Tests\Feature\Inventory;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\RecipeComponent;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\RecipeService;
use App\Services\Inventory\SupplierService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItemService $items;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole('Owner'));
        $this->items = app(InventoryItemService::class);
    }

    private function assertRefused(callable $do, string $key, ?string $message = null): void
    {
        try {
            $do();
            $this->fail("expected a refusal on {$key}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), json_encode($e->errors()));
            if ($message !== null) {
                $this->assertSame($message, $e->errors()[$key][0]);
            }
        }
    }

    public function test_suppliers_are_created_updated_and_archived_with_logs(): void
    {
        $service = app(SupplierService::class);
        $supplier = $service->create(['name' => 'San Miguel Brewery', 'phone' => '0917 000 0000']);
        $service->update($supplier, ['name' => 'San Miguel Brewery', 'phone' => '0917 111 1111']);
        $service->archive($supplier);
        $service->restore($supplier);

        $this->assertSame(['supplier.created', 'supplier.updated', 'supplier.archived', 'supplier.restored'],
            ActivityLog::where('action', 'like', 'supplier.%')->orderBy('id')->pluck('action')->all());
        $this->assertSame(['phone' => '0917 000 0000'], ActivityLog::where('action', 'supplier.updated')->sole()->old_values);
    }

    public function test_items_never_take_stock_or_cost_from_input(): void
    {
        $item = $this->items->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle', 'low_stock_level' => '24', 'on_hand' => '999', 'avg_unit_cost' => '5']);

        $this->assertSame(['0.000', '0.000000', '24.000'], [$item->fresh()->on_hand, $item->fresh()->avg_unit_cost, $item->fresh()->low_stock_level]);
        $this->assertEquals(['name' => 'Red Horse 500ml', 'sku' => null, 'base_unit' => 'bottle', 'low_stock_level' => '24.000', 'category_id' => null],
            ActivityLog::where('action', 'inventory_item.created')->sole()->new_values);
    }

    public function test_units_are_replaced_as_a_set_and_validated(): void
    {
        $item = $this->items->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle']);

        $this->items->saveUnits($item, [['name' => 'case', 'factor' => '24'], ['name' => 'half case', 'factor' => '12']]);
        $this->assertSame(['half case' => '12.000', 'case' => '24.000'], $item->units()->pluck('factor', 'name')->all());

        $case = $item->units()->where('name', 'case')->sole();
        $this->items->saveUnits($item, [['id' => $case->id, 'name' => 'case', 'factor' => '24']]);
        $this->assertSame(['case'], $item->fresh()->units->pluck('name')->all());
        $this->assertSame(2, ActivityLog::where('action', 'inventory_item.units_changed')->count());

        $this->assertRefused(fn () => $this->items->saveUnits($item, [['name' => 'Bottle', 'factor' => '1']]), 'units.0.name', 'That is the base unit already.');
        $this->assertRefused(fn () => $this->items->saveUnits($item, [['name' => 'crate', 'factor' => '0']]), 'units.0.factor');
        $this->assertRefused(fn () => $this->items->saveUnits($item, [['name' => 'case', 'factor' => '24'], ['name' => 'CASE', 'factor' => '12']]), 'units.1.name', 'Each unit needs its own name.');
    }

    public function test_the_base_unit_is_fixed_once_the_item_has_movements(): void
    {
        $item = $this->items->create(['name' => 'Emperador', 'base_unit' => 'bottle']);
        $item = $this->items->update($item, ['base_unit' => 'ml']); // no stock yet: allowed
        $this->assertSame('ml', $item->base_unit);

        \App\Models\InventoryMovement::forceCreate(['inventory_item_id' => $item->id, 'type' => 'receipt', 'quantity' => '750', 'unit_cost' => '1', 'total_cost' => '750', 'balance_after' => '750', 'business_date' => '2026-11-19', 'created_by' => auth()->id()]);
        $this->assertRefused(fn () => $this->items->update($item, ['base_unit' => 'bottle']), 'base_unit', 'This item already has stock history. Its base unit cannot change.');
    }

    public function test_the_base_unit_is_also_fixed_once_units_or_recipes_count_in_it(): void
    {
        $item = $this->items->saveUnits($this->items->create(['name' => 'Red Horse', 'base_unit' => 'bottle']), [['name' => 'case', 'factor' => '24']]);

        $this->assertRefused(fn () => $this->items->update($item, ['base_unit' => 'ml']), 'base_unit', 'Units or recipes are counted in bottle. Remove them first, or create a new item.');
        $this->assertRefused(fn () => $this->items->update($item, ['base_unit' => 'Case']), 'base_unit', 'That is the name of one of its units.');
    }

    public function test_unit_names_can_be_swapped_in_one_save(): void
    {
        $item = $this->items->saveUnits($this->items->create(['name' => 'Red Horse', 'base_unit' => 'bottle']), [['name' => 'case', 'factor' => '24'], ['name' => 'pack', 'factor' => '6']]);
        [$case, $pack] = [$item->units()->where('name', 'case')->sole(), $item->units()->where('name', 'pack')->sole()];

        $this->items->saveUnits($item, [['id' => $case->id, 'name' => 'pack', 'factor' => '24'], ['id' => $pack->id, 'name' => 'case', 'factor' => '6']]);

        $this->assertSame(['case' => '6.000', 'pack' => '24.000'], $item->units()->orderBy('name')->pluck('factor', 'name')->all());
    }

    public function test_a_recipe_is_saved_per_product_option_and_replaced_as_a_set(): void
    {
        $product = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Drinks'])->id, 'name' => 'Rum Coke', 'type' => 'drink', 'price' => '120',
            'variants' => [['name' => 'Double', 'price' => '180']]]);
        $double = $product->variants()->sole();
        $rum = $this->items->create(['name' => 'Tanduay Rum', 'base_unit' => 'ml']);
        $cola = $this->items->create(['name' => 'Coke can', 'base_unit' => 'pc']);
        $recipes = app(RecipeService::class);

        $recipes->save($product, null, [['inventory_item_id' => $rum->id, 'quantity' => '45'], ['inventory_item_id' => $cola->id, 'quantity' => '1']]);
        $recipes->save($product, $double, [['inventory_item_id' => $rum->id, 'quantity' => '90'], ['inventory_item_id' => $cola->id, 'quantity' => '1']]);
        $recipes->save($product, null, [['inventory_item_id' => $rum->id, 'quantity' => '60']]);

        $this->assertSame(['60.000'], RecipeComponent::where('product_id', $product->id)->whereNull('product_variant_id')->pluck('quantity')->all());
        $this->assertSame(2, RecipeComponent::where('product_variant_id', $double->id)->count());
        $log = ActivityLog::where('action', 'product.recipe_changed')->latest('id')->first();
        $this->assertEquals(['Tanduay Rum' => '45.000', 'Coke can' => '1.000'], $log->old_values['components']);
        $this->assertEquals(['Tanduay Rum' => '60.000'], $log->new_values['components']);
    }

    public function test_recipe_rules(): void
    {
        $product = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Drinks'])->id, 'name' => 'Rum Coke', 'type' => 'drink', 'price' => '120']);
        $other = app(ProductService::class)->create(['category_id' => $product->category_id, 'name' => 'Beer', 'type' => 'drink', 'price' => '85', 'variants' => [['name' => 'Bucket', 'price' => '480']]]);
        $rum = $this->items->create(['name' => 'Tanduay Rum', 'base_unit' => 'ml']);
        $recipes = app(RecipeService::class);

        $this->assertRefused(fn () => $recipes->save($product, $other->variants()->sole(), []), 'product_variant_id', 'That is not an option of Rum Coke.');
        $this->assertRefused(fn () => $recipes->save($product, null, [['inventory_item_id' => $rum->id, 'quantity' => '0']]), 'components.0.quantity');
        $this->assertRefused(fn () => $recipes->save($product, null, [['inventory_item_id' => $rum->id, 'quantity' => '45'], ['inventory_item_id' => $rum->id, 'quantity' => '10']]), 'components.1.inventory_item_id', 'Tanduay Rum is listed twice.');
        $this->items->archive($rum);
        $this->assertRefused(fn () => $recipes->save($product, null, [['inventory_item_id' => $rum->id, 'quantity' => '45']]), 'components.0.inventory_item_id', 'Choose an inventory item that is in use.');
    }
}

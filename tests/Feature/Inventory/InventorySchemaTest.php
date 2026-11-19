<?php

namespace Tests\Feature\Inventory;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\RecipeComponent;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryItem $beer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->beer = InventoryItem::create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle', 'low_stock_level' => '24']);
    }

    private function assertRejectedByDatabase(callable $write): void
    {
        DB::beginTransaction(); // a failed statement aborts the transaction; keep the test's own one usable
        try {
            $write();
            $this->fail('the database accepted the write');
        } catch (QueryException) {
            $this->assertTrue(true);
        } finally {
            DB::rollBack();
        }
    }

    public function test_new_items_start_empty_and_names_are_unique_ignoring_case(): void
    {
        $this->assertSame(['0.000', '0.000000'], [$this->beer->fresh()->on_hand, $this->beer->fresh()->avg_unit_cost]);
        $this->assertRejectedByDatabase(fn () => InventoryItem::create(['name' => 'red horse 500ML', 'base_unit' => 'bottle']));
        $this->assertRejectedByDatabase(fn () => Supplier::insert([['name' => 'A', 'created_at' => now(), 'updated_at' => now()], ['name' => 'a', 'created_at' => now(), 'updated_at' => now()]]));
    }

    public function test_unit_factors_and_recipe_quantities_must_be_positive_and_unique(): void
    {
        InventoryUnit::create(['inventory_item_id' => $this->beer->id, 'name' => 'case', 'factor' => '24']);
        $this->assertRejectedByDatabase(fn () => InventoryUnit::create(['inventory_item_id' => $this->beer->id, 'name' => 'case', 'factor' => '12']));
        $this->assertRejectedByDatabase(fn () => InventoryUnit::create(['inventory_item_id' => $this->beer->id, 'name' => 'crate', 'factor' => '0']));

        $product = Product::create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85']);
        RecipeComponent::create(['product_id' => $product->id, 'inventory_item_id' => $this->beer->id, 'quantity' => '1']);
        $this->assertRejectedByDatabase(fn () => RecipeComponent::create(['product_id' => $product->id, 'inventory_item_id' => $this->beer->id, 'quantity' => '2']));
        $this->assertRejectedByDatabase(fn () => RecipeComponent::create(['product_id' => $product->id, 'product_variant_id' => null, 'inventory_item_id' => InventoryItem::create(['name' => 'Ice', 'base_unit' => 'kg'])->id, 'quantity' => '0']));
    }

    public function test_the_ledger_and_receipts_are_append_only(): void
    {
        $receipt = StockReceipt::forceCreate(['reference' => 'SR-20261119-0001', 'received_at' => now(), 'business_date' => '2026-11-19', 'received_by' => $this->owner->id, 'total_cost' => '0.00']);
        $movement = InventoryMovement::forceCreate(['inventory_item_id' => $this->beer->id, 'type' => 'receipt', 'quantity' => '1', 'unit_cost' => '1', 'total_cost' => '1', 'balance_after' => '1', 'business_date' => '2026-11-19', 'created_by' => $this->owner->id]);

        foreach ([$receipt, $movement] as $record) {
            try {
                $record->delete();
                $this->fail(get_class($record).' allowed a delete');
            } catch (\LogicException) {
            }
        }
        $this->assertRejectedByDatabase(fn () => DB::table('inventory_movements')->where('id', $movement->id)->update(['quantity' => 5]));
        $this->assertRejectedByDatabase(fn () => DB::table('stock_receipts')->where('id', $receipt->id)->delete());
    }

    public function test_suppliers_and_items_archive_and_restore_with_logs(): void
    {
        $supplier = Supplier::create(['name' => 'San Miguel Brewery']);
        $supplier->delete();
        $supplier->restore();
        $this->beer->delete();

        $this->assertSame(['supplier.archived', 'supplier.restored', 'inventory_item.archived'],
            ActivityLog::whereIn('action', ['supplier.archived', 'supplier.restored', 'inventory_item.archived'])->orderBy('id')->pluck('action')->all());
        $this->expectException(\LogicException::class);
        $this->beer->forceDelete();
    }
}

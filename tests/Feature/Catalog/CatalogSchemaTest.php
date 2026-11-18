<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const SPEC_CATEGORIES = ['Beer', 'Liquor', 'Cocktails', 'Wine', 'Soft Drinks', 'Water', 'Food and Snacks', 'Other Products'];

    public function test_category_seeder_adds_the_spec_categories_once_in_order(): void
    {
        $this->seed(CategorySeeder::class);
        Category::where('name', 'Beer')->sole()->update(['sort_order' => 99]); // owner edit survives a re-seed
        $this->seed(CategorySeeder::class);

        $this->assertSame(8, Category::count());
        $this->assertSame(self::SPEC_CATEGORIES, Category::orderBy('id')->pluck('name')->all());
        $this->assertSame(99, Category::where('name', 'Beer')->sole()->sort_order);
    }

    public function test_margin_is_amount_and_percent_of_price(): void
    {
        $category = Category::create(['name' => 'Beer']);
        $product = fn (array $attrs) => new Product(['category_id' => $category->id, 'name' => 'X', 'type' => 'drink', ...$attrs]);

        $this->assertSame(['amount' => '75.00', 'percent' => '62.50'], $product(['price' => '120.00', 'cost' => '45.00'])->margin());
        $this->assertSame(['amount' => '2.00', 'percent' => '66.67'], $product(['price' => '3.00', 'cost' => '1.00'])->margin());
        $this->assertSame(['amount' => null, 'percent' => null], $product(['price' => '120.00', 'cost' => null])->margin());
        $this->assertSame(['amount' => '-10.00', 'percent' => null], $product(['price' => '0.00', 'cost' => '10.00'])->margin());
        $this->assertSame(['amount' => '150.00', 'percent' => '55.56'], (new ProductVariant(['price' => '270.00', 'cost' => '120.00']))->margin());
    }

    public function test_money_columns_come_back_as_two_decimal_strings(): void
    {
        $category = Category::create(['name' => 'Beer']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.5']);

        $fresh = $product->fresh();
        $this->assertSame('85.00', $fresh->price);
        $this->assertSame('52.50', $fresh->cost);
        $this->assertTrue($fresh->is_available);
        $this->assertFalse($fresh->track_inventory);
    }

    public function test_price_history_is_append_only_in_the_model_and_the_database(): void
    {
        $category = Category::create(['name' => 'Beer']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85.00']);
        $row = ProductPriceHistory::create(['product_id' => $product->id, 'field' => 'price', 'old_value' => null, 'new_value' => '85.00']);

        foreach ([fn () => $row->update(['new_value' => '1.00']), fn () => $row->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('model allowed a change');
            } catch (LogicException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }

        try {
            DB::transaction(fn () => DB::table('product_price_histories')->update(['new_value' => '1.00']));
            $this->fail('database allowed a change');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame('85.00', $row->fresh()->new_value);
    }
}

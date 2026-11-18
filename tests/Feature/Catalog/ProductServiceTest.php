<?php

namespace Tests\Feature\Catalog;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private Category $beer;

    private ProductService $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->beer = Category::create(['name' => 'Beer']);
        $this->products = app(ProductService::class);
    }

    private function redHorse(array $overrides = []): Product
    {
        return $this->products->create([
            'category_id' => $this->beer->id, 'name' => 'Red Horse', 'type' => 'drink',
            'price' => '85', 'cost' => '52.50', ...$overrides,
        ]);
    }

    private function history(Product $product): array
    {
        return $product->priceHistory()->reorder('id')->get()
            ->map(fn ($h) => [$h->product_variant_id ? 'variant' : 'product', $h->field, $h->old_value, $h->new_value])->all();
    }

    public function test_creating_a_product_records_initial_prices_and_logs(): void
    {
        $product = $this->redHorse();

        $this->assertSame([['product', 'price', null, '85.00'], ['product', 'cost', null, '52.50']], $this->history($product));
        $this->assertSame(auth()->id(), ProductPriceHistory::first()->changed_by);
        $log = ActivityLog::where('action', 'product.created')->sole();
        $this->assertSame($product->id, $log->subject_id);
        $this->assertSame('85.00', $log->new_values['price']);
    }

    public function test_price_change_writes_history_and_log_without_touching_earlier_records(): void
    {
        $product = $this->redHorse();
        $historyBefore = ProductPriceHistory::orderBy('id')->get()->toArray();
        $logsBefore = ActivityLog::orderBy('id')->get()->toArray();

        $this->products->update($product, ['price' => '90.00', 'cost' => '52.50']);

        $this->assertSame('90.00', $product->fresh()->price);
        $this->assertSame([['product', 'price', null, '85.00'], ['product', 'cost', null, '52.50'], ['product', 'price', '85.00', '90.00']], $this->history($product));
        $this->assertSame($historyBefore, ProductPriceHistory::orderBy('id')->limit(2)->get()->toArray());
        $this->assertSame($logsBefore, ActivityLog::orderBy('id')->limit(count($logsBefore))->get()->toArray());

        $log = ActivityLog::where('action', 'product.price_changed')->sole();
        $this->assertSame(['price' => '85.00'], $log->old_values);
        $this->assertSame(['price' => '90.00'], $log->new_values);
    }

    public function test_same_price_in_another_format_and_non_price_edits_write_no_history(): void
    {
        $product = $this->redHorse();

        $this->products->update($product, ['price' => '85', 'cost' => '52.5', 'name' => 'Red Horse 500ml']);

        $this->assertCount(2, $this->history($product));
        $this->assertSame(0, ActivityLog::where('action', 'product.price_changed')->count());
        $log = ActivityLog::where('action', 'product.updated')->sole();
        $this->assertSame(['name' => 'Red Horse'], $log->old_values);
        $this->assertSame(['name' => 'Red Horse 500ml'], $log->new_values);
    }

    public function test_variants_are_added_repriced_and_archived(): void
    {
        $product = $this->redHorse(['variants' => [
            ['name' => 'Bucket of 6', 'price' => '480', 'cost' => '315'],
            ['name' => 'Tower', 'price' => '900', 'cost' => null],
        ]]);
        [$bucket, $tower] = $product->variants;

        $this->products->update($product, ['variants' => [
            ['id' => $bucket->id, 'name' => 'Bucket of 6', 'price' => '500', 'cost' => '315'],
            ['name' => 'Bucket of 3', 'price' => '250', 'cost' => '160'],
        ]]);

        $product->refresh();
        $this->assertSame(['Bucket of 6', 'Bucket of 3'], $product->variants->pluck('name')->all());
        $this->assertSame('500.00', $bucket->fresh()->price);
        $this->assertSoftDeleted($tower);
        $this->assertSame(1, ActivityLog::where('action', 'product_variant.archived')->where('subject_id', $tower->id)->count());

        $bucketHistory = ProductPriceHistory::where('product_variant_id', $bucket->id)->orderBy('id')->get(['field', 'old_value', 'new_value'])->toArray();
        $this->assertSame([
            ['field' => 'price', 'old_value' => null, 'new_value' => '480.00'],
            ['field' => 'cost', 'old_value' => null, 'new_value' => '315.00'],
            ['field' => 'price', 'old_value' => '480.00', 'new_value' => '500.00'],
        ], $bucketHistory);
        $this->assertSame(1, ActivityLog::where('action', 'product.price_changed')->where('subject_id', $bucket->id)->count());
    }

    public function test_a_variant_id_from_another_product_is_rejected(): void
    {
        $other = $this->redHorse(['name' => 'San Mig', 'variants' => [['name' => 'Bucket', 'price' => '400']]]);
        $product = $this->redHorse();

        $this->expectException(ValidationException::class);
        $this->products->update($product, ['variants' => [['id' => $other->variants->first()->id, 'name' => 'Stolen', 'price' => '1']]]);
    }

    public function test_category_with_active_products_cannot_be_archived(): void
    {
        $categories = app(CategoryService::class);
        $product = $this->redHorse();

        try {
            $categories->archive($this->beer);
            $this->fail('archived a category that still has products');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('1 active product', $e->errors()['category'][0]);
        }

        $this->products->archive($product);
        $categories->archive($this->beer->fresh());
        $this->assertSoftDeleted($this->beer);

        $categories->restore(Category::withTrashed()->find($this->beer->id));
        $this->assertNotSoftDeleted($this->beer);
    }

    public function test_sellable_hides_archived_unavailable_and_archived_category_products(): void
    {
        $water = Category::create(['name' => 'Water']);
        $sold = $this->redHorse();
        $off = $this->redHorse(['name' => 'Off menu']);
        $archived = $this->redHorse(['name' => 'Old']);
        $orphan = $this->redHorse(['name' => 'Still water', 'category_id' => $water->id]);

        $this->products->setAvailability($off, false);
        $this->products->archive($archived);
        $this->products->archive($orphan);
        app(CategoryService::class)->archive($water->fresh());
        $this->products->restore(Product::withTrashed()->find($orphan->id)); // product active, category archived

        $this->assertSame([$sold->id], Product::sellable()->pluck('id')->all());
        $this->assertSame(1, ActivityLog::where('action', 'product.archived')->where('subject_id', $archived->id)->count());
        $this->assertSame(['is_available' => false], ActivityLog::where('action', 'product.updated')->where('subject_id', $off->id)->sole()->new_values);
    }

    public function test_image_is_stored_and_replacing_or_removing_it_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $product = $this->products->create(
            ['category_id' => $this->beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85'],
            UploadedFile::fake()->image('red.png', 200, 200),
        );
        $first = $product->image_path;
        Storage::disk('public')->assertExists($first);

        $this->products->update($product, [], UploadedFile::fake()->image('new.jpg'));
        $second = $product->fresh()->image_path;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->products->update($product->fresh(), [], removeImage: true);
        $this->assertNull($product->fresh()->image_path);
        Storage::disk('public')->assertMissing($second);
    }
}

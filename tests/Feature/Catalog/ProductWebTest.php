<?php

namespace Tests\Feature\Catalog;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\ProductService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductWebTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Category $beer;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->beer = Category::create(['name' => 'Beer', 'sort_order' => 10]);
        $this->food = Category::create(['name' => 'Food and Snacks', 'sort_order' => 20]);
    }

    private function make(string $name, Category $category, string $price = '85', ?string $cost = '50'): Product
    {
        $this->actingAs($this->owner);

        return app(ProductService::class)->create(['category_id' => $category->id, 'name' => $name, 'type' => 'drink', 'price' => $price, 'cost' => $cost]);
    }

    private function rows($response): array
    {
        return $response->viewData('products')->getCollection()->pluck('name')->all();
    }

    private function form(array $overrides = []): array
    {
        return [
            'category_id' => $this->beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'description' => '',
            'price' => '85.00', 'cost' => '52.50', 'is_available' => '1', 'track_inventory' => '0', 'variants_sent' => '1',
            ...$overrides,
        ];
    }

    public function test_owner_lists_searches_and_filters_products(): void
    {
        $this->make('Red Horse', $this->beer);
        $this->make('San Mig Light', $this->beer);
        $this->make('Sisig', $this->food);
        $archived = $this->make('Old Brew', $this->beer);
        app(ProductService::class)->archive($archived);

        $this->actingAs($this->owner);
        $this->assertSame(['Red Horse', 'San Mig Light', 'Sisig'], $this->rows($this->get('/products')->assertOk()->assertSee('₱85.00')));
        $this->assertSame(['San Mig Light'], $this->rows($this->get('/products?q=mig')));
        $this->assertSame(['Sisig'], $this->rows($this->get("/products?category_id={$this->food->id}")));
        $this->assertSame(['Old Brew'], $this->rows($this->get('/products?status=archived')));
    }

    public function test_owner_creates_a_product_with_options_and_an_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->post('/products', $this->form([
            'image' => UploadedFile::fake()->image('red.png'),
            'variants' => [['id' => '', 'name' => 'Bucket of 6', 'price' => '480', 'cost' => '']],
        ]))->assertRedirect('/products')->assertSessionHas('status');

        $product = Product::with('variants')->sole();
        $this->assertSame('52.50', $product->cost);
        $this->assertSame(['Bucket of 6'], $product->variants->pluck('name')->all());
        $this->assertNull($product->variants->first()->cost);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_editing_the_price_records_history_shown_on_the_edit_page(): void
    {
        $product = $this->make('Red Horse', $this->beer, '85', '52.50');

        $this->actingAs($this->owner)->put("/products/{$product->id}", $this->form(['price' => '90', 'cost' => '']))
            ->assertRedirect('/products');

        $this->assertSame('90.00', $product->fresh()->price);
        $this->assertNull($product->fresh()->cost);
        $this->actingAs($this->owner)->get("/products/{$product->id}/edit")
            ->assertOk()
            ->assertSeeInOrder(['Price history', '₱85.00', '₱90.00']);
    }

    public function test_removing_every_option_row_archives_the_options(): void
    {
        $this->actingAs($this->owner);
        $product = app(ProductService::class)->create([
            'category_id' => $this->beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85',
            'variants' => [['name' => 'Bucket of 6', 'price' => '480']],
        ]);

        $this->put("/products/{$product->id}", $this->form())->assertRedirect('/products');

        $this->assertCount(0, $product->fresh()->variants);
    }

    public function test_availability_toggle_archive_and_restore(): void
    {
        $product = $this->make('Red Horse', $this->beer);
        $this->actingAs($this->owner);

        $this->patch("/products/{$product->id}/availability")->assertRedirect();
        $this->assertFalse($product->fresh()->is_available);

        $this->delete("/products/{$product->id}")->assertRedirect('/products');
        $this->assertSoftDeleted($product);

        $this->post("/products/{$product->id}/restore")->assertRedirect('/products');
        $this->assertNotSoftDeleted($product);
        $this->assertSame(1, ActivityLog::where('action', 'product.restored')->count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post('/products', $this->form(['price' => '-1', 'cost' => '1.234', 'type' => 'spaceship', 'category_id' => 999999,
                'variants' => [['name' => 'Big', 'price' => '1'], ['name' => 'big', 'price' => '2']]]))
            ->assertSessionHasErrors(['price', 'cost', 'type', 'category_id', 'variants.1.name']);

        $this->assertSame(0, Product::count());
    }

    public function test_categories_can_be_created_renamed_and_archive_is_refused_while_in_use(): void
    {
        $this->make('Red Horse', $this->beer);
        $this->actingAs($this->owner);

        $this->post('/categories', ['name' => 'Cocktails', 'sort_order' => 30])->assertRedirect('/categories');
        $this->post('/categories', ['name' => 'beer'])->assertSessionHasErrors('name');
        $this->put("/categories/{$this->food->id}", ['name' => 'Bar Chow', 'sort_order' => 20])->assertRedirect('/categories');
        $this->assertSame('Bar Chow', $this->food->fresh()->name);

        $this->from('/categories')->delete("/categories/{$this->beer->id}")
            ->assertRedirect('/categories')
            ->assertSessionHasErrors('category');
        $this->assertNotSoftDeleted($this->beer);

        $this->delete("/categories/{$this->food->id}")->assertRedirect('/categories');
        $this->assertSoftDeleted($this->food);
    }

    public function test_cashier_cannot_reach_catalogue_management(): void
    {
        $cashier = User::factory()->create()->assignRole('Cashier');
        $product = $this->make('Red Horse', $this->beer);

        $this->actingAs($cashier);
        $this->get('/products')->assertForbidden();
        $this->get('/products/create')->assertForbidden();
        $this->post('/products', $this->form())->assertForbidden();
        $this->patch("/products/{$product->id}/availability")->assertForbidden();
        $this->delete("/products/{$product->id}")->assertForbidden();
        $this->get('/categories')->assertForbidden();
    }

    public function test_view_only_users_see_prices_but_not_costs_and_cannot_edit(): void
    {
        Role::findByName('Bartender')->givePermissionTo('products.view');
        $bartender = User::factory()->create()->assignRole('Bartender');
        $this->make('Red Horse', $this->beer, '85', '52.50');

        $this->actingAs($bartender)->get('/products')
            ->assertOk()
            ->assertSee('₱85.00')
            ->assertDontSee('₱52.50')
            ->assertDontSee('Margin');
        $this->actingAs($bartender)->get('/products/create')->assertForbidden();
    }
}

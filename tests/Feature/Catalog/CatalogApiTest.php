<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\ProductService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Category $beer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->beer = Category::create(['name' => 'Beer']);
    }

    private function make(array $data = []): Product
    {
        $this->actingAs($this->owner);

        return app(ProductService::class)->create(['category_id' => $this->beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.5', ...$data]);
    }

    public function test_owner_manages_products_through_the_api(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $id = $this->postJson('/api/v1/products', [
            'category_id' => $this->beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => 85, 'cost' => 52.5,
            'variants' => [['name' => 'Bucket of 6', 'price' => 480, 'cost' => 315]],
        ])->assertCreated()
            ->assertJsonPath('data.price', '85.00')
            ->assertJsonPath('data.margin', ['amount' => '32.50', 'percent' => '38.24'])
            ->assertJsonPath('data.category.name', 'Beer')
            ->assertJsonPath('data.variants.0.margin.amount', '165.00')
            ->json('data.id');

        $this->putJson("/api/v1/products/{$id}", ['price' => '90'])->assertOk()->assertJsonPath('data.price', '90.00');
        $this->assertSame(['85.00', '90.00'], ProductPriceHistory::where('product_id', $id)->whereNull('product_variant_id')->where('field', 'price')->orderBy('id')->pluck('new_value')->all());

        $this->getJson("/api/v1/products/{$id}")->assertOk()->assertJsonPath('data.name', 'Red Horse');
        $this->deleteJson("/api/v1/products/{$id}")->assertOk()->assertJsonPath('data.archived', true);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?status=archived')->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/products/{$id}/restore")->assertOk()->assertJsonPath('data.archived', false);
    }

    public function test_owner_manages_categories_through_the_api(): void
    {
        $this->make();
        $this->actingAs($this->owner, 'sanctum');

        $id = $this->postJson('/api/v1/categories', ['name' => 'Cocktails', 'sort_order' => 30])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/categories/{$id}", ['name' => 'Mixed drinks'])->assertOk()->assertJsonPath('data.name', 'Mixed drinks');
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(2, 'data');

        $this->deleteJson("/api/v1/categories/{$this->beer->id}")->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->deleteJson("/api/v1/categories/{$id}")->assertOk();
        $this->assertSoftDeleted('categories', ['id' => $id]);
        $this->postJson("/api/v1/categories/{$id}/restore")->assertOk();
    }

    public function test_validation_errors_come_back_as_422(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v1/products', ['category_id' => $this->beer->id, 'name' => '', 'type' => 'drink', 'price' => -5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price']);
    }

    public function test_costs_are_hidden_from_view_only_users(): void
    {
        $this->make();
        Role::findByName('Bartender')->givePermissionTo('products.view');
        $bartender = User::factory()->create()->assignRole('Bartender');

        $data = $this->actingAs($bartender, 'sanctum')->getJson('/api/v1/products')->assertOk()->json('data.0');
        $this->assertSame('85.00', $data['price']);
        $this->assertArrayNotHasKey('cost', $data);
        $this->assertArrayNotHasKey('margin', $data);
        $this->postJson('/api/v1/products', [])->assertForbidden();
    }

    public function test_menu_lists_only_what_the_pos_may_sell(): void
    {
        $water = Category::create(['name' => 'Water']);
        $redHorse = $this->make(['variants' => [['name' => 'Bucket of 6', 'price' => '480'], ['name' => 'Tower', 'price' => '900']]]);
        $off = $this->make(['name' => 'Off menu']);
        $archived = $this->make(['name' => 'Old brew']);
        $inArchivedCategory = $this->make(['name' => 'Still water', 'category_id' => $water->id]);

        $products = app(ProductService::class);
        $products->setAvailability($off, false);
        $products->archive($archived);
        $products->update($redHorse, ['variants' => [['id' => $redHorse->variants[0]->id, 'name' => 'Bucket of 6', 'price' => '480']]]); // archives Tower
        $products->archive($inArchivedCategory);
        app(CategoryService::class)->archive($water->fresh());
        $products->restore(Product::withTrashed()->find($inArchivedCategory->id));

        $cashier = User::factory()->create()->assignRole('Cashier');
        $menu = $this->actingAs($cashier, 'sanctum')->getJson('/api/v1/menu')->assertOk()->json('data');

        $this->assertSame(['Beer'], array_column($menu, 'name'));
        $this->assertSame(['Red Horse'], array_column($menu[0]['products'], 'name'));
        $this->assertSame([['id' => $redHorse->variants[0]->id, 'name' => 'Bucket of 6', 'price' => '480.00']], $menu[0]['products'][0]['variants']);
        $this->assertArrayNotHasKey('cost', $menu[0]['products'][0]);
    }

    public function test_permissions_are_enforced(): void
    {
        $product = $this->make();
        $cashier = User::factory()->create()->assignRole('Cashier');

        $this->actingAs($cashier, 'sanctum');
        $this->getJson('/api/v1/products')->assertForbidden();
        $this->postJson('/api/v1/products', [])->assertForbidden();
        $this->putJson("/api/v1/products/{$product->id}", ['price' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/products/{$product->id}")->assertForbidden();
        $this->postJson('/api/v1/categories', ['name' => 'X'])->assertForbidden();
        $this->assertSame('85.00', $product->fresh()->price);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/menu')->assertUnauthorized();
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }
}

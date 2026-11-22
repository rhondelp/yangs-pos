<?php

namespace Tests\Feature\Checkpoint;

use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Inventory\InventoryItemService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Inventory\Concerns\SellsTrackedProducts;
use Tests\TestCase;

/** Fixes from the Phase 3 checkpoint audit, each pinned by a test that failed first. */
class Phase3AuditTest extends TestCase
{
    use RefreshDatabase, SellsTrackedProducts;

    private User $owner;

    private InventoryItem $gin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->gin = app(InventoryItemService::class)->create(['name' => 'Old Gin', 'base_unit' => 'bottle']);
    }

    // An archived item left in a recipe made every sale of that product fail with "Only 0 bottle of Old Gin left".
    public function test_an_item_used_in_a_recipe_cannot_be_archived(): void
    {
        $product = $this->trackedProduct($this->gin);

        $this->from(route('inventory.show', $this->gin))->delete(route('inventory.archive', $this->gin))
            ->assertRedirect(route('inventory.show', $this->gin))
            ->assertSessionHasErrors(['archive' => "Old Gin is in the recipe of {$product->name}. Remove it from that recipe first."]);
        $this->assertFalse($this->gin->fresh()->trashed());
        $this->deleteJson(route('api.v1.inventory-items.destroy', $this->gin))->assertUnprocessable()->assertJsonValidationErrors('archive');

        app(ProductService::class)->archive($product); // a recipe of an archived product does not block
        $this->delete(route('inventory.archive', $this->gin))->assertSessionHasNoErrors();
        $this->assertTrue($this->gin->fresh()->trashed());
    }
}

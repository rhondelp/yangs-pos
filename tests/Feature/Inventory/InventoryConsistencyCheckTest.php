<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Inventory\Concerns\SellsTrackedProducts;
use Tests\TestCase;

class InventoryConsistencyCheckTest extends TestCase
{
    use RefreshDatabase, SellsTrackedProducts;

    private User $owner;

    private InventoryItem $beer;

    private InventoryItem $gin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $items = app(InventoryItemService::class);
        $inventory = app(InventoryService::class);
        $this->beer = $items->create(['name' => 'Red Horse', 'base_unit' => 'bottle']);
        $this->gin = $items->create(['name' => 'Gin', 'base_unit' => 'bottle']);
        $items->create(['name' => 'Ice', 'base_unit' => 'kg']); // no movements, 0 on hand
        $inventory->receive($this->owner, ['received_at' => now()->toIso8601String(), 'lines' => [
            ['inventory_item_id' => $this->beer->id, 'quantity' => '24', 'line_cost' => '720'],
            ['inventory_item_id' => $this->gin->id, 'quantity' => '2.5', 'line_cost' => '500'],
        ]]);
        $inventory->stockOut($this->gin, $this->owner, ['quantity' => '0.25', 'reason' => 'damaged']);
        $this->sell($this->owner, [[$this->trackedProduct($this->beer), 3]]);
    }

    public function test_every_item_matches_its_ledger(): void
    {
        $this->artisan('inventory:check')
            ->expectsOutputToContain('All 3 items match their stock ledger.')
            ->assertExitCode(0);
    }

    public function test_archived_items_are_checked_too(): void
    {
        app(InventoryItemService::class)->archive($this->gin);
        InventoryItem::withTrashed()->whereKey($this->gin->id)->update(['on_hand' => '9']);

        $this->artisan('inventory:check')->expectsOutputToContain('Gin')->assertExitCode(1);
    }

    public function test_a_mismatch_is_reported_with_both_figures(): void
    {
        $this->beer->forceFill(['on_hand' => '20.5'])->save(); // ledger says 21

        $this->artisan('inventory:check')
            ->expectsOutputToContain('1 of 3 items do not match their stock ledger.')
            ->expectsTable(['ID', 'Item', 'On hand', 'Ledger sum', 'Difference'], [
                [$this->beer->id, 'Red Horse', '20.500', '21.000', '-0.500'],
            ])
            ->assertExitCode(1);
    }
}

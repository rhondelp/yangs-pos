<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Inventory\Concerns\SellsTrackedProducts;
use Tests\TestCase;

class MovementHistoryTest extends TestCase
{
    use RefreshDatabase, SellsTrackedProducts;

    private User $owner;

    private User $manager;

    private InventoryItem $beer; // 48 bottles at ₱30

    private InventoryItem $gin; // 10 bottles at ₱200

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create(['name' => 'Owner Olga'])->assignRole('Owner');
        $this->manager = User::factory()->create(['name' => 'Manager Max'])->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->inventory = app(InventoryService::class);
        $items = app(InventoryItemService::class);
        $this->beer = $items->create(['name' => 'Red Horse', 'base_unit' => 'bottle']);
        $this->gin = $items->create(['name' => 'Gin', 'base_unit' => 'bottle']);
        $this->receive($this->beer, '48', '1440');
        $this->receive($this->gin, '10', '2000');
    }

    private function receive(InventoryItem $item, string $quantity, string $cost): StockReceipt
    {
        return $this->inventory->receive($this->owner, ['received_at' => now()->toIso8601String(), 'lines' => [['inventory_item_id' => $item->id, 'quantity' => $quantity, 'line_cost' => $cost]]]);
    }

    private function out(InventoryItem $item, string $quantity, ?User $by = null): InventoryMovement
    {
        return $this->inventory->stockOut($item, $by ?? $this->owner, ['quantity' => $quantity, 'reason' => 'damaged', 'note' => 'Dropped']);
    }

    /** Movement ids the API returns for these filters. */
    private function ids(array $filters = []): array
    {
        return collect($this->getJson(route('api.v1.inventory-movements.index', $filters))->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_all_movements_newest_first_across_items(): void
    {
        $out = $this->out($this->gin, '1');

        $expected = InventoryMovement::orderByDesc('id')->pluck('id')->all();
        $this->assertSame($expected, $this->ids());
        $this->assertSame($out->id, $expected[0]);
        $this->assertCount(3, $expected);
    }

    public function test_filters_by_item_type_and_user(): void
    {
        $byOwner = $this->out($this->beer, '1');
        $byManager = $this->out($this->beer, '2', $this->manager);

        $this->assertCount(3, $this->ids(['item_id' => $this->beer->id]));
        $this->assertSame([$byManager->id, $byOwner->id], $this->ids(['type' => 'adjustment']));
        $this->assertSame([$byManager->id], $this->ids(['user_id' => $this->manager->id]));
        $this->assertSame([$byOwner->id], $this->ids(['item_id' => $this->beer->id, 'type' => 'adjustment', 'user_id' => $this->owner->id]));
    }

    public function test_business_date_range_crosses_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 01:30', 'Asia/Manila'));
        $late = $this->out($this->beer, '1'); // before the 06:00 cutoff: still the 20th's business day
        $this->travelTo(CarbonImmutable::parse('2026-11-21 21:00', 'Asia/Manila'));
        $next = $this->out($this->beer, '1');

        $this->assertSame('2026-11-20', $late->business_date->toDateString());
        $this->assertSame([$late->id], $this->ids(['type' => 'adjustment', 'from' => '2026-11-20', 'to' => '2026-11-20']));
        $this->assertSame([$next->id], $this->ids(['type' => 'adjustment', 'from' => '2026-11-21']));
        $this->assertSame([$late->id], $this->ids(['type' => 'adjustment', 'to' => '2026-11-20']));
    }

    public function test_archived_items_movements_are_included(): void
    {
        $this->out($this->gin, '10');
        app(InventoryItemService::class)->archive($this->gin);

        $this->assertCount(3, $this->ids()); // two stock-ins and the stock-out
        $this->assertCount(2, $this->ids(['item_id' => $this->gin->id]));
        $this->get(route('inventory.movements'))->assertOk()->assertSee('<option value="'.$this->gin->id.'"', false);
    }

    public function test_invalid_filters_are_refused(): void
    {
        $this->getJson(route('api.v1.inventory-movements.index', ['type' => 'bogus', 'item_id' => 999999, 'user_id' => 999999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['type', 'item_id', 'user_id']);
        $this->getJson(route('api.v1.inventory-movements.index', ['from' => '2026-11-21', 'to' => '2026-11-20']))
            ->assertUnprocessable()->assertJsonValidationErrors(['to']);
        $this->getJson(route('api.v1.inventory-movements.index', ['from' => '21/11/2026']))
            ->assertUnprocessable()->assertJsonValidationErrors(['from']);
        $this->get(route('inventory.movements', ['type' => 'bogus']))->assertSessionHasErrors('type');
    }

    public function test_page_two_keeps_the_filters(): void
    {
        foreach (range(1, 51) as $i) {
            InventoryMovement::forceCreate(['inventory_item_id' => $this->beer->id, 'type' => 'adjustment', 'quantity' => '-0.001', 'unit_cost' => '30', 'total_cost' => '0.03',
                'balance_after' => '48', 'reason' => 'damaged', 'business_date' => '2026-11-20', 'created_by' => $this->owner->id]);
        }

        $response = $this->getJson(route('api.v1.inventory-movements.index', ['type' => 'adjustment']))->assertOk()->assertJsonCount(50, 'data');
        $this->assertStringContainsString('type=adjustment', $response->json('links.next'));
        $this->get(route('inventory.movements', ['type' => 'adjustment']))->assertOk()->assertSee('type=adjustment&amp;page=2', false);
    }

    public function test_the_adjustments_tab_shows_reason_note_and_unusual(): void
    {
        InventoryMovement::forceCreate(['inventory_item_id' => $this->beer->id, 'type' => 'adjustment', 'quantity' => '-40', 'unit_cost' => '30', 'total_cost' => '1200.00',
            'balance_after' => '8', 'reason' => 'missing', 'note' => 'Count was short', 'is_unusual' => true, 'business_date' => '2026-11-20', 'created_by' => $this->owner->id]);

        $this->get(route('inventory.movements', ['type' => 'adjustment']))->assertOk()
            ->assertSee(['Missing', 'Count was short', 'Unusual', '₱1,200.00'])
            ->assertSee('aria-current="page"', false)
            ->assertDontSee(route('stock-receipts.show', StockReceipt::first())); // only adjustments on this tab
        $this->getJson(route('api.v1.inventory-movements.index', ['type' => 'adjustment']))->assertJsonPath('data.0.is_unusual', true)
            ->assertJsonPath('data.0.item', 'Red Horse');
    }

    public function test_rows_link_to_item_and_source(): void
    {
        $receipt = StockReceipt::orderBy('id')->first();
        $order = $this->sell($this->owner, [[$this->trackedProduct($this->beer), 1]]);

        $page = $this->get(route('inventory.movements'))->assertOk()
            ->assertSee(route('inventory.show', $this->beer))
            ->assertSee(route('stock-receipts.show', $receipt))
            ->assertSee($order->reference)
            ->assertDontSee(route('transactions.show', $order->table_session_id)); // the tab is still open
        $this->assertSame($order->reference, $this->getJson(route('api.v1.inventory-movements.index', ['type' => 'sale']))->json('data.0.reference'));

        $this->payTab($this->owner, '100');
        $this->get(route('inventory.movements'))->assertSee(route('transactions.show', $order->table_session_id));
    }

    public function test_the_item_movements_api_lists_sales_with_their_order(): void
    {
        $beer = $this->trackedProduct($this->beer);
        $order = $this->sell($this->owner, [[$beer, 1], [$beer, 2]]);

        $this->getJson(route('api.v1.inventory-items.movements', $this->beer))->assertOk()
            ->assertJsonPath('data.0.reference', $order->reference)
            ->assertJsonPath('data.1.reference', $order->reference);
    }

    public function test_the_inventory_pages_link_to_each_other(): void
    {
        $this->get(route('inventory.index'))->assertSee(route('inventory.movements'))->assertSee(route('inventory.movements', ['type' => 'adjustment']), false);
        $this->get(route('inventory.movements'))->assertSee(route('inventory.index'));
    }

    public function test_the_movements_page_and_api_need_inventory_view(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('inventory.movements'))->assertForbidden();
        $this->getJson(route('api.v1.inventory-movements.index'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Events\StockLevelCrossed;
use App\Events\UnusualAdjustmentRecorded;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Feature\Inventory\Concerns\SellsTrackedProducts;
use Tests\TestCase;

class StockAlertsTest extends TestCase
{
    use RefreshDatabase, SellsTrackedProducts;

    private User $owner;

    private InventoryItem $beer; // 48 bottles at ₱30, low at 10

    private InventoryService $inventory;

    /** @var list<StockLevelCrossed> */
    private array $crossings = [];

    /** @var list<UnusualAdjustmentRecorded> */
    private array $unusual = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->inventory = app(InventoryService::class);
        $this->beer = app(InventoryItemService::class)->create(['name' => 'Red Horse', 'base_unit' => 'bottle', 'low_stock_level' => '10']);
        $this->receive('48', '1440');
        // Real listeners, not Event::fake(), so the after-commit behaviour is what runs.
        Event::listen(fn (StockLevelCrossed $e) => $this->crossings[] = $e);
        Event::listen(fn (UnusualAdjustmentRecorded $e) => $this->unusual[] = $e);
    }

    private function receive(string $quantity, string $cost): void
    {
        $this->inventory->receive($this->owner, ['received_at' => now()->toIso8601String(), 'lines' => [['inventory_item_id' => $this->beer->id, 'quantity' => $quantity, 'line_cost' => $cost]]]);
    }

    private function out(string $quantity): InventoryMovement
    {
        return $this->inventory->stockOut($this->beer->fresh(), $this->owner, ['quantity' => $quantity, 'reason' => 'missing']);
    }

    private function threshold(string $amount): void
    {
        app(SettingService::class)->set(['unusual_adjustment_amount' => $amount]);
    }

    /** @return list<string> "from>to" per crossing event */
    private function crossed(): array
    {
        return array_map(fn (StockLevelCrossed $e) => "{$e->from}>{$e->to}", $this->crossings);
    }

    public function test_events_wait_for_the_commit(): void
    {
        $this->assertContains(ShouldDispatchAfterCommit::class, class_implements(StockLevelCrossed::class));
        $this->assertContains(ShouldDispatchAfterCommit::class, class_implements(UnusualAdjustmentRecorded::class));
    }

    public function test_the_default_threshold_is_one_thousand_pesos(): void
    {
        $this->assertSame('1000.00', app(SettingService::class)->get('unusual_adjustment_amount'));
        $this->assertTrue($this->out('34')->is_unusual); // ₱1,020
        $this->assertFalse($this->out('14')->is_unusual); // ₱420
    }

    public function test_a_stock_out_at_the_threshold_is_unusual(): void
    {
        $this->threshold('900.00');
        $movement = $this->out('30'); // 30 × ₱30 = ₱900

        $this->assertTrue($movement->fresh()->is_unusual);
        $this->assertCount(1, $this->unusual);
        $this->assertSame($movement->id, $this->unusual[0]->movement->id);
        $this->assertTrue(ActivityLog::where('action', 'stock.adjusted')->sole()->new_values['unusual']);
        $this->get(route('inventory.show', $this->beer))->assertSee('Unusual');
        $this->get(route('inventory.movements', ['type' => 'adjustment']))->assertSee('Unusual');
    }

    public function test_below_the_threshold_is_not_unusual(): void
    {
        $this->threshold('900.00');
        $movement = $this->out('29'); // ₱870

        $this->assertFalse($movement->fresh()->is_unusual);
        $this->assertSame([], $this->unusual);
        $this->assertFalse(ActivityLog::where('action', 'stock.adjusted')->sole()->new_values['unusual']);
    }

    public function test_a_blank_or_zero_threshold_turns_it_off(): void
    {
        $this->threshold('');
        $this->assertFalse($this->out('24')->is_unusual);
        $this->threshold('0');
        $this->assertFalse($this->out('24')->is_unusual);
        $this->assertSame([], $this->unusual);
    }

    public function test_a_later_threshold_change_leaves_old_flags(): void
    {
        $this->threshold('900.00');
        $flagged = $this->out('30');
        $this->threshold('5000.00');

        $this->assertTrue($flagged->fresh()->is_unusual);
        $this->assertFalse($this->out('18')->is_unusual);
    }

    public function test_a_rolled_back_stock_out_fires_nothing(): void
    {
        $this->threshold('900.00');
        try {
            DB::transaction(function () {
                $this->out('48'); // unusual and in stock > out
                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame([], $this->unusual);
        $this->assertSame([], $this->crossings);
        $this->assertSame(1, InventoryMovement::count()); // only the stock-in
    }

    public function test_one_event_per_crossing(): void
    {
        $this->out('38'); // 48 → 10: at the level
        $this->assertSame(['in_stock>low_stock'], $this->crossed());
        $this->assertSame('10.000', $this->crossings[0]->movement->balance_after);
        $this->assertSame($this->beer->id, $this->crossings[0]->item->id);

        $this->out('5'); // 10 → 5: still low
        $this->assertSame(['in_stock>low_stock'], $this->crossed());

        $this->out('5'); // 5 → 0
        $this->assertSame(['in_stock>low_stock', 'low_stock>out_of_stock'], $this->crossed());
    }

    public function test_skipping_levels_fires_one_event_with_both_ends(): void
    {
        $this->out('48');

        $this->assertSame(['in_stock>out_of_stock'], $this->crossed());
    }

    public function test_recovery_then_drop_fires_again(): void
    {
        $this->out('40'); // 8: low
        $this->receive('10', '300'); // 18: in stock again, no event for getting better
        $this->out('9'); // 9: low again

        $this->assertSame(['in_stock>low_stock', 'in_stock>low_stock'], $this->crossed());
    }

    public function test_a_sale_crossing_fires_once_for_two_lines_of_one_item(): void
    {
        $this->out('36'); // 12: in stock
        $this->crossings = [];
        $beer = $this->trackedProduct($this->beer);

        $this->sell($this->owner, [[$beer, 1], [$beer, 1]]); // 12 → 11 → 10

        $this->assertSame(['in_stock>low_stock'], $this->crossed());
        $this->assertSame('sale', $this->crossings[0]->movement->type);
        $this->assertSame('10.000', $this->crossings[0]->movement->balance_after);
    }

    public function test_a_sale_below_zero_fires_out_to_negative(): void
    {
        app(SettingService::class)->set(['allow_negative_stock' => true]);
        $beer = $this->trackedProduct($this->beer);
        $this->out('48');
        $this->crossings = [];

        $this->sell($this->owner, [[$beer, 1]]);

        $this->assertSame(['out_of_stock>negative_stock'], $this->crossed());
    }

    public function test_the_setting_is_validated_and_saved(): void
    {
        $base = ['business_name' => 'YANGS'];
        foreach (['abc', '-1', '100000000', '1.234'] as $bad) {
            $this->put(route('settings.update'), [...$base, 'unusual_adjustment_amount' => $bad])->assertSessionHasErrors('unusual_adjustment_amount');
        }
        $this->put(route('settings.update'), [...$base, 'unusual_adjustment_amount' => '2500.50'])->assertSessionHasNoErrors();
        $this->assertSame('2500.50', app(SettingService::class)->get('unusual_adjustment_amount'));
        $this->assertSame(['unusual_adjustment_amount' => '2500.50'], ActivityLog::where('action', 'settings.updated')->latest('id')->first()->new_values);

        $this->put(route('settings.update'), [...$base, 'unusual_adjustment_amount' => ''])->assertSessionHasNoErrors();
        $this->assertSame('', app(SettingService::class)->get('unusual_adjustment_amount'));
        $this->get(route('settings.edit'))->assertOk()->assertSee('Unusual stock-out amount');
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\SupplierService;
use App\Support\BusinessDate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private User $owner;

    private Supplier $smb;

    private InventoryItem $beer; // base unit bottle, case = 24

    private InventoryItem $rum; // base unit ml, bottle = 750

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->inventory = app(InventoryService::class);
        $this->smb = app(SupplierService::class)->create(['name' => 'San Miguel Brewery']);
        $items = app(InventoryItemService::class);
        $this->beer = $items->saveUnits($items->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle']), [['name' => 'case', 'factor' => '24']]);
        $this->rum = $items->saveUnits($items->create(['name' => 'Tanduay Rum', 'base_unit' => 'ml']), [['name' => 'bottle', 'factor' => '750']]);
    }

    private function line(InventoryItem $item, string $quantity, string $cost, ?string $unit = null): array
    {
        return [
            'inventory_item_id' => $item->id,
            'inventory_unit_id' => $unit ? $item->units()->where('name', $unit)->value('id') : null,
            'quantity' => $quantity,
            'line_cost' => $cost,
        ];
    }

    private function receive(array $lines, array $extra = []): StockReceipt
    {
        return $this->inventory->receive($this->owner, ['supplier_id' => $this->smb->id, 'invoice_no' => 'INV-1', 'received_at' => now()->toIso8601String(), 'lines' => $lines, ...$extra]);
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

    public function test_cases_are_converted_to_bottles_and_costed_per_bottle(): void
    {
        $receipt = $this->receive([$this->line($this->beer, '2', '1440', 'case')]);

        $line = $receipt->lines->sole();
        $this->assertSame(['case', '24.000', '2.000', '48.000', '1440.00', '30.000000'],
            [$line->unit_name, $line->unit_factor, $line->quantity, $line->base_quantity, $line->line_cost, $line->base_unit_cost]);
        $this->assertSame(['48.000', '30.000000'], [$this->beer->fresh()->on_hand, $this->beer->fresh()->avg_unit_cost]);
        $this->assertMatchesRegularExpression('/^SR-\d{8}-0001$/', $receipt->reference);
        $this->assertSame(['1440.00', BusinessDate::for(now())], [$receipt->total_cost, $receipt->business_date->toDateString()]);
    }

    public function test_the_average_cost_is_weighted_by_quantity(): void
    {
        $this->receive([$this->line($this->beer, '10', '250')]); // 10 @ ₱25
        $this->receive([$this->line($this->beer, '2', '1440', 'case')]); // 48 @ ₱30

        // (10 × 25 + 48 × 30) / 58 = 1690 / 58 = 29.137931…
        $this->assertSame(['58.000', '29.137931'], [$this->beer->fresh()->on_hand, $this->beer->fresh()->avg_unit_cost]);
    }

    public function test_lines_for_the_same_item_average_one_after_the_other_and_free_stock_lowers_the_cost(): void
    {
        $this->receive([$this->line($this->beer, '10', '250'), $this->line($this->beer, '10', '0')]); // promo: 10 free bottles

        $this->assertSame(['20.000', '12.500000'], [$this->beer->fresh()->on_hand, $this->beer->fresh()->avg_unit_cost]);
        $this->assertSame(['10.000', '20.000'], InventoryMovement::where('inventory_item_id', $this->beer->id)->orderBy('id')->pluck('balance_after')->all());
    }

    public function test_stock_received_while_negative_takes_the_new_cost(): void
    {
        // Sold 5 before any stock-in (a later step writes sales; simulate the ledger row and cache it keeps).
        InventoryMovement::forceCreate(['inventory_item_id' => $this->beer->id, 'type' => 'sale', 'quantity' => '-5', 'unit_cost' => '0', 'total_cost' => '0', 'balance_after' => '-5', 'business_date' => BusinessDate::for(now()), 'created_by' => $this->owner->id]);
        DB::table('inventory_items')->where('id', $this->beer->id)->update(['on_hand' => '-5']);

        $this->receive([$this->line($this->beer, '1', '720', 'case')]);

        $this->assertSame(['19.000', '30.000000'], [$this->beer->fresh()->on_hand, $this->beer->fresh()->avg_unit_cost]);
    }

    public function test_liquor_is_stocked_by_the_bottle_and_costed_per_ml(): void
    {
        $this->receive([$this->line($this->rum, '3', '1000', 'bottle')]);

        $this->assertSame(['2250.000', '0.444444'], [$this->rum->fresh()->on_hand, $this->rum->fresh()->avg_unit_cost]); // ₱1,000 / 2,250 ml
        $this->assertSame('1000.00', InventoryMovement::where('inventory_item_id', $this->rum->id)->sole()->total_cost);
    }

    public function test_every_line_writes_one_receipt_movement_and_the_ledger_matches_the_cache(): void
    {
        $this->receive([$this->line($this->beer, '1', '720', 'case'), $this->line($this->rum, '1', '350', 'bottle')]);
        $this->receive([$this->line($this->beer, '6', '180'), $this->line($this->rum, '375', '180')]);

        $receipt = StockReceipt::latest('id')->first();
        $movement = InventoryMovement::where('source_type', $receipt->lines->first()->getMorphClass())->where('source_id', $receipt->lines->first()->id)->sole();
        $this->assertSame(['receipt', '6.000', '30.000000', '180.00', '30.000'], [$movement->type, $movement->quantity, $movement->unit_cost, $movement->total_cost, $movement->balance_after]);

        foreach ([$this->beer, $this->rum] as $item) {
            $ledger = InventoryMovement::where('inventory_item_id', $item->id)->sum('quantity');
            $this->assertSame(0, bccomp((string) $ledger, $item->fresh()->on_hand, 3), "{$item->name}: ledger {$ledger} vs cache {$item->fresh()->on_hand}");
        }
        $this->assertSame(['30.000', '1125.000'], [$this->beer->fresh()->on_hand, $this->rum->fresh()->on_hand]);
    }

    public function test_stock_in_is_logged_with_its_lines(): void
    {
        $receipt = $this->receive([$this->line($this->beer, '2', '1440', 'case')]);

        $log = ActivityLog::where('action', 'stock.received')->sole();
        $this->assertSame($receipt->id, $log->subject_id);
        $this->assertEquals([
            'reference' => $receipt->reference, 'supplier' => 'San Miguel Brewery', 'invoice_no' => 'INV-1', 'total_cost' => '1440.00',
            'lines' => [['item' => 'Red Horse 500ml', 'quantity' => '2.000 case', 'base_quantity' => '48.000', 'line_cost' => '1440.00']],
        ], $log->new_values);
    }

    public function test_bad_input_is_refused_and_nothing_is_written(): void
    {
        $this->assertRefused(fn () => $this->receive([]), 'lines', 'Add at least one item.');
        $this->assertRefused(fn () => $this->receive([$this->line($this->beer, '0', '10')]), 'lines.0.quantity');
        $this->assertRefused(fn () => $this->receive([$this->line($this->beer, '1.2345', '10')]), 'lines.0.quantity');
        $this->assertRefused(fn () => $this->receive([$this->line($this->beer, '1', '-1')]), 'lines.0.line_cost');
        $this->assertRefused(fn () => $this->receive([['inventory_item_id' => $this->beer->id, 'inventory_unit_id' => $this->rum->units()->value('id'), 'quantity' => '1', 'line_cost' => '10']]), 'lines.0.inventory_unit_id', 'That unit is not one of Red Horse 500ml\'s units.');
        $this->assertRefused(fn () => $this->receive([$this->line($this->beer, '1', '10')], ['received_at' => now()->addDay()->toIso8601String()]), 'received_at', 'The date received cannot be in the future.');

        app(SupplierService::class)->archive($this->smb);
        $this->assertRefused(fn () => $this->receive([$this->line($this->beer, '1', '10')]), 'supplier_id', 'Choose a supplier that is in use.');
        app(InventoryItemService::class)->archive($this->rum);
        $this->assertRefused(fn () => $this->receive([$this->line($this->rum, '1', '10')], ['supplier_id' => null]), 'lines.0.inventory_item_id', 'Choose an inventory item that is in use.');

        $this->assertSame([0, 0, '0.000'], [StockReceipt::count(), InventoryMovement::count(), $this->beer->fresh()->on_hand]);
    }

    public function test_a_utc_time_from_an_api_client_is_stored_as_the_same_moment(): void
    {
        $sent = now()->subHour()->utc()->startOfSecond();

        $receipt = $this->receive([$this->line($this->beer, '1', '30')], ['received_at' => $sent->toIso8601ZuluString()]);

        $this->assertTrue($receipt->fresh()->received_at->equalTo($sent), $receipt->fresh()->received_at.' vs '.$sent);
    }

    public function test_numbers_too_small_or_too_large_for_the_ledger_are_refused_not_crashed(): void
    {
        $items = app(InventoryItemService::class);
        $tiny = $items->saveUnits($items->create(['name' => 'Saffron', 'base_unit' => 'g']), [['name' => 'pinch', 'factor' => '0.4']]);

        $this->assertRefused(fn () => $this->receive([$this->line($tiny, '0.001', '10', 'pinch')]), 'lines.0.quantity', 'This is less than 0.001 g. Enter a bigger quantity.');
        $this->assertRefused(fn () => $this->receive([$this->line($this->rum, '0.001', '150000')]), 'lines.0.line_cost', 'That works out to more than ₱99,999,999 per ml. Check the quantity and cost.');
        $this->assertRefused(fn () => $this->receive([$this->line($this->beer, '9999999999', '10', 'case')]), 'lines.0.quantity', 'That is more stock than the system can hold. Check the quantity.');
        $this->assertRefused(fn () => $this->receive(array_fill(0, 2, $this->line($this->beer, '1000', '9999999999'))), 'lines', 'The stock-in total is too large. Split it into smaller stock-ins.');
        $this->assertSame(0, StockReceipt::count());
    }

    public function test_stock_in_needs_its_permission(): void
    {
        $cashier = User::factory()->create()->assignRole('Cashier');

        $this->expectException(AuthorizationException::class);
        $this->inventory->receive($cashier, ['received_at' => now()->toIso8601String(), 'lines' => [$this->line($this->beer, '1', '10')]]);
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockOutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryItem $beer; // 48 bottles at ₱30, case = 24

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->inventory = app(InventoryService::class);
        $items = app(InventoryItemService::class);
        $this->beer = $items->saveUnits($items->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle']), [['name' => 'case', 'factor' => '24']]);
        $this->inventory->receive($this->owner, ['received_at' => now()->toIso8601String(), 'lines' => [['inventory_item_id' => $this->beer->id, 'quantity' => '48', 'line_cost' => '1440']]]);
    }

    private function out(array $input, ?User $by = null): InventoryMovement
    {
        return $this->inventory->stockOut($this->beer, $by ?? $this->owner, $input);
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

    public function test_a_stock_out_takes_stock_at_the_average_cost_and_is_logged(): void
    {
        $movement = $this->out(['quantity' => '3', 'reason' => 'damaged', 'note' => 'Dropped crate']);

        $this->assertSame(['adjustment', '-3.000', '30.000000', '90.00', '45.000', 'damaged', 'Dropped crate'],
            [$movement->type, $movement->quantity, $movement->unit_cost, $movement->total_cost, $movement->balance_after, $movement->reason, $movement->note]);
        $this->assertSame(['45.000', '30.000000'], [$this->beer->fresh()->on_hand, $this->beer->fresh()->avg_unit_cost]);
        $this->assertEquals(['item' => 'Red Horse 500ml', 'quantity' => '3.000', 'reason' => 'damaged', 'note' => 'Dropped crate', 'value' => '90.00', 'on_hand' => '45.000'],
            ActivityLog::where('action', 'stock.adjusted')->sole()->new_values);
    }

    public function test_a_stock_out_can_be_counted_in_a_bigger_unit(): void
    {
        $this->out(['quantity' => '1', 'inventory_unit_id' => $this->beer->units()->value('id'), 'reason' => 'internal_use']);

        $this->assertSame('24.000', $this->beer->fresh()->on_hand);
    }

    public function test_reasons_notes_and_quantities_are_checked(): void
    {
        $this->assertRefused(fn () => $this->out(['quantity' => '1']), 'reason', 'Choose why the stock is going out.');
        $this->assertRefused(fn () => $this->out(['quantity' => '1', 'reason' => 'stolen']), 'reason');
        $this->assertRefused(fn () => $this->out(['quantity' => '1', 'reason' => 'other']), 'note', 'Say what happened when the reason is "Other".');
        $this->assertRefused(fn () => $this->out(['quantity' => '0', 'reason' => 'expired']), 'quantity');
        $this->assertRefused(fn () => $this->out(['quantity' => '49', 'reason' => 'missing']), 'quantity', 'Only 48 bottle of Red Horse 500ml on hand.');
        $this->assertSame(0, InventoryMovement::where('type', 'adjustment')->count());
    }

    public function test_stock_outs_need_inventory_adjust(): void
    {
        $cashier = User::factory()->create()->assignRole('Cashier');
        Role::findByName('Cashier')->givePermissionTo('inventory.view');

        $this->actingAs($cashier)->post("/inventory/{$this->beer->id}/stock-outs", ['quantity' => '1', 'reason' => 'damaged'])->assertForbidden();
        $this->expectException(AuthorizationException::class);
        $this->out(['quantity' => '1', 'reason' => 'damaged'], $cashier->fresh());
    }

    public function test_the_item_page_records_a_stock_out_and_lists_its_reason(): void
    {
        $this->get("/inventory/{$this->beer->id}")->assertOk()->assertSee('Stock-out');
        $this->post("/inventory/{$this->beer->id}/stock-outs", ['quantity' => '2', 'reason' => 'expired'])->assertRedirect("/inventory/{$this->beer->id}");

        $this->get("/inventory/{$this->beer->id}")->assertSee('Expired')->assertSee('46 bottle');
        $this->from("/inventory/{$this->beer->id}")->post("/inventory/{$this->beer->id}/stock-outs", ['quantity' => '999', 'reason' => 'expired'])
            ->assertSessionHasErrors('quantity');
    }

    public function test_the_api_records_a_stock_out(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/inventory-items/{$this->beer->id}/stock-outs", ['quantity' => '4', 'reason' => 'missing', 'note' => 'Count short'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.quantity', '-4.000')
            ->assertJsonPath('data.reason', 'missing');
    }
}

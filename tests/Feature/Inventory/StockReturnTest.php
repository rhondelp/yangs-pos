<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\RefundService;
use App\Services\Corrections\VoidService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\RecipeService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private TableSession $session;

    private InventoryItem $beer; // 24 bottles at ₱30

    private Product $redHorse; // tracked, 1 bottle per sale

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->beer = app(InventoryItemService::class)->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle']);
        $this->receive('24', '720');
        $this->redHorse = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'track_inventory' => true]);
        app(RecipeService::class)->save($this->redHorse, null, [['inventory_item_id' => $this->beer->id, 'quantity' => '1']]);
        $this->session = app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]), $this->owner);
    }

    private function receive(string $quantity, string $cost): void
    {
        app(InventoryService::class)->receive($this->owner, ['received_at' => now()->toIso8601String(), 'lines' => [['inventory_item_id' => $this->beer->id, 'quantity' => $quantity, 'line_cost' => $cost]]]);
    }

    private function sell(int $quantity): OrderItem
    {
        return app(OrderService::class)->addOrder($this->session, $this->owner, [['product_id' => $this->redHorse->id, 'quantity' => $quantity]])->items->sole();
    }

    private function pay(?string $amount = null): void
    {
        app(PaymentService::class)->record($this->session, $this->owner, ['method_id' => PaymentMethod::where('code', 'cash')->value('id'), 'amount' => $amount, 'idempotency_key' => (string) Str::uuid()]);
    }

    private function onHand(): string
    {
        return $this->beer->fresh()->on_hand;
    }

    public function test_a_partial_cancel_puts_the_cancelled_units_back_at_the_sale_cost(): void
    {
        $line = $this->sell(5);
        $this->receive('1', '60'); // average moves to (19 × 30 + 60) / 20 = 31.50

        $cancelled = app(OrderService::class)->cancelItem($line, $this->owner, 'Wrong order', 2, returnToStock: true);

        $this->assertSame('22.000', $this->onHand());
        $return = InventoryMovement::where('type', 'void_return')->sole();
        $this->assertSame(['2.000', '30.000000', '60.00', '22.000'], [$return->quantity, $return->unit_cost, $return->total_cost, $return->balance_after]);
        $this->assertSame('31.363636', $this->beer->fresh()->avg_unit_cost); // (20 × 31.50 + 2 × 30) / 22
        $this->assertTrue($cancelled->fresh()->returned_to_stock);
    }

    public function test_a_cancel_that_does_not_go_back_to_stock_writes_no_movement(): void
    {
        $line = $this->sell(2);

        $cancelled = app(OrderService::class)->cancelItem($line, $this->owner, 'Spilled', returnToStock: false);

        $this->assertSame('22.000', $this->onHand());
        $this->assertSame(0, InventoryMovement::where('type', 'void_return')->count());
        $this->assertFalse($cancelled->fresh()->returned_to_stock);
    }

    public function test_a_void_returns_its_units_when_asked(): void
    {
        $line = $this->sell(3); // ₱255
        $this->pay('100');

        $void = app(VoidService::class)->voidItem($line, $this->owner, 1, 'Unopened', returnToStock: true);

        $this->assertSame('22.000', $this->onHand());
        $this->assertTrue($void->returned_to_stock);
        $this->assertSame($line->id, InventoryMovement::where('type', 'void_return')->sole()->source_id);
    }

    public function test_voids_without_return_and_refunds_leave_stock_alone(): void
    {
        $line = $this->sell(3); // ₱255
        $this->pay('100');

        $void = app(VoidService::class)->voidItem($line, $this->owner, 1, 'Poured wrong', returnToStock: false);
        $this->assertFalse($void->returned_to_stock);
        $this->assertSame('21.000', $this->onHand());

        $this->pay(); // ₱70 closes it
        $payment = $this->session->payments()->first();
        app(RefundService::class)->refund($payment, $this->owner, ['amount' => '50', 'reason' => 'Warm', 'idempotency_key' => (string) Str::uuid()]);

        $this->assertSame('21.000', $this->onHand());
        $this->assertSame(0, InventoryMovement::where('type', 'void_return')->count());
    }

    public function test_the_api_takes_the_put_back_choice_and_reports_stock_warnings(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $order = $this->postJson("/api/v1/sessions/{$this->session->id}/orders", ['lines' => [['product_id' => $this->redHorse->id, 'quantity' => 3]]])
            ->assertCreated()->assertJsonPath('data.items.0.tracks_stock', true)->assertJsonPath('warnings', [])->json('data');

        $this->assertSame('21.000', $this->onHand());
        $this->postJson("/api/v1/order-items/{$order['items'][0]['id']}/cancel", ['reason' => 'Spilled', 'quantity' => 1, 'return_to_stock' => false])->assertOk();
        $this->assertSame('21.000', $this->onHand());
        $this->postJson("/api/v1/order-items/{$order['items'][0]['id']}/cancel", ['reason' => 'Wrong', 'quantity' => 1])->assertOk(); // default: back to stock
        $this->assertSame('22.000', $this->onHand());

        app(\App\Services\Settings\SettingService::class)->set(['allow_negative_stock' => true]);
        $this->postJson("/api/v1/sessions/{$this->session->id}/orders", ['lines' => [['product_id' => $this->redHorse->id, 'quantity' => 30]]])
            ->assertCreated()->assertJsonPath('warnings', ['Red Horse 500ml is below zero: -8 bottle.']);

        $this->pay('100');
        $line = $this->session->items()->where('order_items.status', 'active')->first();
        $this->postJson("/api/v1/order-items/{$line->id}/void", ['quantity' => 1, 'reason' => 'Poured wrong', 'idempotency_key' => (string) Str::uuid(), 'return_to_stock' => false])
            ->assertCreated()->assertJsonPath('bill.bill.items.0.tracks_stock', true);
        $this->assertFalse(\App\Models\OrderVoid::sole()->returned_to_stock);
    }

    public function test_voiding_a_whole_order_locks_all_its_stock_at_once_in_id_order(): void
    {
        // Two tracked lines whose inventory ids run backwards (line 1 uses the higher id).
        $ice = app(InventoryItemService::class)->create(['name' => 'Ice bucket', 'base_unit' => 'pc']);
        app(InventoryService::class)->receive($this->owner, ['received_at' => now()->toIso8601String(), 'lines' => [['inventory_item_id' => $ice->id, 'quantity' => '10', 'line_cost' => '100']]]);
        $bucket = app(ProductService::class)->create(['category_id' => $this->redHorse->category_id, 'name' => 'Ice', 'type' => 'other', 'price' => '20', 'track_inventory' => true]);
        app(RecipeService::class)->save($bucket, null, [['inventory_item_id' => $ice->id, 'quantity' => '1']]);
        $order = app(OrderService::class)->addOrder($this->session, $this->owner, [['product_id' => $bucket->id, 'quantity' => 1], ['product_id' => $this->redHorse->id, 'quantity' => 2]]);
        app(OrderService::class)->addOrder($this->session, $this->owner, [['product_id' => $this->redHorse->id, 'quantity' => 2]]);
        $this->pay('50');

        \Illuminate\Support\Facades\DB::enableQueryLog();
        app(VoidService::class)->voidOrder($order, $this->owner, 'Wrong table', returnToStock: true);
        $locks = array_values(array_filter(\Illuminate\Support\Facades\DB::getQueryLog(), fn ($q) => str_contains($q['query'], '"inventory_items"') && str_contains($q['query'], 'for update')));

        // Integer whereIn values are inlined; PostgreSQL takes the row locks in the ORDER BY order.
        preg_match('/"id" in \(([\d, ]+)\) order by "id" asc for update/', $locks[0]['query'], $m);
        $ids = array_map('intval', explode(',', $m[1] ?? ''));
        sort($ids);
        $this->assertSame([$this->beer->id, $ice->id], $ids, 'the first inventory lock must cover every item, ascending: '.$locks[0]['query']);
        $this->assertSame(['22.000', '10.000'], [$this->onHand(), $ice->fresh()->on_hand]);
    }

    public function test_a_return_onto_negative_stock_takes_the_sale_cost(): void
    {
        app(\App\Services\Settings\SettingService::class)->set(['allow_negative_stock' => true]);
        $line = $this->sell(30); // 24 − 30 = −6
        $this->receive('2', '100'); // −4 on hand; stock-in while negative sets the average to ₱50

        app(OrderService::class)->cancelItem($line, $this->owner, 'Wrong table', 10, returnToStock: true);

        $this->assertSame(['6.000', '30.000000'], [$this->onHand(), $this->beer->fresh()->avg_unit_cost]); // on hand was ≤ 0: the return's own cost
    }

    public function test_a_return_onto_empty_stock_takes_the_sale_cost(): void
    {
        $line = $this->sell(24); // shelf empty
        $this->assertSame('0.000', $this->onHand());

        app(OrderService::class)->cancelItem($line, $this->owner, 'Wrong table', 4, returnToStock: true);

        $this->assertSame(['4.000', '30.000000'], [$this->onHand(), $this->beer->fresh()->avg_unit_cost]);
    }
}

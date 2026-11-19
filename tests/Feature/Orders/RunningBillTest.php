<?php

namespace Tests\Feature\Orders;

use App\Models\Category;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Orders\OrderService;
use App\Services\Orders\RunningBill;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RunningBillTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;

    private User $owner;

    private TableSession $session;

    private Product $redHorse;

    private Product $sisig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->orders = app(OrderService::class);

        $products = app(ProductService::class);
        $beer = Category::create(['name' => 'Beer']);
        $this->redHorse = $products->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50',
            'variants' => [['name' => 'Bucket of 6', 'price' => '480', 'cost' => '300']]]);
        $this->sisig = $products->create(['category_id' => $beer->id, 'name' => 'Sisig', 'type' => 'drink', 'price' => '220']);
        $table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($table, $this->owner);
    }

    private function bill(): array
    {
        return app(RunningBill::class)->calculate($this->session);
    }

    public function test_an_empty_tab_has_a_zero_bill(): void
    {
        $this->assertSame([
            'gross' => '0.00', 'discounts' => '0.00', 'subtotal' => '0.00', 'bill_discount' => '0.00',
            'bill_discount_type' => null, 'bill_discount_value' => null, 'bill_discount_reason' => null,
            'total' => '0.00', 'paid' => '0.00', 'balance' => '0.00', 'item_count' => 0,
            'orders' => [], 'cancelled' => [], 'payments' => [],
        ], $this->bill());
    }

    public function test_the_bill_is_exact_over_two_orders_with_a_discount_and_cancels(): void
    {
        $first = $this->orders->addOrder($this->session, $this->owner, [
            ['product_id' => $this->redHorse->id, 'quantity' => 3],
            ['product_id' => $this->sisig->id, 'quantity' => 1],
        ]);
        [$beer, $sisig] = $first->items->all();
        $this->orders->updateItem($sisig, $this->owner, ['discount' => '12.50']);
        $spilled = $this->orders->cancelItem($beer, $this->owner, 'spilled', 1);

        $second = $this->orders->addOrder($this->session, $this->owner, [
            ['product_id' => $this->redHorse->id, 'variant_id' => $this->redHorse->variants->sole()->id, 'quantity' => 1],
            ['product_id' => $this->redHorse->id, 'quantity' => 2],
        ]);
        $returned = $this->orders->cancelItem($second->items[1], $this->owner, 'wrong table');

        $bill = $this->bill();

        // Active: Red Horse 2 x 85 = 170, Sisig 220 - 12.50, Bucket 480.
        $this->assertSame(['870.00', '12.50', '857.50', 4], [$bill['gross'], $bill['discounts'], $bill['total'], $bill['item_count']]);
        $this->assertSame([
            ['id' => $first->id, 'reference' => $first->reference, 'total' => '377.50'],
            ['id' => $second->id, 'reference' => $second->reference, 'total' => '480.00'],
        ], array_map(fn ($o) => array_diff_key($o, ['created_at' => 1]), $bill['orders']));
        $this->assertStringEndsWith('+08:00', $bill['orders'][0]['created_at']);
        $this->assertSame($first->created_at->toIso8601String(), $bill['orders'][0]['created_at']);
        $this->assertSame([
            ['id' => $spilled->id, 'product_name' => 'Red Horse', 'quantity' => 1, 'cancel_reason' => 'spilled'],
            ['id' => $returned->id, 'product_name' => 'Red Horse', 'quantity' => 2, 'cancel_reason' => 'wrong table'],
        ], $bill['cancelled']);
    }

    public function test_cancelled_items_are_excluded_from_the_total_and_listed_separately(): void
    {
        $order = $this->orders->addOrder($this->session, $this->owner, [
            ['product_id' => $this->redHorse->id, 'quantity' => 2],
            ['product_id' => $this->sisig->id, 'quantity' => 1],
        ]);
        $cancelled = $this->orders->cancelItem($order->items[1], $this->owner, 'kitchen closed');

        $bill = $this->bill();

        $this->assertSame(['170.00', '0.00', '170.00', 2], [$bill['gross'], $bill['discounts'], $bill['total'], $bill['item_count']]);
        $this->assertSame('170.00', $bill['orders'][0]['total']);
        $this->assertSame([['id' => $cancelled->id, 'product_name' => 'Sisig', 'quantity' => 1, 'cancel_reason' => 'kitchen closed']], $bill['cancelled']);
    }

    public function test_the_bill_loads_with_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 3) as $i) {
            $this->orders->addOrder($this->session, $this->owner, [['product_id' => $this->redHorse->id, 'quantity' => $i]]);
        }

        DB::enableQueryLog();
        $bill = $this->bill();

        $this->assertSame('510.00', $bill['total']);
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog())); // orders, items, payments, methods
    }
}

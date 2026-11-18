<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SessionStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Orders\OrderService;
use App\Services\Orders\StockHook;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;

    private ProductService $products;

    private User $owner;

    private User $cashier;

    private TableSession $session;

    private Category $beer;

    private Product $redHorse;

    private StockHook $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($this->owner);

        // Records every hook call and whether it ran inside the caller's transaction.
        $this->stock = new class extends StockHook
        {
            public array $calls = [];

            public function deductForOrder(Order $order): void
            {
                $this->calls[] = ['deduct', $order->id, $order->items->count(), DB::transactionLevel() > 0];
            }

            public function itemCancelled(OrderItem $item, int $quantity): void
            {
                $this->calls[] = ['cancelled', $item->id, $quantity, DB::transactionLevel() > 0];
            }

            public function quantityAdded(OrderItem $item, int $added): void
            {
                $this->calls[] = ['added', $item->id, $added, DB::transactionLevel() > 0];
            }
        };
        $this->app->instance(StockHook::class, $this->stock);

        $this->orders = app(OrderService::class);
        $this->products = app(ProductService::class);
        $this->beer = Category::create(['name' => 'Beer']);
        $this->redHorse = $this->product('Red Horse', '85', '52.50');
        $table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($table, $this->cashier);
    }

    private function product(string $name, string $price, ?string $cost = null, array $overrides = []): Product
    {
        return $this->products->create([
            'category_id' => $this->beer->id, 'name' => $name, 'type' => 'drink',
            'price' => $price, 'cost' => $cost, ...$overrides,
        ]);
    }

    private function order(array $lines, ?User $by = null): Order
    {
        return $this->orders->addOrder($this->session, $by ?? $this->cashier, $lines);
    }

    private function line(Product $product, int $quantity = 1, ?int $variantId = null): array
    {
        return ['product_id' => $product->id, 'variant_id' => $variantId, 'quantity' => $quantity];
    }

    private function assertRefused(callable $do, string $key, string $message): void
    {
        try {
            $do();
            $this->fail('expected a refusal');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
            $this->assertStringContainsString($message, $e->errors()[$key][0]);
        }
    }

    // activity_logs stores jsonb, which does not keep key order.
    private function assertLogValues(array $expected, ?array $actual): void
    {
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    private function supervisorWith(string ...$permissions): User
    {
        Role::findByName('Supervisor')->givePermissionTo($permissions);

        return User::factory()->create()->assignRole('Supervisor');
    }

    public function test_an_order_snapshots_its_lines_and_logs(): void
    {
        $order = $this->orders->addOrder($this->session, $this->cashier, [
            ['product_id' => $this->redHorse->id, 'quantity' => 3, 'notes' => 'ice'],
        ], 'round one');

        $item = $order->items->sole();
        $this->assertSame(['Red Horse', null, '85.00', '52.50', 3, '0.00', '255.00', 'ice', OrderItemStatus::Active],
            [$item->product_name, $item->variant_name, $item->unit_price, $item->unit_cost, $item->quantity, $item->discount, $item->line_total, $item->notes, $item->status]);
        $this->assertSame($this->cashier->id, $order->created_by);
        $this->assertSame('round one', $order->notes);
        $this->assertSame(OrderStatus::Open, $order->fresh()->status);
        $log = ActivityLog::where('action', 'order.created')->sole();
        $this->assertSame($order->id, $log->subject_id);
        $this->assertLogValues(['reference' => $order->reference, 'items' => 1, 'total' => '255.00'], $log->new_values);
    }

    public function test_multiple_orders_per_session_get_consecutive_references(): void
    {
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 19)->setTime(1, 30));

        $first = $this->order([$this->line($this->redHorse)]);
        $second = $this->order([$this->line($this->redHorse)]);

        $this->assertSame('ORD-20261118-0001', $first->reference);
        $this->assertSame('ORD-20261118-0002', $second->reference);
        $this->assertSame('2026-11-18', $second->fresh()->business_date->toDateString());
        $this->assertSame([$first->id, $second->id], $this->session->orders()->pluck('id')->all());
    }

    public function test_a_price_snapshot_survives_a_later_price_change(): void
    {
        $old = $this->order([$this->line($this->redHorse)])->items->sole();

        $this->products->update($this->redHorse, ['price' => '90']);
        $new = $this->order([$this->line($this->redHorse->fresh())])->items->sole();

        $this->assertSame(['85.00', '85.00'], [$old->fresh()->unit_price, $old->fresh()->line_total]);
        $this->assertSame(['90.00', '90.00'], [$new->unit_price, $new->line_total]);
    }

    public function test_variant_price_and_cost_are_used_with_product_cost_as_fallback_and_null_cost_stays_null(): void
    {
        $product = $this->product('Pale Pilsen', '75', '40.00', ['variants' => [
            ['name' => 'Bucket of 6', 'price' => '420', 'cost' => '250.00'],
            ['name' => 'Pitcher', 'price' => '260'],
        ]]);
        [$bucket, $pitcher] = $product->variants->all();
        $water = $this->product('Water', '30');

        $items = $this->order([
            $this->line($product, 2, $bucket->id),
            $this->line($product, 1, $pitcher->id),
            $this->line($water),
        ])->items;

        $this->assertSame(['Bucket of 6', '420.00', '250.00', '840.00'], [$items[0]->variant_name, $items[0]->unit_price, $items[0]->unit_cost, $items[0]->line_total]);
        $this->assertSame(['Pitcher', '260.00', '40.00'], [$items[1]->variant_name, $items[1]->unit_price, $items[1]->unit_cost]);
        $this->assertSame($pitcher->id, $items[1]->product_variant_id);
        $this->assertSame(['30.00', null], [$items[2]->unit_price, $items[2]->unit_cost]);
    }

    public function test_archived_hidden_wrong_variant_and_out_of_range_lines_are_refused_and_nothing_is_created(): void
    {
        $archived = $this->product('Tanduay', '120');
        $this->products->archive($archived);
        $hidden = $this->product('Gin', '150', overrides: ['is_available' => false]);
        $closedCategory = Category::create(['name' => 'Seasonal']);
        $seasonal = $this->product('Eggnog', '90', overrides: ['category_id' => $closedCategory->id]);
        $closedCategory->delete(); // the service refuses while products remain; this is the race it guards against
        $other = $this->product('Pale Pilsen', '75', overrides: ['variants' => [['name' => 'Bucket', 'price' => '420'], ['name' => 'Tower', 'price' => '900']]]);
        [$otherBucket, $tower] = $other->variants->all();
        $this->products->update($other, ['variants' => [['id' => $otherBucket->id, 'name' => 'Bucket', 'price' => '420']]]);
        $ok = $this->line($this->redHorse);

        $this->assertRefused(fn () => $this->order([$ok, $this->line($archived)]), 'lines.1.product_id', 'Tanduay is not on the menu right now.');
        $this->assertRefused(fn () => $this->order([$this->line($hidden)]), 'lines.0.product_id', 'Gin is not on the menu right now.');
        $this->assertRefused(fn () => $this->order([$this->line($seasonal)]), 'lines.0.product_id', 'Eggnog is not on the menu right now.');
        $this->assertRefused(fn () => $this->order([['product_id' => 999999, 'quantity' => 1]]), 'lines.0.product_id', '#999999 is not on the menu right now.');
        $this->assertRefused(fn () => $this->order([$this->line($this->redHorse, 1, $otherBucket->id)]), 'lines.0.variant_id', 'not an option of Red Horse');
        $this->assertRefused(fn () => $this->order([$this->line($other, 1, $tower->id)]), 'lines.0.variant_id', 'not an option of Pale Pilsen');
        $this->assertRefused(fn () => $this->order([$this->line($this->redHorse, 0)]), 'lines.0.quantity', 'between 1 and 99');
        $this->assertRefused(fn () => $this->order([$ok, $this->line($this->redHorse, 100)]), 'lines.1.quantity', 'between 1 and 99');
        $this->assertRefused(fn () => $this->order([]), 'lines', 'Add at least one item.');

        $this->assertSame([0, 0, 0], [Order::count(), OrderItem::count(), DB::table('order_reference_counters')->count()]);
        $this->assertSame(0, ActivityLog::where('action', 'order.created')->count());
        $this->assertSame([], $this->stock->calls);
    }

    public function test_taking_an_order_needs_the_permission(): void
    {
        $staff = User::factory()->create()->assignRole('Staff');

        $this->expectException(AuthorizationException::class);
        $this->order([$this->line($this->redHorse)], $staff);
    }

    public function test_raising_the_quantity_and_setting_a_discount_recompute_the_line_and_log_only_changed_fields(): void
    {
        $item = $this->order([$this->line($this->redHorse, 2)])->items->sole();

        $updated = $this->orders->updateItem($item, $this->cashier, ['quantity' => 3, 'notes' => null]);
        $this->assertSame([3, '255.00'], [$updated->quantity, $updated->line_total]);
        $log = ActivityLog::where('action', 'order_item.updated')->sole();
        $this->assertLogValues(['quantity' => 2, 'line_total' => '170.00'], $log->old_values);
        $this->assertLogValues(['quantity' => 3, 'line_total' => '255.00'], $log->new_values);

        $supervisor = $this->supervisorWith('orders.discount');
        $updated = $this->orders->updateItem($item, $supervisor, ['quantity' => 3, 'discount' => '12.5']);
        $this->assertSame(['12.50', '242.50'], [$updated->discount, $updated->line_total]);
        $this->assertSame('242.50', $item->line_total); // caller's model is refreshed
        $log = ActivityLog::where('action', 'order_item.updated')->orderByDesc('id')->first();
        $this->assertLogValues(['discount' => '0.00', 'line_total' => '255.00'], $log->old_values);
        $this->assertLogValues(['discount' => '12.50', 'line_total' => '242.50'], $log->new_values);
        $this->assertSame($supervisor->id, $log->user_id);
    }

    public function test_a_discount_must_be_between_zero_and_the_line_gross(): void
    {
        $item = $this->order([$this->line($this->redHorse, 2)])->items->sole();

        $this->assertRefused(fn () => $this->orders->updateItem($item, $this->owner, ['discount' => '170.01']), 'discount', 'between 0.00 and 170.00');
        $this->assertRefused(fn () => $this->orders->updateItem($item, $this->owner, ['discount' => '-1']), 'discount', 'between 0.00 and 170.00');
        $this->assertRefused(fn () => $this->orders->updateItem($item, $this->owner, ['discount' => 'abc']), 'discount', 'between 0.00 and 170.00');
        $this->assertSame('0.00', $this->orders->updateItem($item, $this->owner, ['discount' => '170'])->line_total);
    }

    public function test_a_discount_without_the_permission_is_blocked(): void
    {
        $item = $this->order([$this->line($this->redHorse, 2)])->items->sole();

        try {
            $this->orders->updateItem($item, $this->cashier, ['discount' => '10']);
            $this->fail('expected AuthorizationException');
        } catch (AuthorizationException) {
        }

        $this->assertSame(['0.00', '170.00'], [$item->fresh()->discount, $item->fresh()->line_total]);
        $this->assertSame(0, ActivityLog::where('action', 'order_item.updated')->count());
    }

    public function test_lowering_the_quantity_is_refused(): void
    {
        $item = $this->order([$this->line($this->redHorse, 3)])->items->sole();

        $this->assertRefused(fn () => $this->orders->updateItem($item, $this->owner, ['quantity' => 2]), 'quantity', 'Use cancel to remove items; it needs a reason.');
        $this->assertRefused(fn () => $this->orders->updateItem($item, $this->owner, ['quantity' => 100]), 'quantity', 'between 1 and 99');
        $this->assertSame(3, $item->fresh()->quantity);
    }

    public function test_a_partial_cancel_splits_the_line_and_the_discount_and_the_totals_sum(): void
    {
        $item = $this->order([$this->line($this->redHorse, 3)])->items->sole();
        $this->orders->updateItem($item, $this->owner, ['discount' => '10']);
        $before = $item->fresh()->line_total;

        $cancelled = $this->orders->cancelItem($item, $this->owner, '  spilled  ', 1);
        $original = $item->fresh();

        $this->assertSame('245.00', $before);
        $this->assertSame([2, '6.67', '163.33', OrderItemStatus::Active], [$original->quantity, $original->discount, $original->line_total, $original->status]);
        $this->assertNotSame($original->id, $cancelled->id);
        $this->assertSame([1, '3.33', '81.67', OrderItemStatus::Cancelled, 'spilled'], [$cancelled->quantity, $cancelled->discount, $cancelled->line_total, $cancelled->status, $cancelled->cancel_reason]);
        $this->assertSame(['Red Horse', '85.00', '52.50', $original->order_id, $original->product_id], [$cancelled->product_name, $cancelled->unit_price, $cancelled->unit_cost, $cancelled->order_id, $cancelled->product_id]);
        $this->assertSame($this->owner->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertNull($original->cancelled_at);
        $this->assertSame($before, bcadd($original->line_total, $cancelled->line_total, 2));
        $this->assertSame(OrderStatus::Open, Order::find($original->order_id)->status);

        $log = ActivityLog::where('action', 'order_item.cancelled')->sole();
        $this->assertSame($cancelled->id, $log->subject_id);
        $this->assertLogValues(['quantity' => 2, 'discount' => '6.67', 'line_total' => '163.33'], $log->new_values['remaining']);
        $this->assertLogValues(['quantity' => 1, 'reason' => 'spilled', 'line_total' => '81.67', 'split_from' => $original->id], array_diff_key($log->new_values, ['remaining' => 1]));
        $this->assertLogValues(['quantity' => 3, 'discount' => '10.00', 'line_total' => '245.00'], $log->old_values);
    }

    public function test_cancelling_every_item_cancels_the_order(): void
    {
        $order = $this->order([$this->line($this->redHorse, 2), $this->line($this->product('Sisig', '220'))]);
        [$beer, $sisig] = $order->items->all();

        $whole = $this->orders->cancelItem($beer, $this->owner, 'wrong order', 2);
        $this->assertSame([$beer->id, OrderItemStatus::Cancelled, '170.00'], [$whole->id, $whole->status, $whole->line_total]);
        $this->assertSame(OrderStatus::Open, $order->fresh()->status);

        $this->orders->cancelItem($sisig, $this->owner, 'kitchen closed');
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(2, OrderItem::count());
    }

    public function test_a_cancel_needs_a_reason_and_a_valid_quantity(): void
    {
        $item = $this->order([$this->line($this->redHorse, 2)])->items->sole();

        $this->assertRefused(fn () => $this->orders->cancelItem($item, $this->owner, '   '), 'reason', 'Give a reason');
        $this->assertRefused(fn () => $this->orders->cancelItem($item, $this->owner, str_repeat('x', 201)), 'reason', '200');
        $this->assertRefused(fn () => $this->orders->cancelItem($item, $this->owner, 'x', 3), 'quantity', 'between 1 and 2');
        $this->assertRefused(fn () => $this->orders->cancelItem($item, $this->owner, 'x', 0), 'quantity', 'between 1 and 2');
        $this->assertSame([OrderItemStatus::Active, 1], [$item->fresh()->status, OrderItem::count()]);
    }

    public function test_a_cashier_without_cancel_permission_is_blocked_and_nothing_changes(): void
    {
        $item = $this->order([$this->line($this->redHorse, 2)])->items->sole();

        try {
            $this->orders->cancelItem($item, $this->cashier, 'changed mind', 1);
            $this->fail('expected AuthorizationException');
        } catch (AuthorizationException) {
        }

        $this->assertSame([OrderItemStatus::Active, 2, '170.00'], [$item->fresh()->status, $item->fresh()->quantity, $item->fresh()->line_total]);
        $this->assertSame(1, OrderItem::count());
        $this->assertSame(0, ActivityLog::where('action', 'order_item.cancelled')->count());
        $this->assertSame(1, count($this->stock->calls));

        $this->assertSame(OrderItemStatus::Cancelled, $this->orders->cancelItem($item, $this->supervisorWith('orders.cancel-item'), 'changed mind')->status);
    }

    public function test_changes_on_a_cancelled_item_are_refused(): void
    {
        $item = $this->order([$this->line($this->redHorse, 2), $this->line($this->redHorse)])->items->first();
        $this->orders->cancelItem($item, $this->owner, 'spilled');

        $this->assertRefused(fn () => $this->orders->updateItem($item, $this->owner, ['quantity' => 5]), 'item', 'This item is cancelled.');
        $this->assertRefused(fn () => $this->orders->cancelItem($item, $this->owner, 'again'), 'item', 'This item is cancelled.');
        $this->assertSame([2, 1], [$item->fresh()->quantity, ActivityLog::where('action', 'order_item.cancelled')->count()]);
    }

    public function test_changes_on_a_cancelled_or_closed_tab_are_refused(): void
    {
        $item = $this->order([$this->line($this->redHorse, 2)])->items->sole();
        $this->orders->cancelItem($item, $this->owner, 'walked out');
        app(TableSessionService::class)->cancel($this->session, $this->owner, 'walked out');

        $this->assertRefused(fn () => $this->order([$this->line($this->redHorse)]), 'session', 'This tab is closed.');
        $this->assertRefused(fn () => $this->orders->updateItem($item, $this->owner, ['notes' => 'x']), 'session', 'This tab is closed.');
        $this->assertRefused(fn () => $this->orders->cancelItem($item, $this->owner, 'x'), 'session', 'This tab is closed.');

        $table = app(DiningTableService::class)->create(['label' => 'T2', 'capacity' => 2]);
        $this->session = app(TableSessionService::class)->open($table, $this->cashier);
        $live = $this->order([$this->line($this->redHorse, 2)])->items->sole();
        $this->session->forceFill(['status' => SessionStatus::Closed])->save();

        $this->assertRefused(fn () => $this->order([$this->line($this->redHorse)]), 'session', 'This tab is closed.');
        $this->assertRefused(fn () => $this->orders->updateItem($live, $this->owner, ['quantity' => 3]), 'session', 'This tab is closed.');
        $this->assertRefused(fn () => $this->orders->cancelItem($live, $this->owner, 'x'), 'session', 'This tab is closed.');
        $this->assertSame([2, 2], [Order::count(), $live->fresh()->quantity]);
    }

    public function test_stock_hook_is_called_inside_the_transaction(): void
    {
        $order = $this->order([$this->line($this->redHorse, 3), $this->line($this->product('Sisig', '220'), 2)]);
        $cancelled = $this->orders->cancelItem($order->items->first(), $this->owner, 'spilled', 2);
        $sisig = $order->items[1];
        $this->orders->updateItem($sisig, $this->owner, ['quantity' => 3]);
        $this->orders->updateItem($sisig, $this->owner, ['discount' => '10', 'notes' => 'no onions']);

        $this->assertSame([
            ['deduct', $order->id, 2, true],
            ['cancelled', $cancelled->id, 2, true],
            ['added', $sisig->id, 1, true],
        ], $this->stock->calls);
    }

    private function fullFlow(): void
    {
        $order = $this->order([$this->line($this->redHorse, 2)]);
        $this->orders->updateItem($order->items->sole(), $this->cashier, ['quantity' => 4]);
        $this->orders->cancelItem($order->items->sole(), $this->owner, 'spilled', 1);
        $this->order([$this->line($this->redHorse)]);
    }

    public function test_after_the_full_flow_every_order_is_unpaid(): void
    {
        $this->fullFlow();

        $this->assertSame([PaymentStatus::Unpaid, PaymentStatus::Unpaid], Order::orderBy('id')->get()->pluck('payment_status')->all());
    }

    public function test_every_write_leaves_its_activity_log_row(): void
    {
        $this->fullFlow();

        $this->assertSame(
            ['session.opened', 'order.created', 'order_item.updated', 'order_item.cancelled', 'order.created'],
            ActivityLog::where('action', 'like', 'session.%')->orWhere('action', 'like', 'order%')->orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame([$this->cashier->id, $this->cashier->id, $this->cashier->id, $this->owner->id, $this->cashier->id],
            ActivityLog::where('action', 'like', 'session.%')->orWhere('action', 'like', 'order%')->orderBy('id')->pluck('user_id')->all());
    }
}

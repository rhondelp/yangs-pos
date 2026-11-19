<?php

namespace Tests\Feature\Corrections;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderVoid;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\VoidService;
use App\Services\Orders\OrderService;
use App\Services\Orders\RunningBill;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VoidServiceTest extends TestCase
{
    use RefreshDatabase;

    private VoidService $voids;

    private User $owner;

    private User $cashier;

    private DiningTable $table;

    private TableSession $session;

    private Order $order;

    private OrderItem $beer; // 3 × ₱85 less ₱10 = ₱245.00

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        Role::findByName('Cashier')->givePermissionTo('orders.void');
        $this->actingAs($this->owner);
        $this->voids = app(VoidService::class);

        $beer = Category::create(['name' => 'Beer']);
        $product = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        $this->table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($this->table, $this->cashier);
        $this->order = app(OrderService::class)->addOrder($this->session, $this->cashier, [['product_id' => $product->id, 'quantity' => 3]]);
        $this->beer = $this->order->items->sole();
        app(OrderService::class)->updateItem($this->beer, $this->owner, ['discount' => '10']);
    }

    private function pay(string $amount): void
    {
        app(PaymentService::class)->record($this->session, $this->cashier, [
            'method_id' => PaymentMethod::where('code', 'cash')->value('id'), 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function bill(): array
    {
        return app(RunningBill::class)->calculate($this->session->fresh());
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

    public function test_an_unpaid_tab_uses_cancel_not_void(): void
    {
        $this->assertRefused(fn () => $this->voids->voidItem($this->beer, $this->cashier, 1, 'Wrong drink'), 'session', 'No payment yet: cancel the item instead.');
    }

    public function test_a_void_needs_the_permission_a_reason_and_a_valid_quantity(): void
    {
        $this->pay('50');

        $this->assertRefused(fn () => $this->voids->voidItem($this->beer, $this->cashier, 1, '  '), 'reason', 'Give a reason for the void.');
        $this->assertRefused(fn () => $this->voids->voidItem($this->beer, $this->cashier, 0, 'Wrong drink'), 'quantity', 'You can void between 1 and 3.');
        $this->assertRefused(fn () => $this->voids->voidItem($this->beer, $this->cashier, 4, 'Wrong drink'), 'quantity', 'You can void between 1 and 3.');

        Role::findByName('Cashier')->revokePermissionTo('orders.void');
        $this->expectException(AuthorizationException::class);
        $this->voids->voidItem($this->beer, $this->cashier->fresh(), 1, 'Wrong drink');
    }

    public function test_a_partial_void_lowers_the_bill_and_leaves_the_item_row_untouched(): void
    {
        $this->pay('50');
        $before = $this->beer->fresh()->getAttributes();

        $void = $this->voids->voidItem($this->beer, $this->cashier, 1, 'Spilled');

        $this->assertSame('81.67', $void->amount); // ₱85 less its ₱3.33 share of the ₱10 discount
        $bill = $this->bill();
        $this->assertSame(['170.00', '6.67', '163.33', '81.67', 2], [$bill['gross'], $bill['discounts'], $bill['total'], $bill['voided'], $bill['item_count']]);
        $this->assertSame([['id' => $void->id, 'product_name' => 'Red Horse', 'quantity' => 1, 'amount' => '81.67', 'reason' => 'Spilled']], $bill['voids']);
        $this->assertSame($before, $this->beer->fresh()->getAttributes());

        $log = ActivityLog::where('action', 'order_item.voided')->sole();
        $this->assertEquals(['quantity' => 1, 'amount' => '81.67', 'reason' => 'Spilled', 'total' => '163.33'], $log->new_values);
    }

    public function test_two_part_voids_of_a_discounted_line_equal_one_void(): void
    {
        $this->pay('50');

        $this->voids->voidItem($this->beer, $this->cashier, 1, 'Spilled');
        $this->voids->voidItem($this->beer, $this->cashier, 1, 'Spilled');

        // One unit left: ₱85 less ₱3.33 of the discount, exactly as if two had been voided at once.
        $this->assertSame('81.67', $this->bill()['total']);
        $this->assertSame('163.33', OrderVoid::sum('amount'));
        $this->assertRefused(fn () => $this->voids->voidItem($this->beer, $this->cashier, 2, 'Spilled'), 'quantity', 'You can void between 1 and 1.');
    }

    public function test_a_void_cannot_take_the_bill_below_what_was_paid(): void
    {
        $this->pay('200');

        $this->assertRefused(fn () => $this->voids->voidItem($this->beer, $this->cashier, 1, 'Spilled'), 'quantity', 'This void would take the bill below the ₱200.00 already paid.');
        $this->assertSame(0, OrderVoid::count());
    }

    public function test_a_void_that_reaches_what_was_paid_closes_the_tab(): void
    {
        $this->pay('163.33');

        $this->voids->voidItem($this->beer, $this->cashier, 1, 'Spilled');

        $session = $this->session->fresh();
        $this->assertSame(SessionStatus::Closed, $session->status);
        $this->assertSame(['170.00', '6.67', '163.33'], [$session->gross_total, $session->discount_total, $session->net_total]);
        $this->assertNotNull($session->receipt_number);
        $this->assertSame(TableStatus::Available, $this->table->fresh()->status);
    }

    public function test_voiding_a_whole_order_voids_every_unit_left(): void
    {
        $this->pay('50');
        $this->assertRefused(fn () => $this->voids->voidOrder($this->order, $this->cashier, 'Wrong table'), 'quantity', 'This void would take the bill below the ₱50.00 already paid.');

        $other = app(OrderService::class)->addOrder($this->session, $this->cashier, [['product_id' => $this->beer->product_id, 'quantity' => 2]]);
        $this->voids->voidItem($other->items->sole(), $this->cashier, 1, 'Spilled');

        $voids = $this->voids->voidOrder($other, $this->cashier, 'Wrong table');

        $this->assertCount(1, $voids);
        $this->assertSame(1, $voids->sole()->quantity);
        $this->assertSame('245.00', $this->bill()['total']);
        $this->assertSame(1, ActivityLog::where('action', 'order.voided')->count());
        $this->assertRefused(fn () => $this->voids->voidOrder($other, $this->cashier, 'Again'), 'order', 'Nothing is left to void on this order.');
    }

    public function test_a_closed_tab_cannot_be_voided(): void
    {
        $this->pay('245');

        $this->assertRefused(fn () => $this->voids->voidItem($this->beer, $this->cashier, 1, 'Late'), 'session', 'This tab is closed.');
    }

    public function test_voids_are_append_only(): void
    {
        $this->pay('50');
        $void = $this->voids->voidItem($this->beer, $this->cashier, 1, 'Spilled');

        try {
            $void->forceFill(['quantity' => 2])->save();
            $this->fail('model allowed an edit');
        } catch (\LogicException) {
        }

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('order_voids')->where('id', $void->id)->delete();
    }
}

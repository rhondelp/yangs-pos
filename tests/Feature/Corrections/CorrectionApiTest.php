<?php

namespace Tests\Feature\Corrections;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CorrectionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private TableSession $session;

    private Order $order;

    private Payment $payment; // ₱50 cash on a ₱170 tab

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($this->owner);

        $beer = Category::create(['name' => 'Beer']);
        $product = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        $table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($table, $this->cashier);
        $this->order = app(OrderService::class)->addOrder($this->session, $this->cashier, [['product_id' => $product->id, 'quantity' => 2]]);
        $this->payment = $this->pay('50');
    }

    private function pay(string $amount): Payment
    {
        return app(PaymentService::class)->record($this->session, $this->owner, [
            'method_id' => PaymentMethod::where('code', 'cash')->value('id'), 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    public function test_every_route_needs_its_permission(): void
    {
        $item = $this->order->items->sole();
        $this->actingAs($this->cashier, 'sanctum');

        $this->postJson("/api/v1/order-items/{$item->id}/void", ['quantity' => 1, 'reason' => 'x'])->assertForbidden();
        $this->postJson("/api/v1/orders/{$this->order->id}/void", ['reason' => 'x'])->assertForbidden();
        $this->postJson("/api/v1/payments/{$this->payment->id}/refunds", ['amount' => '1', 'reason' => 'x', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        $this->postJson("/api/v1/payments/{$this->payment->id}/corrections", ['method_id' => 1, 'reason' => 'x'])->assertForbidden();
    }

    public function test_voids_need_a_reason_and_return_the_record_and_the_bill(): void
    {
        $item = $this->order->items->sole();
        Role::findByName('Cashier')->givePermissionTo('orders.void');
        $this->actingAs($this->cashier, 'sanctum');

        $this->postJson("/api/v1/order-items/{$item->id}/void", ['quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/order-items/{$item->id}/void", ['quantity' => 1, 'reason' => 'Spilled', 'idempotency_key' => (string) Str::uuid()])
            ->assertCreated()
            ->assertJsonPath('data.quantity', 1)
            ->assertJsonPath('data.amount', '85.00')
            ->assertJsonPath('bill.bill.total', '85.00')
            ->assertJsonPath('bill.bill.voided', '85.00');

        $other = app(OrderService::class)->addOrder($this->session, $this->owner, [['product_id' => $item->product_id, 'quantity' => 1]]);
        $this->postJson("/api/v1/orders/{$other->id}/void", ['reason' => 'Wrong table', 'idempotency_key' => (string) Str::uuid()])
            ->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('bill.bill.total', '85.00');
    }

    public function test_a_refund_is_created_once_and_replays_with_200(): void
    {
        $this->pay('120'); // closes the tab
        Role::findByName('Cashier')->givePermissionTo('payments.refund');
        $this->actingAs($this->cashier, 'sanctum');
        $body = ['amount' => '20', 'reason' => 'Warm beer', 'idempotency_key' => (string) Str::uuid()];

        $this->postJson("/api/v1/payments/{$this->payment->id}/refunds", ['amount' => '51', 'reason' => 'x', 'idempotency_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $id = $this->postJson("/api/v1/payments/{$this->payment->id}/refunds", $body)
            ->assertCreated()
            ->assertJsonPath('data.amount', '20.00')
            ->assertJsonPath('data.method', 'Cash')
            ->assertJsonPath('bill.bill.refunded', '20.00')
            ->json('data.id');
        $this->postJson("/api/v1/payments/{$this->payment->id}/refunds", $body)->assertOk()->assertJsonPath('data.id', $id);
    }

    public function test_a_correction_returns_the_effective_method(): void
    {
        Role::findByName('Cashier')->givePermissionTo('corrections.create');
        $gcash = PaymentMethod::where('code', 'gcash')->value('id');

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/payments/{$this->payment->id}/corrections", ['method_id' => $gcash, 'reference_no' => 'GC-9', 'reason' => 'Wrong button'])
            ->assertCreated()
            ->assertJsonPath('data.method', 'GCash')
            ->assertJsonPath('bill.bill.payments.0.method', 'GCash')
            ->assertJsonPath('bill.bill.payments.0.corrected', true);
    }
}

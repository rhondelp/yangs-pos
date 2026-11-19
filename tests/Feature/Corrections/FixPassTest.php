<?php

namespace Tests\Feature\Corrections;

use App\Enums\SessionStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderVoid;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Refund;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\BillService;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\ApprovalService;
use App\Services\Corrections\PaymentCorrectionService;
use App\Services\Corrections\RefundService;
use App\Services\Corrections\VoidService;
use App\Services\Orders\OrderService;
use App\Services\Orders\RunningBill;
use App\Services\Orders\TableSessionService;
use App\Services\Settings\SettingService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Findings from the Step 12 final review, each pinned by a test. */
class FixPassTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private User $otherCashier;

    private Product $redHorse;

    private PaymentMethod $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create(['username' => 'boss'])->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->otherCashier = User::factory()->create()->assignRole('Cashier');
        Role::findByName('Cashier')->givePermissionTo(['payments.refund', 'corrections.create', 'orders.void']);
        $this->actingAs($this->owner);
        $this->cash = PaymentMethod::where('code', 'cash')->sole();
        $beer = Category::create(['name' => 'Beer']);
        $this->redHorse = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
    }

    /** Opens a tab by $by with $quantity × ₱85. */
    private function tab(User $by, int $quantity = 2, string $label = 'T1'): TableSession
    {
        $table = app(DiningTableService::class)->create(['label' => $label, 'capacity' => 4]);
        $session = app(TableSessionService::class)->open($table, $by);
        app(OrderService::class)->addOrder($session, $by, [['product_id' => $this->redHorse->id, 'quantity' => $quantity]]);

        return $session;
    }

    private function pay(TableSession $session, ?string $amount, User $by): Payment
    {
        return app(PaymentService::class)->record($session, $by, ['method_id' => $this->cash->id, 'amount' => $amount, 'idempotency_key' => (string) Str::uuid()]);
    }

    public function test_refunds_and_corrections_are_limited_to_tabs_the_user_may_see(): void
    {
        $session = $this->tab($this->otherCashier);
        $payment = $this->pay($session, null, $this->otherCashier);
        $body = ['amount' => '10', 'reason' => 'x', 'idempotency_key' => (string) Str::uuid()];
        $fix = ['method_id' => PaymentMethod::where('code', 'gcash')->value('id'), 'reference_no' => 'GC-1', 'reason' => 'x'];

        $this->actingAs($this->cashier)->post("/transactions/payments/{$payment->id}/refunds", $body)->assertNotFound();
        $this->actingAs($this->cashier)->post("/transactions/payments/{$payment->id}/corrections", $fix)->assertNotFound();
        $this->actingAs($this->cashier, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/refunds", $body)->assertNotFound();
        $this->actingAs($this->cashier, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/corrections", $fix)->assertNotFound();
        $this->assertSame(0, Refund::count() + PaymentCorrection::count());

        $this->actingAs($this->otherCashier, 'sanctum')->postJson("/api/v1/payments/{$payment->id}/refunds", $body)->assertCreated();
    }

    public function test_approval_pins_are_never_flashed_into_the_session(): void
    {
        app(ApprovalService::class)->setPin($this->owner, '4321');
        app(SettingService::class)->set(['require_approval_pin' => true]);
        $session = $this->tab($this->owner);
        $payment = $this->pay($session, null, $this->owner);

        $this->actingAs($this->owner)->from("/transactions/{$session->id}")
            ->post("/transactions/payments/{$payment->id}/refunds", ['amount' => '999', 'reason' => 'x', 'idempotency_key' => (string) Str::uuid(), 'approver_username' => 'boss', 'approver_pin' => '4321'])
            ->assertSessionHasErrors('amount')
            ->assertSessionHasInput('approver_username', 'boss')
            ->assertSessionMissing('_old_input.approver_pin');

        $this->actingAs($this->owner)->put('/profile/approval-pin', ['approval_pin' => '12', 'approval_pin_confirmation' => '12'])
            ->assertSessionMissing('_old_input.approval_pin')
            ->assertSessionMissing('_old_input.approval_pin_confirmation');
    }

    public function test_the_quantity_of_a_line_with_voids_cannot_change(): void
    {
        $session = $this->tab($this->owner, 3);
        $item = $session->items()->sole();
        $this->pay($session, '50', $this->owner);
        app(VoidService::class)->voidItem($item, $this->owner, 1, 'Spilled');

        try {
            app(OrderService::class)->updateItem($item, $this->owner, ['quantity' => 4]);
            $this->fail('quantity changed on a voided line');
        } catch (ValidationException $e) {
            $this->assertSame('Units of this item were voided. Add a new order instead.', $e->errors()['quantity'][0]);
        }
        $this->assertSame('85.00', app(RunningBill::class)->calculate($session->fresh())['voided']);
    }

    public function test_a_resent_void_is_recorded_once(): void
    {
        $session = $this->tab($this->owner, 3);
        $item = $session->items()->sole();
        $this->pay($session, '50', $this->owner);
        $key = (string) Str::uuid();

        $first = app(VoidService::class)->voidItem($item, $this->owner, 1, 'Spilled', [], $key);
        $again = app(VoidService::class)->voidItem($item, $this->owner, 1, 'Spilled', [], $key);
        $this->assertTrue($first->is($again));

        $order = app(OrderService::class)->addOrder($session, $this->owner, [['product_id' => $this->redHorse->id, 'quantity' => 1]]);
        $orderKey = (string) Str::uuid();
        app(VoidService::class)->voidOrder($order, $this->owner, 'Wrong table', [], $orderKey);
        $this->assertCount(1, app(VoidService::class)->voidOrder($order, $this->owner, 'Wrong table', [], $orderKey));
        $this->assertSame(2, OrderVoid::count());

        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/order-items/{$item->id}/void", ['quantity' => 1, 'reason' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
    }

    public function test_the_tab_view_shows_items_net_of_voids(): void
    {
        $session = $this->tab($this->owner, 3);
        $this->pay($session, '50', $this->owner);
        app(VoidService::class)->voidItem($session->items()->sole(), $this->owner, 1, 'Spilled');

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.orders.0.items.0.quantity', 3)
            ->assertJsonPath('data.orders.0.items.0.voided_quantity', 1)
            ->assertJsonPath('data.orders.0.items.0.net_total', '170.00');
    }

    public function test_voids_follow_a_bill_discount_and_can_close_the_tab(): void
    {
        $session = $this->tab($this->owner, 3); // ₱255
        app(BillService::class)->applyDiscount($session, $this->owner, 'percent', '10', 'Regular'); // total ₱229.50
        $this->pay($session, '153', $this->owner);

        app(VoidService::class)->voidItem($session->items()->sole(), $this->owner, 1, 'Spilled'); // ₱170 less 10% = ₱153

        $session->refresh();
        $this->assertSame(SessionStatus::Closed, $session->status);
        $this->assertSame(['170.00', '17.00', '153.00'], [$session->gross_total, $session->discount_total, $session->net_total]);
    }

    public function test_the_approver_is_stored_on_every_record_and_log(): void
    {
        app(ApprovalService::class)->setPin($this->owner, '4321');
        app(SettingService::class)->set(['require_approval_pin' => true]);
        $approval = ['approver_username' => 'boss', 'approver_pin' => '4321'];
        $session = $this->tab($this->cashier, 3);
        $payment = $this->pay($session, '50', $this->cashier);

        $void = app(VoidService::class)->voidItem($session->items()->sole(), $this->cashier, 1, 'Spilled', $approval);
        $correction = app(PaymentCorrectionService::class)->correct($payment, $this->cashier, ['method_id' => $this->cash->id, 'reference_no' => null, 'reason' => 'x', ...$approval, 'method_id' => PaymentMethod::where('code', 'gcash')->value('id'), 'reference_no' => 'GC-1']);
        $this->pay($session, null, $this->cashier);
        $refund = app(RefundService::class)->refund($payment, $this->cashier, ['amount' => '10', 'reason' => 'x', 'reference_no' => 'R-1', 'idempotency_key' => (string) Str::uuid(), ...$approval]);

        foreach ([$void, $correction, $refund] as $record) {
            $this->assertSame($this->owner->id, $record->approved_by, $record::class);
        }
        $this->assertSame([$this->owner->id], ActivityLog::whereIn('action', ['order_item.voided', 'payment.corrected', 'payment.refunded'])->pluck('approved_by')->unique()->values()->all());
    }
}

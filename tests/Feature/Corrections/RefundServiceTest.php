<?php

namespace Tests\Feature\Corrections;

use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\RefundService;
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

class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $refunds;

    private User $owner;

    private User $cashier;

    private TableSession $session;

    private PaymentMethod $cash;

    private PaymentMethod $gcash;

    private Payment $gcashPayment; // ₱70.00, ref GC-1

    private Payment $cashPayment; // ₱100.00

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        Role::findByName('Cashier')->givePermissionTo('payments.refund');
        $this->actingAs($this->owner);
        $this->refunds = app(RefundService::class);
        $this->cash = PaymentMethod::where('code', 'cash')->sole();
        $this->gcash = PaymentMethod::where('code', 'gcash')->sole();

        $beer = Category::create(['name' => 'Beer']);
        $product = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        $table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($table, $this->cashier);
        app(OrderService::class)->addOrder($this->session, $this->cashier, [['product_id' => $product->id, 'quantity' => 2]]); // ₱170.00
        $this->gcashPayment = $this->pay($this->gcash, '70', ['reference_no' => 'GC-1']);
    }

    private function pay(PaymentMethod $method, string $amount, array $extra = []): Payment
    {
        return app(PaymentService::class)->record($this->session, $this->cashier, [
            'method_id' => $method->id, 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(), ...$extra,
        ]);
    }

    private function close(): void
    {
        $this->cashPayment = $this->pay($this->cash, '100');
    }

    private function refund(Payment $payment, string $amount, array $extra = [], ?User $by = null): Refund
    {
        return $this->refunds->refund($payment, $by ?? $this->cashier, [
            'amount' => $amount, 'reason' => 'Warm beer', 'idempotency_key' => (string) Str::uuid(), ...$extra,
        ]);
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

    public function test_only_closed_tabs_can_be_refunded(): void
    {
        $this->assertRefused(fn () => $this->refund($this->gcashPayment, '10', ['reference_no' => 'R1']), 'session', 'Only a paid, closed tab can be refunded. Void items while the tab is open.');
    }

    public function test_a_refund_cannot_exceed_what_is_left_of_the_payment(): void
    {
        $this->close();

        $this->assertRefused(fn () => $this->refund($this->cashPayment, '100.01'), 'amount', 'The most this payment can refund is ₱100.00.');
        $this->refund($this->cashPayment, '60');
        $this->assertRefused(fn () => $this->refund($this->cashPayment, '40.01'), 'amount', 'The most this payment can refund is ₱40.00.');
        $this->refund($this->cashPayment, '40');
        $this->assertRefused(fn () => $this->refund($this->cashPayment, '1'), 'amount', 'This payment is already fully refunded.');
    }

    public function test_a_refund_needs_a_reason_a_valid_amount_and_a_reference_when_the_method_asks(): void
    {
        $this->close();

        $this->assertRefused(fn () => $this->refund($this->cashPayment, '10', ['reason' => ' ']), 'reason', 'Give a reason for the refund.');
        $this->assertRefused(fn () => $this->refund($this->cashPayment, '0'), 'amount');
        $this->assertRefused(fn () => $this->refund($this->cashPayment, '1.234'), 'amount');
        $this->assertRefused(fn () => $this->refund($this->gcashPayment, '10'), 'reference_no', 'Enter the GCash reference number.');

        // A GCash payment refunded in cash needs no reference.
        $refund = $this->refund($this->gcashPayment, '10', ['method_id' => $this->cash->id]);
        $this->assertSame('Cash', $refund->method->name);
    }

    public function test_partial_then_full_refund_sets_the_payment_status(): void
    {
        $this->close();

        $this->refund($this->gcashPayment, '70', ['reference_no' => 'R1']);
        $this->assertSame([PaymentStatus::PartiallyRefunded], Order::pluck('payment_status')->unique()->values()->all());

        $refund = $this->refund($this->cashPayment, '100');
        $this->assertSame([PaymentStatus::Refunded], Order::pluck('payment_status')->unique()->values()->all());

        $bill = app(RunningBill::class)->calculate($this->session->fresh());
        $this->assertSame(['170.00', '170.00'], [$bill['paid'], $bill['refunded']]);
        $this->assertSame('100.00', $bill['payments'][1]['refunded']);
        $this->assertSame([['id' => $refund->id, 'method' => 'Cash', 'amount' => '100.00', 'reference_no' => null, 'reason' => 'Warm beer', 'refunded_at' => $refund->created_at->toIso8601String()]], $bill['payments'][1]['refunds']);

        $this->assertEquals(['payment' => $this->cashPayment->id, 'method' => 'Cash', 'amount' => '100.00', 'reason' => 'Warm beer'],
            ActivityLog::where('action', 'payment.refunded')->latest('id')->first()->new_values);
    }

    public function test_a_resent_refund_is_recorded_once(): void
    {
        $this->close();
        $key = (string) Str::uuid();

        $first = $this->refund($this->cashPayment, '30', ['idempotency_key' => $key]);
        $again = $this->refund($this->cashPayment, '30', ['idempotency_key' => $key]);

        $this->assertTrue($first->is($again));
        $this->assertSame(1, Refund::count());
    }

    public function test_the_payment_is_never_changed_and_refunds_are_append_only(): void
    {
        $this->close();
        $before = $this->cashPayment->fresh()->getAttributes();

        $refund = $this->refund($this->cashPayment, '30');

        $this->assertSame($before, $this->cashPayment->fresh()->getAttributes());
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('refunds')->where('id', $refund->id)->update(['amount' => '1']);
    }

    public function test_a_corrected_payment_refunds_by_its_corrected_method(): void
    {
        $this->close();
        $this->owner->givePermissionTo('corrections.create');
        app(\App\Services\Corrections\PaymentCorrectionService::class)->correct($this->cashPayment, $this->owner, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-9', 'reason' => 'Wrong button']);

        $this->assertRefused(fn () => $this->refund($this->cashPayment, '10'), 'reference_no', 'Enter the GCash reference number.');
        $this->assertSame('GCash', $this->refund($this->cashPayment, '10', ['reference_no' => 'R-1'])->method->name);
    }

    public function test_refunds_need_the_permission(): void
    {
        $this->close();
        Role::findByName('Cashier')->revokePermissionTo('payments.refund');

        $this->expectException(AuthorizationException::class);
        $this->refund($this->cashPayment, '10', by: $this->cashier->fresh());
    }
}

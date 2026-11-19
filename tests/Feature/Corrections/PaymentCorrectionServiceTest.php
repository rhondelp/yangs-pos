<?php

namespace Tests\Feature\Corrections;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\PaymentMethod;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Billing\ReceiptService;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\PaymentCorrectionService;
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

class PaymentCorrectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentCorrectionService $corrections;

    private User $cashier;

    private TableSession $session;

    private PaymentMethod $cash;

    private PaymentMethod $gcash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        Role::findByName('Cashier')->givePermissionTo('corrections.create');
        $this->actingAs($owner);
        $this->corrections = app(PaymentCorrectionService::class);
        $this->cash = PaymentMethod::where('code', 'cash')->sole();
        $this->gcash = PaymentMethod::where('code', 'gcash')->sole();

        $beer = Category::create(['name' => 'Beer']);
        $product = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        $table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($table, $this->cashier);
        app(OrderService::class)->addOrder($this->session, $this->cashier, [['product_id' => $product->id, 'quantity' => 2]]); // ₱170.00
    }

    private function pay(PaymentMethod $method, string $amount, array $extra = []): Payment
    {
        return app(PaymentService::class)->record($this->session, $this->cashier, [
            'method_id' => $method->id, 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(), ...$extra,
        ]);
    }

    private function correct(Payment $payment, array $input, ?User $by = null): PaymentCorrection
    {
        return $this->corrections->correct($payment, $by ?? $this->cashier, ['reason' => 'Wrong button', ...$input]);
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

    public function test_a_cash_payment_is_corrected_to_gcash_and_the_bill_and_receipt_follow(): void
    {
        $this->pay($this->gcash, '70', ['reference_no' => 'GC-1']);
        $payment = $this->pay($this->cash, '100'); // closes the tab, exact cash
        $before = $payment->fresh()->getAttributes();

        $this->correct($payment, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-9']);

        $this->assertSame($before, $payment->fresh()->getAttributes());
        $entry = app(RunningBill::class)->calculate($this->session->fresh())['payments'][1];
        $this->assertSame(['GCash', 'GC-9', true], [$entry['method'], $entry['reference_no'], $entry['corrected']]);
        $this->assertSame('GCash', app(ReceiptService::class)->data($this->session->fresh())['payments'][1]['method']);

        $log = ActivityLog::where('action', 'payment.corrected')->sole();
        $this->assertEquals(['method' => 'Cash', 'reference_no' => null], $log->old_values);
        $this->assertEquals(['method' => 'GCash', 'reference_no' => 'GC-9', 'reason' => 'Wrong button'], $log->new_values);
    }

    public function test_the_latest_correction_wins(): void
    {
        $payment = $this->pay($this->gcash, '70', ['reference_no' => 'GC-1']);

        $this->correct($payment, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-2']);
        $this->correct($payment, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-3']);

        $this->assertSame('GC-3', app(RunningBill::class)->calculate($this->session->fresh())['payments'][0]['reference_no']);
        $this->assertRefused(fn () => $this->correct($payment, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-3']), 'method_id', 'Nothing to correct: that is already the method and reference.');
    }

    public function test_the_new_method_rules_apply(): void
    {
        $payment = $this->pay($this->cash, '50', ['tendered' => '100']); // ₱50 change given

        $this->assertRefused(fn () => $this->correct($payment, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-9']), 'method_id', 'Change was given, so this was a cash payment.');

        $exact = $this->pay($this->cash, '20');
        $this->assertRefused(fn () => $this->correct($exact, ['method_id' => $this->gcash->id]), 'reference_no', 'Enter the GCash reference number.');
        $this->assertRefused(fn () => $this->correct($exact, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-9', 'reason' => '']), 'reason', 'Give a reason for the correction.');
    }

    public function test_corrections_are_append_only_and_need_the_permission(): void
    {
        $payment = $this->pay($this->gcash, '70', ['reference_no' => 'GC-1']);
        $correction = $this->correct($payment, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-2']);

        Role::findByName('Cashier')->revokePermissionTo('corrections.create');
        try {
            $this->correct($payment, ['method_id' => $this->cash->id], $this->cashier->fresh());
            $this->fail('allowed without corrections.create');
        } catch (AuthorizationException) {
        }

        // Last: a refused statement aborts the test's PostgreSQL transaction.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('payment_corrections')->where('id', $correction->id)->delete();
    }
}

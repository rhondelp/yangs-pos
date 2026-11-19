<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\BillService;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Orders\OrderService;
use App\Services\Orders\RunningBill;
use App\Services\Orders\TableSessionService;
use App\Services\Settings\SettingService;
use App\Services\Tables\DiningTableService;
use App\Support\BusinessDate;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    private BillService $bills;

    private User $owner;

    private User $cashier;

    private DiningTable $table;

    private TableSession $session;

    private Product $redHorse;

    private PaymentMethod $cash;

    private PaymentMethod $gcash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($this->owner);

        $this->payments = app(PaymentService::class);
        $this->bills = app(BillService::class);
        $this->cash = PaymentMethod::where('code', 'cash')->sole();
        $this->gcash = PaymentMethod::where('code', 'gcash')->sole();

        $beer = Category::create(['name' => 'Beer']);
        $this->redHorse = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        $this->table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($this->table, $this->cashier);
        $this->order(2); // ₱170.00
    }

    private function order(int $quantity): Order
    {
        return app(OrderService::class)->addOrder($this->session, $this->cashier, [['product_id' => $this->redHorse->id, 'quantity' => $quantity]]);
    }

    private function pay(PaymentMethod $method, ?string $amount = null, array $extra = [], ?User $by = null): Payment
    {
        return $this->payments->record($this->session, $by ?? $this->cashier, [
            'method_id' => $method->id, 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(), ...$extra,
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

    public function test_a_partial_payment_never_marks_the_tab_paid(): void
    {
        $payment = $this->pay($this->cash, '100', ['tendered' => '100']);

        $this->assertSame('100.00', $payment->amount);
        $this->assertSame('0.00', $payment->change_given);
        $session = $this->session->fresh();
        $this->assertTrue($session->status->isOpen());
        $this->assertNull($session->receipt_number);
        $this->assertSame(['paid' => '100.00', 'balance' => '70.00'], array_intersect_key($this->bill(), ['paid' => 0, 'balance' => 0]));
        $this->assertSame([PaymentStatus::Partial], Order::pluck('payment_status')->unique()->values()->all());
        $this->assertNotSame(TableStatus::Available, $this->table->fresh()->status);
    }

    public function test_split_payments_close_the_tab_once_they_cover_the_total(): void
    {
        $this->bills->present($this->session, $this->cashier);
        $this->assertSame(SessionStatus::Billing, $this->session->fresh()->status);
        $this->assertSame(TableStatus::AwaitingPayment, $this->table->fresh()->status);

        $this->pay($this->gcash, '100', ['reference_no' => ' 1012345678901 ']);
        $cash = $this->pay($this->cash, null, ['tendered' => '100']);

        $this->assertSame(['70.00', '100.00', '30.00'], [$cash->amount, $cash->tendered, $cash->change_given]);
        $this->assertSame('1012345678901', Payment::where('payment_method_id', $this->gcash->id)->value('reference_no'));

        $session = $this->session->fresh();
        $this->assertSame(SessionStatus::Closed, $session->status);
        $this->assertSame(['170.00', '0.00', '170.00'], [$session->gross_total, $session->discount_total, $session->net_total]);
        $this->assertSame('RCP-'.str_replace('-', '', BusinessDate::for(now())).'-0001', $session->receipt_number);
        $this->assertSame($this->cashier->id, $session->closed_by);
        $this->assertNotNull($session->closed_at);
        $this->assertSame([PaymentStatus::Paid], Order::pluck('payment_status')->unique()->values()->all());
        $this->assertSame(TableStatus::Available, $this->table->fresh()->status);
        $this->assertSame('170.00', $this->bill()['paid']);
        $this->assertSame(2, ActivityLog::where('action', 'payment.recorded')->count());
        $this->assertEquals(['receipt_number' => $session->receipt_number, 'total' => '170.00', 'paid' => '170.00'], // jsonb reorders keys
            array_intersect_key(ActivityLog::where('action', 'session.closed')->sole()->new_values, ['receipt_number' => 0, 'total' => 0, 'paid' => 0]));
    }

    public function test_cash_change_is_what_was_handed_over_minus_the_amount(): void
    {
        $this->assertRefused(fn () => $this->pay($this->cash, null, ['tendered' => '150']), 'tendered', 'Cash received must be at least ₱170.00.');

        $payment = $this->pay($this->cash, null, ['tendered' => '500']);

        $this->assertSame(['170.00', '500.00', '330.00'], [$payment->amount, $payment->tendered, $payment->change_given]);
    }

    public function test_cash_without_a_tendered_figure_counts_as_exact(): void
    {
        $payment = $this->pay($this->cash);

        $this->assertSame(['170.00', '170.00', '0.00'], [$payment->amount, $payment->tendered, $payment->change_given]);
    }

    public function test_paying_more_than_the_balance_is_refused_overpayment_exists_only_as_cash_change(): void
    {
        $this->assertRefused(fn () => $this->pay($this->gcash, '200', ['reference_no' => '1012345678901']), 'amount', 'The most this tab can take is ₱170.00.');
        $this->assertRefused(fn () => $this->pay($this->cash, '170.01', ['tendered' => '200']), 'amount');
        $this->assertRefused(fn () => $this->pay($this->cash, '0'), 'amount');
        $this->assertRefused(fn () => $this->pay($this->cash, '-5'), 'amount');
        $this->assertRefused(fn () => $this->pay($this->cash, '1.234'), 'amount');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_double_submit_with_the_same_key_records_one_payment(): void
    {
        $input = ['method_id' => $this->cash->id, 'amount' => '50', 'tendered' => '50', 'idempotency_key' => (string) Str::uuid()];

        $first = $this->payments->record($this->session, $this->cashier, $input);
        $second = $this->payments->record($this->session, $this->cashier, $input);

        $this->assertTrue($first->is($second));
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, ActivityLog::where('action', 'payment.recorded')->count());
        $this->assertSame('120.00', $this->bill()['balance']);

        $other = app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => 'T2', 'capacity' => 2]), $this->cashier);
        $this->assertRefused(fn () => $this->payments->record($other, $this->cashier, $input), 'idempotency_key');
    }

    public function test_a_second_tablet_working_from_a_stale_balance_cannot_overpay(): void
    {
        // Both tablets saw a ₱170.00 balance; the first one takes it all.
        $this->pay($this->cash, '170', ['tendered' => '200']);

        $this->assertRefused(fn () => $this->pay($this->gcash, '170', ['reference_no' => '1012345678901']), 'session', 'This tab is closed.');

        // Partial first, then two tablets both try ₱100 on the remaining ₱70.00.
        $this->session = app(TableSessionService::class)->open($this->table->fresh(), $this->cashier);
        $this->order(2);
        $this->pay($this->cash, '100');
        $this->assertRefused(fn () => $this->pay($this->cash, '100'), 'amount', 'The most this tab can take is ₱70.00.');
        $this->assertSame('100.00', $this->bill()['paid']);
    }

    public function test_the_payment_and_its_session_row_lock_happen_in_one_transaction(): void
    {
        $levels = [];
        DB::listen(function ($query) use (&$levels) {
            if (str_contains($query->sql, 'for update') && str_contains($query->sql, 'table_sessions')) {
                $levels[] = DB::transactionLevel();
            }
        });

        $this->pay($this->cash);

        $this->assertNotEmpty($levels);
        $this->assertGreaterThan(0, min($levels));
    }

    public function test_a_closed_tab_refuses_orders_payments_and_discounts(): void
    {
        $this->pay($this->cash);

        $this->assertRefused(fn () => $this->order(1), 'session', 'This tab is closed.');
        $this->assertRefused(fn () => $this->pay($this->cash, '10'), 'session', 'This tab is closed.');
        $this->assertRefused(fn () => $this->bills->applyDiscount($this->session, $this->owner, 'amount', '10', 'Promo'), 'session', 'This tab is closed.');
        $this->assertRefused(fn () => $this->bills->present($this->session, $this->owner), 'session', 'This tab is closed.');
    }

    public function test_a_method_that_needs_a_reference_refuses_a_blank_one(): void
    {
        $this->assertRefused(fn () => $this->pay($this->gcash, '170'), 'reference_no', 'Enter the GCash reference number.');
        $this->assertRefused(fn () => $this->pay($this->gcash, '170', ['reference_no' => '   ']), 'reference_no');
        $this->assertRefused(fn () => $this->pay($this->gcash, '170', ['reference_no' => str_repeat('9', 61)]), 'reference_no');
    }

    public function test_an_inactive_or_unknown_method_is_refused(): void
    {
        $this->gcash->forceFill(['is_active' => false])->save();

        $this->assertRefused(fn () => $this->pay($this->gcash, '170', ['reference_no' => '1']), 'method_id');
        $this->assertRefused(fn () => $this->payments->record($this->session, $this->cashier, ['method_id' => 999, 'idempotency_key' => (string) Str::uuid()]), 'method_id');
    }

    public function test_taking_payment_needs_the_permission(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->pay($this->cash, null, [], User::factory()->create()->assignRole('Bartender'));
    }

    public function test_a_percent_bill_discount_follows_the_bill_and_is_logged(): void
    {
        $this->bills->applyDiscount($this->session, $this->owner, 'percent', '10', '  Regular customer ');

        $bill = $this->bill();
        $this->assertSame(['170.00', '17.00', '153.00'], [$bill['subtotal'], $bill['bill_discount'], $bill['total']]);
        $log = ActivityLog::where('action', 'session.discounted')->sole();
        $this->assertSame(['type' => 'percent', 'value' => '10.00', 'reason' => 'Regular customer'], $log->new_values);

        $this->order(1); // subtotal ₱255.00, 10% = ₱25.50
        $this->assertSame(['25.50', '229.50'], [$this->bill()['bill_discount'], $this->bill()['total']]);

        $this->bills->applyDiscount($this->session, $this->owner, 'amount', '55', 'Promo');
        $this->assertSame('200.00', $this->bill()['total']);
        $this->assertSame(['type' => 'percent', 'value' => '10.00', 'reason' => 'Regular customer'], ActivityLog::where('action', 'session.discounted')->latest('id')->first()->old_values);

        $this->bills->removeDiscount($this->session, $this->owner);
        $this->assertSame(['0.00', '255.00'], [$this->bill()['bill_discount'], $this->bill()['total']]);
        $this->assertSame(1, ActivityLog::where('action', 'session.discount_removed')->count());
    }

    public function test_bill_discount_rules(): void
    {
        $this->assertRefused(fn () => $this->bills->applyDiscount($this->session, $this->owner, 'amount', '170.01', 'Promo'), 'value', 'The discount must be between ₱0.01 and ₱170.00.');
        $this->assertRefused(fn () => $this->bills->applyDiscount($this->session, $this->owner, 'percent', '100.5', 'Promo'), 'value', 'The discount must be between 0.01% and 100%.');
        $this->assertRefused(fn () => $this->bills->applyDiscount($this->session, $this->owner, 'percent', '0', 'Promo'), 'value');
        $this->assertRefused(fn () => $this->bills->applyDiscount($this->session, $this->owner, 'free', '5', 'Promo'), 'type');
        $this->assertRefused(fn () => $this->bills->applyDiscount($this->session, $this->owner, 'amount', '5', '  '), 'reason', 'Give a reason for the discount.');

        $this->pay($this->cash, '100');
        $this->assertRefused(fn () => $this->bills->applyDiscount($this->session, $this->owner, 'amount', '80', 'Promo'), 'value', 'The bill must stay above the ₱100.00 already paid.');
        // Equal to what was paid would leave nothing to pay on a tab that never closes.
        $this->assertRefused(fn () => $this->bills->applyDiscount($this->session, $this->owner, 'amount', '70', 'Promo'), 'value', 'The bill must stay above the ₱100.00 already paid.');
        $this->bills->applyDiscount($this->session, $this->owner, 'amount', '69.99', 'Promo');
        $this->assertSame('0.01', $this->bill()['balance']);

        $this->expectException(AuthorizationException::class);
        $this->bills->applyDiscount($this->session, $this->cashier, 'amount', '5', 'Promo');
    }

    public function test_the_bill_discount_cannot_be_removed_once_money_was_taken(): void
    {
        $this->bills->applyDiscount($this->session, $this->owner, 'percent', '10', 'Regular');
        $this->pay($this->cash, '50');

        $this->assertRefused(fn () => $this->bills->removeDiscount($this->session, $this->owner), 'session', 'Payment was taken on this tab. The discount can no longer be removed.');
        $this->assertSame('17.00', $this->bill()['bill_discount']);
    }

    public function test_after_payment_the_table_goes_to_cleaning_when_the_setting_is_on(): void
    {
        app(SettingService::class)->set(['clean_table_after_payment' => true]);

        $this->pay($this->cash);

        $this->assertSame(TableStatus::Cleaning, $this->table->fresh()->status);
    }

    public function test_a_new_order_on_a_presented_bill_reopens_the_tab(): void
    {
        $this->bills->present($this->session, $this->cashier);
        $this->bills->present($this->session, $this->cashier); // second open is a no-op
        $this->assertSame(1, ActivityLog::where('action', 'session.billed')->count());

        $this->order(1);

        $this->assertSame(SessionStatus::Open, $this->session->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $this->table->fresh()->status);
        $this->assertSame(1, ActivityLog::where('action', 'session.reopened')->count());
    }

    public function test_a_fully_discounted_bill_is_settled_without_a_payment(): void
    {
        $this->assertRefused(fn () => $this->payments->settleFreeBill($this->session, $this->cashier), 'session', 'This bill still has ₱170.00 to pay.');

        $this->bills->applyDiscount($this->session, $this->owner, 'percent', '100', "Owner's guest");
        $this->assertRefused(fn () => $this->pay($this->cash), 'session', 'Nothing is left to pay on this tab.');

        $this->payments->settleFreeBill($this->session, $this->cashier);

        $session = $this->session->fresh();
        $this->assertSame(SessionStatus::Closed, $session->status);
        $this->assertSame(['170.00', '170.00', '0.00'], [$session->gross_total, $session->discount_total, $session->net_total]);
        $this->assertNotNull($session->receipt_number);
        $this->assertSame(0, Payment::count());
    }

    public function test_items_cannot_be_cancelled_or_discounted_once_money_was_taken(): void
    {
        $item = $this->session->items()->sole();
        $this->pay($this->cash, '50');

        $this->assertRefused(fn () => app(OrderService::class)->cancelItem($item, $this->owner, 'Wrong order'), 'session', 'Payment was taken on this tab. Items can no longer be cancelled or discounted.');
        $this->assertRefused(fn () => app(OrderService::class)->updateItem($item, $this->owner, ['discount' => '10']), 'session');
    }

    public function test_payments_are_append_only(): void
    {
        $payment = $this->pay($this->cash, '50');

        $this->expectException(\LogicException::class);
        $payment->forceFill(['amount' => '1'])->save();
    }

    public function test_the_database_refuses_editing_a_payment(): void
    {
        $payment = $this->pay($this->cash, '50');

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('payments')->where('id', $payment->id)->update(['amount' => '1']);
    }
}

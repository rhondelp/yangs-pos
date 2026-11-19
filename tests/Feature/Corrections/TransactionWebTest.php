<?php

namespace Tests\Feature\Corrections;

use App\Models\Category;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Refund;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\PaymentCorrectionService;
use App\Services\Corrections\RefundService;
use App\Services\Corrections\VoidService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Settings\SettingService;
use App\Services\Tables\DiningTableService;
use App\Support\BusinessDate;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionWebTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private Product $redHorse;

    private PaymentMethod $cash;

    private PaymentMethod $gcash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create(['name' => 'Owner'])->assignRole('Owner');
        $this->cashier = User::factory()->create(['name' => 'Ana'])->assignRole('Cashier');
        $this->actingAs($this->owner);
        $this->cash = PaymentMethod::where('code', 'cash')->sole();
        $this->gcash = PaymentMethod::where('code', 'gcash')->sole();
        $beer = Category::create(['name' => 'Beer']);
        $this->redHorse = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
    }

    /** A tab with 2 × ₱85, paid in cash in two parts. Returns [session, first payment]. */
    private function tab(string $label, User $by): array
    {
        $table = app(DiningTableService::class)->create(['label' => $label, 'capacity' => 4]);
        $session = app(TableSessionService::class)->open($table, $by);
        app(OrderService::class)->addOrder($session, $by, [['product_id' => $this->redHorse->id, 'quantity' => 2]]);
        $first = $this->pay($session, '50', $by);

        return [$session, $first];
    }

    private function pay(TableSession $session, ?string $amount, User $by): Payment
    {
        return app(PaymentService::class)->record($session, $by, ['method_id' => $this->cash->id, 'amount' => $amount, 'idempotency_key' => (string) Str::uuid()]);
    }

    public function test_the_list_shows_the_days_closed_tabs_and_own_tabs_only_without_view_all(): void
    {
        [$mine] = $this->tab('T1', $this->cashier);
        $this->pay($mine, null, $this->cashier);
        [$theirs] = $this->tab('T2', $this->owner);
        $this->pay($theirs, null, $this->owner);
        [$open] = $this->tab('T3', $this->owner);
        [$mine, $theirs] = [$mine->fresh(), $theirs->fresh()];

        $listed = $this->actingAs($this->owner)->get('/transactions')->assertOk()
            ->assertSee($mine->receipt_number)->assertSee($theirs->receipt_number)
            ->viewData('sessions')->pluck('id')->all();
        $this->assertNotContains($open->id, $listed); // open tabs are not history
        $this->actingAs($this->cashier)->get('/transactions')->assertOk()
            ->assertSee($mine->receipt_number)->assertDontSee($theirs->receipt_number);
        $this->actingAs($this->cashier)->get("/transactions/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->owner)->get("/transactions/{$open->id}")->assertNotFound();

        $yesterday = \Carbon\CarbonImmutable::parse(BusinessDate::for(now()))->subDay()->toDateString();
        $this->actingAs($this->owner)->get("/transactions?date={$yesterday}")->assertOk()->assertDontSee($mine->receipt_number);
        $this->actingAs($this->owner)->get('/transactions?date=not-a-date')->assertSessionHasErrors('date');
    }

    public function test_the_detail_shows_voids_refunds_corrections_and_net(): void
    {
        [$session, $first] = $this->tab('T1', $this->owner);
        app(VoidService::class)->voidItem($session->items()->sole(), $this->owner, 1, 'Spilled');
        $this->pay($session, null, $this->owner); // ₱35 closes it
        app(PaymentCorrectionService::class)->correct($first, $this->owner, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-9', 'reason' => 'Wrong button']);
        app(RefundService::class)->refund($first, $this->owner, ['amount' => '20', 'reason' => 'Warm beer', 'reference_no' => 'R-1', 'idempotency_key' => (string) Str::uuid()]);

        $this->actingAs($this->owner)->get("/transactions/{$session->id}")->assertOk()
            ->assertSee('Spilled')
            ->assertSee('GC-9')
            ->assertSee('Corrected')
            ->assertSee('Warm beer')
            ->assertSeeInOrder(['Total', '₱85.00', 'Refunded', '-₱20.00', 'Net', '₱65.00'])
            ->assertSee(route('receipts.show', $session), false);
    }

    public function test_refund_and_correct_forms_post_back_to_the_detail_page(): void
    {
        [$session, $first] = $this->tab('T1', $this->owner);
        $this->pay($session, null, $this->owner);
        $detail = "/transactions/{$session->id}";

        $this->actingAs($this->owner)->from($detail)->followingRedirects()
            ->post("/transactions/payments/{$first->id}/refunds", ['amount' => '60', 'reason' => 'x', 'idempotency_key' => (string) Str::uuid(), 'dialog' => "refund-{$first->id}"])
            ->assertOk()
            ->assertSee('data-reopen="refund-'.$first->id.'"', false)
            ->assertSee('The most this payment can refund is ₱50.00.');

        $this->actingAs($this->owner)->from($detail)
            ->post("/transactions/payments/{$first->id}/refunds", ['amount' => '20', 'reason' => 'Warm beer', 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect($detail)->assertSessionHas('status', 'Refunded ₱20.00.');
        $this->assertSame(1, Refund::count());

        $this->actingAs($this->owner)->from($detail)
            ->post("/transactions/payments/{$first->id}/corrections", ['method_id' => $this->gcash->id, 'reference_no' => 'GC-9', 'reason' => 'Wrong button'])
            ->assertRedirect($detail)->assertSessionHas('status', 'Payment corrected to GCash.');
    }

    public function test_actions_follow_permissions_and_the_pin_setting(): void
    {
        [$session, $first] = $this->tab('T1', $this->cashier);
        $this->pay($session, null, $this->cashier);
        $refundUrl = route('transactions.refunds', $first);

        $this->actingAs($this->cashier)->get("/transactions/{$session->id}")->assertOk()->assertDontSee($refundUrl, false);
        $this->actingAs($this->cashier)->post($refundUrl, ['amount' => '1', 'reason' => 'x', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();

        $this->actingAs($this->owner)->get("/transactions/{$session->id}")->assertSee($refundUrl, false)->assertDontSee('approver_pin', false);
        app(SettingService::class)->set(['require_approval_pin' => true]);
        $this->actingAs($this->owner)->get("/transactions/{$session->id}")->assertSee('approver_pin', false);
    }

    public function test_the_bill_screen_offers_voids_only_to_users_with_orders_void(): void
    {
        [$session] = $this->tab('T1', $this->cashier);

        $boot = $this->actingAs($this->cashier)->get("/pos/sessions/{$session->id}/bill")->viewData('bootstrap');
        $this->assertArrayNotHasKey('voidItem', $boot['urls']);

        $boot = $this->actingAs($this->owner)->get("/pos/sessions/{$session->id}/bill")->viewData('bootstrap');
        $this->assertSame(route('api.v1.order-items.void', '__ID__'), $boot['urls']['voidItem']);
        $this->assertSame(route('api.v1.orders.void', '__ID__'), $boot['urls']['voidOrder']);
        $this->assertFalse($boot['approval']);
        $this->assertSame(2, $boot['session']['bill']['items'][0]['quantity']);
    }

    public function test_cashiers_see_transactions_in_the_menu(): void
    {
        $this->actingAs($this->cashier)->get('/transactions')->assertOk()->assertSee('No paid tabs');
    }
}

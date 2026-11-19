<?php

namespace Tests\Feature\Checkpoint;

use App\Models\Category;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\BillService;
use App\Services\Billing\PaymentService;
use App\Services\Billing\ReceiptService;
use App\Services\Catalog\ProductService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Findings 1–5 of the Phase 2 checkpoint audit, each pinned by a test. */
class Phase2AuditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private Product $redHorse;

    private PaymentMethod $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($this->owner);
        $this->cash = PaymentMethod::where('code', 'cash')->sole();
        $this->redHorse = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
    }

    private function tab(int $quantity = 3, string $label = 'T1'): TableSession
    {
        $session = app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => $label, 'capacity' => 4]), $this->owner);
        app(OrderService::class)->addOrder($session, $this->owner, [['product_id' => $this->redHorse->id, 'quantity' => $quantity]]);

        return $session;
    }

    private function pay(TableSession $session, array $extra = []): Payment
    {
        return app(PaymentService::class)->record($session, $this->owner, ['method_id' => $this->cash->id, 'idempotency_key' => (string) Str::uuid(), ...$extra]);
    }

    /** 1. Cancel item / cancel tab are reachable from the order screen for users allowed to. */
    public function test_the_order_screen_offers_item_and_tab_cancels_by_permission(): void
    {
        $session = $this->tab();

        $owner = $this->actingAs($this->owner)->get("/pos/sessions/{$session->id}")->assertOk();
        $this->assertSame(route('api.v1.order-items.cancel', '__ID__'), $owner->viewData('bootstrap')['urls']['cancelItem']);
        $this->assertSame(route('api.v1.sessions.cancel', $session), $owner->viewData('bootstrap')['urls']['cancelSession']);
        $owner->assertSee('Cancel tab');

        $boot = $this->actingAs($this->cashier)->get("/pos/sessions/{$session->id}")->viewData('bootstrap');
        $this->assertNull($boot['urls']['cancelItem']);
        $this->assertNull($boot['urls']['cancelSession']);
    }

    /** 2. The receipt's "Discount" line is the bill discount only; item discounts stay on their lines. */
    public function test_the_receipt_shows_each_discount_once(): void
    {
        $session = $this->tab(); // 3 × ₱85 = ₱255
        app(OrderService::class)->updateItem($session->items()->sole(), $this->owner, ['discount' => '10']); // ₱245
        app(BillService::class)->applyDiscount($session, $this->owner, 'percent', '10', 'Regular'); // -₱24.50 = ₱220.50
        $this->pay($session);

        $data = app(ReceiptService::class)->data($session->fresh());
        $this->assertSame(['255.00', '10.00', '24.50', '220.50'], [$data['gross'], $data['item_discounts'], $data['bill_discount'], $data['total']]);

        $this->actingAs($this->owner)->get("/receipts/{$session->id}")->assertOk()
            ->assertSeeInOrder(['Less discount', '-₱10.00', 'Subtotal', '₱255.00', 'Discount (Regular)', '-₱24.50', 'TOTAL', '₱220.50'])
            ->assertDontSee('-₱34.50');
    }

    /** 3. A typo in cash received cannot record an impossible change. */
    public function test_change_of_a_thousand_or_more_is_refused(): void
    {
        $session = $this->tab(); // ₱255

        try {
            $this->pay($session, ['tendered' => '100000']);
            $this->fail('accepted ₱99,745 change');
        } catch (ValidationException $e) {
            $this->assertSame('Change of ₱99,745.00 is too much. Check the cash received.', $e->errors()['tendered'][0]);
        }

        $this->assertSame('745.00', $this->pay($session, ['tendered' => '1000'])->change_given);
    }

    /** 4. A failed refund on one payment does not prefill another payment's dialog. */
    public function test_a_failed_refund_does_not_leak_into_other_dialogs(): void
    {
        $session = $this->tab(2); // ₱170
        $first = $this->pay($session, ['amount' => '100']);
        $second = $this->pay($session);
        $detail = "/transactions/{$session->id}";

        $html = $this->actingAs($this->owner)->from($detail)->followingRedirects()
            ->post("/transactions/payments/{$first->id}/refunds", ['amount' => '150', 'reason' => 'Leaky reason', 'idempotency_key' => (string) Str::uuid(), 'dialog' => "refund-{$first->id}"])
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="refund-amount-'.$first->id.'"[^>]*value="150"/', $html); // kept where it was typed
        $this->assertMatchesRegularExpression('/id="refund-amount-'.$second->id.'"[^>]*value="70.00"/', $html); // the other payment keeps its own
        $this->assertSame(1, substr_count($html, 'Leaky reason'));
    }

    /** 5. Changing the approval PIN needs the current password. */
    public function test_setting_an_approval_pin_needs_the_current_password(): void
    {
        $this->actingAs($this->owner)->put('/profile/approval-pin', ['approval_pin' => '2468', 'approval_pin_confirmation' => '2468'])
            ->assertSessionHasErrorsIn('approvalPin', 'current_password');
        $this->actingAs($this->owner)->put('/profile/approval-pin', ['current_password' => 'wrong', 'approval_pin' => '2468', 'approval_pin_confirmation' => '2468'])
            ->assertSessionHasErrorsIn('approvalPin', 'current_password');
        $this->assertNull($this->owner->fresh()->approval_pin);

        $this->actingAs($this->owner)->put('/profile/approval-pin', ['current_password' => 'password', 'approval_pin' => '2468', 'approval_pin_confirmation' => '2468'])
            ->assertRedirect('/profile');
        $this->assertTrue(Hash::check('2468', $this->owner->fresh()->approval_pin));
    }
}

<?php

namespace Tests\Feature\Billing;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
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

class BillingHttpTest extends TestCase
{
    use RefreshDatabase;

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
        $this->cash = PaymentMethod::where('code', 'cash')->sole();
        $this->gcash = PaymentMethod::where('code', 'gcash')->sole();
        $this->actingAs($this->owner);

        $beer = Category::create(['name' => 'Beer']);
        $this->redHorse = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        $this->table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($this->table, $this->cashier);
        app(OrderService::class)->addOrder($this->session, $this->cashier, [['product_id' => $this->redHorse->id, 'quantity' => 2]]); // ₱170.00
    }

    private function url(string $path = ''): string
    {
        return "/api/v1/sessions/{$this->session->id}{$path}";
    }

    public function test_getting_the_bill_presents_it(): void
    {
        $this->actingAs($this->cashier, 'sanctum')->getJson($this->url('/bill'))
            ->assertOk()
            ->assertJsonPath('data.status', 'billing')
            ->assertJsonPath('data.table', 'T1')
            ->assertJsonPath('data.bill.total', '170.00')
            ->assertJsonPath('data.bill.balance', '170.00')
            ->assertJsonPath('data.receipt_url', null);

        $this->assertSame(TableStatus::AwaitingPayment, $this->table->fresh()->status);
    }

    public function test_billing_routes_need_their_permissions(): void
    {
        Role::findByName('Cashier')->revokePermissionTo('payments.record');
        $cashier = $this->cashier->fresh();

        $this->actingAs($cashier, 'sanctum')->getJson($this->url('/bill'))->assertForbidden();
        $this->actingAs($cashier, 'sanctum')->postJson($this->url('/payments'), [])->assertForbidden();
        $this->actingAs($cashier, 'sanctum')->postJson($this->url('/settle'))->assertForbidden();
        $this->actingAs($cashier, 'sanctum')->postJson($this->url('/discount'), ['type' => 'amount', 'value' => '10', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($cashier)->get("/pos/sessions/{$this->session->id}/bill")->assertForbidden();
    }

    public function test_a_split_payment_closes_the_tab_and_links_the_receipt(): void
    {
        $this->actingAs($this->cashier, 'sanctum')->postJson($this->url('/payments'), [
            'method_id' => $this->gcash->id, 'amount' => '70', 'reference_no' => 'GC-1234', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.bill.balance', '100.00')
            ->assertJsonPath('payment.amount', '70.00');

        $this->actingAs($this->cashier, 'sanctum')->postJson($this->url('/payments'), [
            'method_id' => $this->cash->id, 'tendered' => '500', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.bill.balance', '0.00')
            ->assertJsonPath('payment.amount', '100.00')
            ->assertJsonPath('payment.change_given', '400.00')
            ->assertJsonPath('data.receipt_url', route('receipts.show', $this->session));
    }

    public function test_a_resent_payment_is_answered_with_the_first_one(): void
    {
        $body = ['method_id' => $this->cash->id, 'amount' => '50', 'idempotency_key' => (string) Str::uuid()];

        $first = $this->actingAs($this->cashier, 'sanctum')->postJson($this->url('/payments'), $body)->assertCreated()->json('payment.id');
        $this->actingAs($this->cashier, 'sanctum')->postJson($this->url('/payments'), $body)->assertOk()->assertJsonPath('payment.id', $first);

        $this->assertSame(1, Payment::count());
    }

    public function test_bad_payment_input_is_refused(): void
    {
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson($this->url('/payments'), ['method_id' => 'cash', 'amount' => '1e3', 'idempotency_key' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method_id', 'amount', 'idempotency_key']);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson($this->url('/payments'), ['method_id' => $this->gcash->id, 'idempotency_key' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference_no');
    }

    public function test_the_owner_discounts_and_removes_the_discount(): void
    {
        $this->actingAs($this->cashier, 'sanctum')->postJson($this->url('/discount'), ['type' => 'percent', 'value' => '10', 'reason' => 'Regular'])->assertForbidden();

        $this->actingAs($this->owner, 'sanctum')->postJson($this->url('/discount'), ['type' => 'percent', 'value' => '10', 'reason' => 'Regular'])
            ->assertOk()
            ->assertJsonPath('data.bill.bill_discount', '17.00')
            ->assertJsonPath('data.bill.total', '153.00');

        $this->actingAs($this->owner, 'sanctum')->deleteJson($this->url('/discount'))
            ->assertOk()
            ->assertJsonPath('data.bill.total', '170.00');

        $this->actingAs($this->owner, 'sanctum')->postJson($this->url('/discount'), ['type' => 'fixed', 'value' => '', 'reason' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'value', 'reason']);
    }

    public function test_a_free_bill_is_settled(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson($this->url('/discount'), ['type' => 'percent', 'value' => '100', 'reason' => "Owner's guest"])->assertOk();

        $this->actingAs($this->cashier, 'sanctum')->postJson($this->url('/settle'))
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_the_bill_page_presents_the_bill_and_ships_its_data(): void
    {
        $boot = $this->actingAs($this->cashier)->get("/pos/sessions/{$this->session->id}/bill")
            ->assertOk()
            ->assertSee('billScreen', false)
            ->viewData('bootstrap');

        $this->assertSame(route('api.v1.sessions.payments', $this->session), $boot['urls']['payments']);
        $this->assertArrayNotHasKey('discount', $boot['urls']); // cashiers cannot discount
        $this->assertSame(['Cash', 'GCash'], array_column($boot['methods'], 'name'));
        $this->assertSame('billing', $boot['session']['status']);
        $this->assertSame([[
            'id' => $this->session->items()->value('order_items.id'), 'order_id' => $this->session->orders()->value('id'), 'name' => 'Red Horse',
            'quantity' => 2, 'voided_quantity' => 0, 'unit_price' => '85.00', 'discount' => '0.00', 'line_total' => '170.00',
        ]], $boot['session']['bill']['items']);
        $this->assertSame(SessionStatus::Billing, $this->session->fresh()->status);

        $owner = $this->actingAs($this->owner)->get("/pos/sessions/{$this->session->id}/bill")->viewData('bootstrap');
        $this->assertSame(route('api.v1.sessions.discount', $this->session), $owner['urls']['discount']);
    }

    public function test_the_bill_page_of_a_closed_tab_goes_to_its_receipt(): void
    {
        $this->actingAs($this->cashier, 'sanctum')->postJson($this->url('/payments'), ['method_id' => $this->cash->id, 'idempotency_key' => (string) Str::uuid()])->assertCreated();

        $this->actingAs($this->cashier)->get("/pos/sessions/{$this->session->id}/bill")->assertRedirect(route('receipts.show', $this->session));
    }

    public function test_the_pos_screen_links_to_the_bill_page(): void
    {
        $this->actingAs($this->cashier)->get("/pos/sessions/{$this->session->id}")
            ->assertOk()
            ->assertSee(route('pos.bill', $this->session), false);
    }
}

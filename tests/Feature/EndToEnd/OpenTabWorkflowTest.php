<?php

namespace Tests\Feature\EndToEnd;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * YANGS_SPEC §4B steps 1–12 end to end, as a cashier on /api/v1 (the same calls the tablet makes),
 * checking the §4C order record and the §4D payment rules on the way.
 */
class OpenTabWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private Product $redHorse;

    private Product $sisig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create(['name' => 'Owner'])->assignRole('Owner');
        $this->cashier = User::factory()->create(['name' => 'Ana'])->assignRole('Cashier');

        $this->actingAs($this->owner);
        $category = Category::create(['name' => 'Beer']);
        $this->redHorse = app(ProductService::class)->create(['category_id' => $category->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        $this->sisig = app(ProductService::class)->create(['category_id' => $category->id, 'name' => 'Sisig', 'type' => 'food', 'price' => '220', 'cost' => '90']);
        foreach (['T1', 'T2'] as $label) {
            app(DiningTableService::class)->create(['label' => $label, 'capacity' => 4]);
        }
    }

    public function test_the_open_tab_workflow_from_free_table_to_free_table(): void
    {
        $this->actingAs($this->cashier, 'sanctum');
        $cash = PaymentMethod::where('code', 'cash')->value('id');
        $gcash = PaymentMethod::where('code', 'gcash')->value('id');

        // 1. The cashier selects an available table.
        $table = collect($this->getJson('/api/v1/tables')->assertOk()->json('data'))->firstWhere('label', 'T1');
        $this->assertSame('available', $table['status']);

        // 2. The cashier creates an order (opens the table's tab).
        $session = $this->postJson("/api/v1/tables/{$table['id']}/sessions")->assertCreated()->json('data');
        $this->assertSame('open', $session['status']);
        $this->assertSame('occupied', $this->tableStatus('T1'));

        // 3–4. Products are added and saved as an open, unpaid transaction.
        $first = $this->postJson("/api/v1/sessions/{$session['id']}/orders", ['lines' => [
            ['product_id' => $this->redHorse->id, 'quantity' => 2],
            ['product_id' => $this->sisig->id, 'quantity' => 1],
        ]])->assertCreated()->json('data');

        // §4C: every field of the order record.
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $first['reference']);
        $this->assertSame('T1', $first['table']);
        $this->assertSame(['id' => $this->cashier->id, 'name' => 'Ana'], $first['cashier']);
        $this->assertNotNull($first['created_at']);
        $this->assertSame(['Red Horse', 'Sisig'], array_column($first['items'], 'product_name'));
        $this->assertSame([2, 1], array_column($first['items'], 'quantity'));
        $this->assertSame(['85.00', '220.00'], array_column($first['items'], 'unit_price'));
        $this->assertSame(['170.00', '220.00'], array_column($first['items'], 'line_total'));
        $this->assertSame(['0.00', '0.00'], array_column($first['items'], 'discount'));
        $this->assertSame('390.00', $first['total']);
        $this->assertSame(['open', 'unpaid'], [$first['status'], $first['payment_status']]);

        // Prices change later; history keeps the price at the time of ordering.
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/products/{$this->redHorse->id}", ['price' => 95])->assertOk();
        $this->actingAs($this->cashier, 'sanctum');

        // 5–7. The customer keeps ordering; new orders join the same tab and the running bill follows.
        $this->postJson("/api/v1/sessions/{$session['id']}/orders", ['lines' => [['product_id' => $this->redHorse->id, 'quantity' => 1]]])->assertCreated();
        $tab = $this->getJson("/api/v1/sessions/{$session['id']}")->assertOk()->json('data');
        $this->assertCount(2, $tab['orders']);
        $this->assertSame('85.00', $tab['orders'][0]['items'][0]['unit_price']);
        $this->assertSame('95.00', $tab['orders'][1]['items'][0]['unit_price']);
        $this->assertSame(['485.00', 4], [$tab['bill']['total'], $tab['bill']['item_count']]);

        // 8–9. The cashier opens the consolidated bill and reviews products, quantities, prices and the total.
        $bill = $this->getJson("/api/v1/sessions/{$session['id']}/bill")->assertOk()->json('data');
        $this->assertSame('billing', $bill['status']);
        $this->assertSame('awaiting_payment', $this->tableStatus('T1'));
        $this->assertSame([['Red Horse', 2, '85.00', '170.00'], ['Sisig', 1, '220.00', '220.00'], ['Red Horse', 1, '95.00', '95.00']],
            array_map(fn ($i) => [$i['name'], $i['quantity'], $i['unit_price'], $i['line_total']], $bill['bill']['items']));
        $this->assertSame(['485.00', '485.00'], [$bill['bill']['total'], $bill['bill']['balance']]);

        // 10. The customer pays: part by GCash...
        $partial = $this->postJson("/api/v1/sessions/{$session['id']}/payments", [
            'method_id' => $gcash, 'amount' => '200', 'reference_no' => 'GC-0001', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data');

        // ...which is not enough: never Paid before payments cover the total (§4D).
        $this->assertSame(['billing', '285.00'], [$partial['status'], $partial['bill']['balance']]);
        $this->assertSame(['partial'], array_unique(array_column($this->getJson("/api/v1/sessions/{$session['id']}")->json('data.orders'), 'payment_status')));
        $this->getJson("/api/v1/sessions/{$session['id']}/receipt")->assertUnprocessable();

        // ...and the rest in cash, with change.
        $paid = $this->postJson("/api/v1/sessions/{$session['id']}/payments", [
            'method_id' => $cash, 'tendered' => '300', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $paid->assertJsonPath('payment.amount', '285.00')->assertJsonPath('payment.change_given', '15.00');

        // 11. Once payment is recorded, the transaction is Paid.
        $this->assertSame('closed', $paid->json('data.status'));
        $this->assertSame(['paid'], array_unique(array_column($this->getJson("/api/v1/sessions/{$session['id']}")->json('data.orders'), 'payment_status')));

        // 12. The tab is closed and the table is Available again.
        $this->assertSame('available', $this->tableStatus('T1'));
        $this->postJson("/api/v1/sessions/{$session['id']}/orders", ['lines' => [['product_id' => $this->sisig->id, 'quantity' => 1]]])->assertUnprocessable();

        // The receipt is ready, and the whole visit is in the audit trail.
        $receipt = $this->getJson("/api/v1/sessions/{$session['id']}/receipt")->assertOk()->json('data');
        $this->assertMatchesRegularExpression('/^RCP-\d{8}-\d{4}$/', $receipt['receipt_number']);
        $this->assertSame(['485.00', '485.00', '15.00'], [$receipt['total'], $receipt['paid'], $receipt['change']]);
        $this->assertSame(
            ['session.opened', 'order.created', 'order.created', 'session.billed', 'payment.recorded', 'payment.recorded', 'session.closed', 'receipt.printed'],
            ActivityLog::whereIn('action', ['session.opened', 'order.created', 'session.billed', 'payment.recorded', 'session.closed', 'receipt.printed'])->orderBy('id')->pluck('action')->all(),
        );
    }

    private function tableStatus(string $label): string
    {
        return collect($this->getJson('/api/v1/tables')->json('data'))->firstWhere('label', $label)['status'];
    }
}

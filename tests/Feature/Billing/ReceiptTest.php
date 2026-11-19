<?php

namespace Tests\Feature\Billing;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DiningTable;
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
use App\Services\Settings\SettingService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private DiningTable $table;

    private TableSession $session;

    private Product $redHorse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create(['name' => 'Ana Cashier'])->assignRole('Cashier');
        $this->actingAs($this->owner);

        app(SettingService::class)->set(['business_name' => 'YANGS Bar', 'business_address' => 'Rizal St.', 'business_tin' => '123-456', 'receipt_footer' => 'Salamat po!']);
        $beer = Category::create(['name' => 'Beer']);
        $this->redHorse = app(ProductService::class)->create(['category_id' => $beer->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        $this->table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
        $this->session = app(TableSessionService::class)->open($this->table, $this->cashier);
        app(OrderService::class)->addOrder($this->session, $this->cashier, [['product_id' => $this->redHorse->id, 'quantity' => 2]]); // ₱170.00
    }

    private function close(): TableSession
    {
        app(BillService::class)->applyDiscount($this->session, $this->owner, 'amount', '20', 'Regular');
        app(PaymentService::class)->record($this->session, $this->cashier, [
            'method_id' => PaymentMethod::where('code', 'cash')->value('id'), 'tendered' => '200', 'idempotency_key' => (string) Str::uuid(),
        ]);

        return $this->session->fresh();
    }

    public function test_receipt_data_carries_business_details_lines_totals_and_change(): void
    {
        $session = $this->close();

        $data = app(ReceiptService::class)->data($session);

        $this->assertSame(['name' => 'YANGS Bar', 'address' => 'Rizal St.', 'tin' => '123-456', 'footer' => 'Salamat po!'], $data['business']);
        $this->assertSame($session->receipt_number, $data['receipt_number']);
        $this->assertSame('T1', $data['table']);
        $this->assertSame('Ana Cashier', $data['cashier']);
        $this->assertSame([['name' => 'Red Horse', 'quantity' => 2, 'unit_price' => '85.00', 'discount' => '0.00', 'line_total' => '170.00']], $data['items']);
        $this->assertSame(['170.00', '20.00', '150.00', '150.00', '50.00'], [$data['gross'], $data['discount'], $data['total'], $data['paid'], $data['change']]);
        $this->assertSame('Cash', $data['payments'][0]['method']);
    }

    public function test_an_open_tab_has_no_receipt(): void
    {
        $this->expectException(ValidationException::class);
        app(ReceiptService::class)->data($this->session);
    }

    public function test_the_first_view_is_a_print_and_later_views_are_reprints(): void
    {
        $session = $this->close();

        $this->actingAs($this->cashier)->get("/receipts/{$session->id}")
            ->assertOk()
            ->assertSee('YANGS Bar')
            ->assertSee($session->receipt_number)
            ->assertSee('Salamat po!')
            ->assertSee('80mm', false);
        $this->actingAs($this->cashier)->get("/receipts/{$session->id}?paper=58")->assertOk()->assertSee('58mm', false);

        $this->assertSame(1, ActivityLog::where('action', 'receipt.printed')->count());
        $this->assertSame(1, ActivityLog::where('action', 'receipt.reprinted')->count());
        $this->assertSame($session->receipt_number, ActivityLog::where('action', 'receipt.printed')->sole()->new_values['receipt_number']);
    }

    public function test_the_receipt_page_refuses_open_tabs_and_users_without_receipts_view(): void
    {
        $this->actingAs($this->cashier)->get("/receipts/{$this->session->id}")->assertNotFound();

        $session = $this->close();
        Role::findByName('Cashier')->revokePermissionTo('receipts.view');
        $this->actingAs($this->cashier->fresh())->get("/receipts/{$session->id}")->assertForbidden();
        $this->assertSame(0, ActivityLog::where('action', 'like', 'receipt.%')->count());
    }

    public function test_the_api_returns_the_receipt_and_logs_it(): void
    {
        $this->actingAs($this->cashier, 'sanctum')->getJson("/api/v1/sessions/{$this->session->id}/receipt")
            ->assertUnprocessable()->assertJsonValidationErrors('session');

        $session = $this->close();
        $this->actingAs($this->cashier, 'sanctum')->getJson("/api/v1/sessions/{$session->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.receipt_number', $session->receipt_number)
            ->assertJsonPath('data.total', '150.00')
            ->assertJsonPath('data.business.name', 'YANGS Bar');
        $this->assertSame(1, ActivityLog::where('action', 'receipt.printed')->count());
    }
}

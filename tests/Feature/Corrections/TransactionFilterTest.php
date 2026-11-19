<?php

namespace Tests\Feature\Corrections;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\PaymentCorrectionService;
use App\Services\Corrections\RefundService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $ana;

    private User $ben;

    private Product $redHorse;

    private PaymentMethod $cash;

    private PaymentMethod $gcash;

    /** @var array<string, TableSession> */
    private array $tabs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $this->owner = User::factory()->create(['name' => 'Owner'])->assignRole('Owner');
        $this->ana = User::factory()->create(['name' => 'Ana'])->assignRole('Cashier');
        $this->ben = User::factory()->create(['name' => 'Ben'])->assignRole('Cashier');
        $this->actingAs($this->owner);
        $this->cash = PaymentMethod::where('code', 'cash')->sole();
        $this->gcash = PaymentMethod::where('code', 'gcash')->sole();
        $this->redHorse = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);

        // T1: Ana, cash. T2: Ben, GCash. T3: Ana, cash, fully refunded. T4: Ben, cash corrected to GCash.
        $this->tabs['T1'] = $this->paidTab('T1', $this->ana, $this->cash);
        $this->tabs['T2'] = $this->paidTab('T2', $this->ben, $this->gcash);
        $this->tabs['T3'] = $this->paidTab('T3', $this->ana, $this->cash);
        app(RefundService::class)->refund($this->tabs['T3']->payments()->sole(), $this->owner, ['amount' => '85', 'reason' => 'Warm', 'idempotency_key' => (string) Str::uuid()]);
        $this->tabs['T4'] = $this->paidTab('T4', $this->ben, $this->cash);
        app(PaymentCorrectionService::class)->correct($this->tabs['T4']->payments()->sole(), $this->owner, ['method_id' => $this->gcash->id, 'reference_no' => 'GC-4', 'reason' => 'Wrong button']);
    }

    private function paidTab(string $label, User $by, PaymentMethod $method): TableSession
    {
        $session = app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => $label, 'capacity' => 4]), $by);
        app(OrderService::class)->addOrder($session, $by, [['product_id' => $this->redHorse->id, 'quantity' => 1]]);
        app(PaymentService::class)->record($session, $by, ['method_id' => $method->id, 'reference_no' => 'R-'.$label, 'idempotency_key' => (string) Str::uuid()]);

        return $session->fresh();
    }

    /** Receipt numbers listed for these filters, in tab order T1..T4 by label. */
    private function listed(User $user, array $filters = []): array
    {
        $html = $this->actingAs($user)->get('/transactions?'.http_build_query($filters))->assertOk()->getContent();

        return array_keys(array_filter($this->tabs, fn (TableSession $s) => str_contains($html, $s->receipt_number)));
    }

    public function test_each_filter_narrows_the_list(): void
    {
        $this->assertSame(['T1', 'T2', 'T3', 'T4'], $this->listed($this->owner));
        $this->assertSame(['T2'], $this->listed($this->owner, ['table_id' => DiningTable::where('label', 'T2')->value('id')]));
        $this->assertSame(['T1', 'T3'], $this->listed($this->owner, ['cashier_id' => $this->ana->id]));
        $this->assertSame(['T3'], $this->listed($this->owner, ['payment_status' => 'refunded']));
        $this->assertSame(['T1', 'T2', 'T4'], $this->listed($this->owner, ['payment_status' => 'paid']));
        $this->assertSame(['T2', 'T4'], $this->listed($this->owner, ['method_id' => $this->gcash->id])); // T4 by its corrected method
        $this->assertSame(['T1', 'T3'], $this->listed($this->owner, ['method_id' => $this->cash->id]));
        $this->assertSame(['T1'], $this->listed($this->owner, ['cashier_id' => $this->ana->id, 'payment_status' => 'paid']));
    }

    public function test_a_filter_never_widens_what_a_cashier_may_see(): void
    {
        $this->assertSame(['T1', 'T3'], $this->listed($this->ana));
        $this->assertSame([], $this->listed($this->ana, ['cashier_id' => $this->ben->id]));
        $this->assertSame([], $this->listed($this->ana, ['method_id' => $this->gcash->id]));

        $this->actingAs($this->ana)->get('/transactions')->assertDontSee('name="cashier_id"', false);
        $this->actingAs($this->owner)->get('/transactions')->assertSee('name="cashier_id"', false);
    }

    public function test_bad_filter_values_are_refused(): void
    {
        $this->actingAs($this->owner)->get('/transactions?payment_status=lost&table_id=x&method_id=x&cashier_id=x')
            ->assertSessionHasErrors(['payment_status', 'table_id', 'method_id', 'cashier_id']);
    }
}

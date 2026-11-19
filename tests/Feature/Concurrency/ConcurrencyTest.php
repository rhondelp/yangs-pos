<?php

namespace Tests\Feature\Concurrency;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Real races: several PHP processes call the same service at the same instant against the test database,
 * so the row locks and unique indexes are what is tested. Rows are committed (no wrapping transaction),
 * and every table is truncated again afterwards so later RefreshDatabase tests start clean.
 */
class ConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private const WORKERS = 5;

    private User $cashier;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class]);
        $owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');
        $this->actingAs($owner);
        $this->table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
    }

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();
        parent::tearDown();
    }

    /**
     * Starts one worker per argument set, releases them together and returns what each printed.
     *
     * @param  list<array<string, mixed>>  $argsPerWorker
     * @return list<string>
     */
    private function race(string $scenario, array $argsPerWorker): array
    {
        $startAt = microtime(true) + 4; // time for every process to boot first
        $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'yangs_test']);
        $procs = [];
        foreach ($argsPerWorker as $args) {
            $cmd = [PHP_BINARY, base_path('tests/Concurrency/worker.php'), $scenario, (string) $startAt, json_encode($args)];
            // stderr goes into stdout: two pipes read one after the other can deadlock on a long error.
            $procs[] = [$p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, base_path(), $env), $pipes];
            $this->assertIsResource($p);
        }

        // Collect every worker before asserting, so none is left running into tearDown's truncation.
        $finished = array_map(function (array $proc) {
            [$p, $pipes] = $proc;
            $out = stream_get_contents($pipes[1]);

            return [proc_close($p), $out];
        }, $procs);

        $results = [];
        foreach ($finished as [$code, $out]) {
            $this->assertSame(0, $code, "worker failed: {$out}");
            $this->assertMatchesRegularExpression('/\|lead=(\d+\.?\d*)$/', $out, "worker booted late, so this run did not race: {$out}");
            $results[] = substr($out, 0, strrpos($out, '|lead='));
        }

        return $results;
    }

    /** A tab on T1 with ₱170.00 to pay. */
    private function tab(): TableSession
    {
        $session = app(TableSessionService::class)->open($this->table, $this->cashier);
        $product = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.50']);
        app(OrderService::class)->addOrder($session, $this->cashier, [['product_id' => $product->id, 'quantity' => 2]]);

        return $session;
    }

    public function test_five_tablets_paying_the_whole_bill_at_once_record_one_payment(): void
    {
        $session = $this->tab();
        $cash = PaymentMethod::where('code', 'cash')->value('id');

        $results = $this->race('pay', array_map(fn () => [
            'user_id' => $this->cashier->id, 'session_id' => $session->id, 'method_id' => $cash, 'key' => (string) Str::uuid(),
        ], range(1, self::WORKERS)));

        $this->assertCount(1, array_filter($results, fn ($r) => str_starts_with($r, 'ok:')), implode(' | ', $results));
        // The winner's payment covers the bill and closes the tab, so every other tablet finds it closed.
        $this->assertCount(self::WORKERS - 1, array_filter($results, fn ($r) => $r === 'refused:This tab is closed.'), implode(' | ', $results));
        $this->assertSame(1, Payment::count());
        $this->assertSame('170.00', Payment::sole()->amount);
        $session->refresh();
        $this->assertSame(SessionStatus::Closed, $session->status);
        $this->assertSame('170.00', $session->net_total);
        $this->assertNotNull($session->receipt_number);
    }

    public function test_the_same_payment_sent_five_times_at_once_is_recorded_once(): void
    {
        $session = $this->tab();
        $args = ['user_id' => $this->cashier->id, 'session_id' => $session->id, 'method_id' => PaymentMethod::where('code', 'cash')->value('id'), 'key' => (string) Str::uuid()];

        $results = $this->race('pay', array_fill(0, self::WORKERS, $args));

        $this->assertSame(1, Payment::count());
        $this->assertSame(array_fill(0, self::WORKERS, 'ok:'.Payment::sole()->id), $results); // every resend gets the same payment back
    }

    public function test_the_same_void_sent_five_times_at_once_is_recorded_once_even_when_it_closes_the_tab(): void
    {
        $session = $this->tab();
        $this->cashier->givePermissionTo('orders.void');
        app(\App\Services\Billing\PaymentService::class)->record($session, $this->cashier, [
            'method_id' => PaymentMethod::where('code', 'cash')->value('id'), 'amount' => '85', 'idempotency_key' => (string) Str::uuid(),
        ]);
        $args = ['user_id' => $this->cashier->id, 'item_id' => $session->items()->value('order_items.id'), 'key' => (string) Str::uuid()];

        $results = $this->race('void', array_fill(0, self::WORKERS, $args)); // voiding one ₱85 unit leaves ₱85 = paid: the tab closes

        $this->assertSame(1, \App\Models\OrderVoid::count());
        $this->assertSame(array_fill(0, self::WORKERS, 'ok:'.\App\Models\OrderVoid::sole()->id), $results);
        $this->assertSame(SessionStatus::Closed, $session->fresh()->status);
    }

    public function test_five_tablets_selling_the_last_bottle_at_once_sell_it_once(): void
    {
        $beer = app(\App\Services\Inventory\InventoryItemService::class)->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle']);
        app(\App\Services\Inventory\InventoryService::class)->receive(auth()->user(), ['received_at' => now()->toIso8601String(),
            'lines' => [['inventory_item_id' => $beer->id, 'quantity' => '1', 'line_cost' => '30']]]);
        $product = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'track_inventory' => true]);
        app(\App\Services\Inventory\RecipeService::class)->save($product, null, [['inventory_item_id' => $beer->id, 'quantity' => '1']]);
        // Five tabs on five tables, so only the shared stock row can make them wait for each other.
        $sessions = array_map(fn (int $n) => app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => "S{$n}", 'capacity' => 2]), $this->cashier), range(1, self::WORKERS));

        $results = $this->race('sell', array_map(fn (TableSession $s) => ['user_id' => $this->cashier->id, 'session_id' => $s->id, 'product_id' => $product->id], $sessions));

        $this->assertCount(1, array_filter($results, fn ($r) => str_starts_with($r, 'ok:')), implode(' | ', $results));
        $this->assertCount(self::WORKERS - 1, array_filter($results, fn ($r) => $r === 'refused:Only 0 bottle of Red Horse 500ml left.'), implode(' | ', $results));
        $this->assertSame('0.000', $beer->fresh()->on_hand);
        $this->assertSame(1, \App\Models\InventoryMovement::where('type', 'sale')->count());
    }

    public function test_five_quantity_raises_racing_for_the_last_bottle_get_it_once(): void
    {
        $beer = app(\App\Services\Inventory\InventoryItemService::class)->create(['name' => 'Red Horse 500ml', 'base_unit' => 'bottle']);
        app(\App\Services\Inventory\InventoryService::class)->receive(auth()->user(), ['received_at' => now()->toIso8601String(),
            'lines' => [['inventory_item_id' => $beer->id, 'quantity' => '6', 'line_cost' => '180']]]);
        $product = app(ProductService::class)->create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'track_inventory' => true]);
        app(\App\Services\Inventory\RecipeService::class)->save($product, null, [['inventory_item_id' => $beer->id, 'quantity' => '1']]);
        // Five tabs with one bottle each leave one on the shelf; each tab then raises its line to 2.
        $lines = array_map(function (int $n) use ($product) {
            $session = app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => "R{$n}", 'capacity' => 2]), $this->cashier);

            return app(OrderService::class)->addOrder($session, $this->cashier, [['product_id' => $product->id, 'quantity' => 1]])->items->sole();
        }, range(1, self::WORKERS));

        $results = $this->race('raise', array_map(fn ($line) => ['user_id' => $this->cashier->id, 'item_id' => $line->id], $lines));

        $this->assertCount(1, array_filter($results, fn ($r) => str_starts_with($r, 'ok:')), implode(' | ', $results));
        $this->assertCount(self::WORKERS - 1, array_filter($results, fn ($r) => $r === 'refused:Only 0 bottle of Red Horse 500ml left.'), implode(' | ', $results));
        $this->assertSame('0.000', $beer->fresh()->on_hand);
    }

    public function test_five_tablets_opening_the_same_table_at_once_open_one_tab(): void
    {
        $results = $this->race('open', array_fill(0, self::WORKERS, ['user_id' => $this->cashier->id, 'table_id' => $this->table->id]));

        $this->assertCount(1, array_filter($results, fn ($r) => str_starts_with($r, 'ok:')), implode(' | ', $results));
        $this->assertCount(self::WORKERS - 1, array_filter($results, fn ($r) => $r === 'refused:Table T1 already has an open tab.'), implode(' | ', $results));
        $this->assertSame(1, TableSession::count());
        $this->assertSame(TableStatus::Occupied, $this->table->fresh()->status);
    }
}

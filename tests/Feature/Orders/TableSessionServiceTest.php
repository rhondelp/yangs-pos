<?php

namespace Tests\Feature\Orders;

use App\Enums\SessionStatus;
use App\Enums\TableStatus as S;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use App\Services\Tables\TableStatusService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TableSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TableSessionService $sessions;

    private DiningTable $table;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        $this->sessions = app(TableSessionService::class);
        $this->table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
    }

    private function assertRefused(callable $do, string $key, string $message): void
    {
        try {
            $do();
            $this->fail('expected a refusal');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, $e->errors()[$key][0]);
        }
    }

    private function addItem(TableSession $session, string $status = 'active'): OrderItem
    {
        $order = Order::create(['reference' => 'YG-'.uniqid(), 'table_session_id' => $session->id, 'created_by' => $this->owner->id, 'business_date' => '2026-11-18']);
        $product = Product::create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85.00']);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Red Horse', 'unit_price' => '85.00', 'quantity' => 1, 'line_total' => '85.00']);
        $item->forceFill(['status' => $status])->save();

        return $item;
    }

    public function test_open_occupies_the_table_and_logs(): void
    {
        $session = $this->sessions->open($this->table, $this->owner, 'birthday');

        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertSame('birthday', $session->notes);
        $this->assertSame(S::Occupied, $this->table->fresh()->status);
        $log = ActivityLog::where('action', 'session.opened')->sole();
        $this->assertSame(['table' => 'T1'], $log->new_values);
        $this->assertSame($session->id, $log->subject_id);
    }

    public function test_a_second_open_on_the_same_table_is_refused(): void
    {
        $this->sessions->open($this->table, $this->owner);

        $this->assertRefused(fn () => $this->sessions->open($this->table, $this->owner), 'table', 'Table T1 already has an open tab.');
        $this->assertSame(1, TableSession::count());
        $this->assertSame(S::Occupied, $this->table->fresh()->status);
    }

    public function test_an_inactive_table_is_refused(): void
    {
        app(DiningTableService::class)->setActive($this->table, false);

        $this->assertRefused(fn () => $this->sessions->open($this->table->fresh(), $this->owner), 'table', 'not in use');
        $this->assertSame(0, TableSession::count());
    }

    public function test_a_cleaning_table_is_refused(): void
    {
        app(TableStatusService::class)->change($this->table, S::Cleaning);

        $this->assertRefused(fn () => $this->sessions->open($this->table->fresh(), $this->owner), 'table', 'Table T1 is not free. Mark it available first.');
        $this->assertSame(0, TableSession::count());
        $this->assertSame(S::Cleaning, $this->table->fresh()->status);
    }

    public function test_an_occupied_table_without_a_tab_is_refused(): void
    {
        $this->table->forceFill(['status' => S::Occupied])->save();

        $this->assertRefused(fn () => $this->sessions->open($this->table->fresh(), $this->owner), 'table', 'not free');
        $this->assertSame(0, TableSession::count());
    }

    public function test_the_given_user_is_the_actor_of_the_table_log_without_a_login(): void
    {
        auth()->logout();

        $session = $this->sessions->open($this->table, $this->owner);
        $this->assertSame($this->owner->id, ActivityLog::where('action', 'table.status_changed')->sole()->user_id);

        $other = User::factory()->create()->assignRole('Owner');
        $this->sessions->cancel($session, $other, 'walked out');
        $this->assertSame([$this->owner->id, $other->id], ActivityLog::where('action', 'table.status_changed')->orderBy('id')->pluck('user_id')->all());
    }

    public function test_cancel_logs_the_real_old_status(): void
    {
        $session = $this->sessions->open($this->table, $this->owner);
        $session->forceFill(['status' => SessionStatus::Billing])->save();

        $this->sessions->cancel($session, $this->owner, 'walked out');

        $this->assertSame('billing', ActivityLog::where('action', 'session.cancelled')->sole()->old_values['status']);
    }

    public function test_cancel_needs_a_trimmed_reason_of_at_most_200_chars(): void
    {
        $session = $this->sessions->open($this->table, $this->owner);

        $this->assertRefused(fn () => $this->sessions->cancel($session, $this->owner, '   '), 'reason', 'Give a reason');
        $this->assertRefused(fn () => $this->sessions->cancel($session, $this->owner, str_repeat('x', 201)), 'reason', '200');
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);

        $this->sessions->cancel($session, $this->owner, '  walked out  ');
        $this->assertSame('walked out', ActivityLog::where('action', 'session.cancelled')->sole()->new_values['reason']);
    }

    public function test_a_reserved_table_can_be_opened(): void
    {
        app(TableStatusService::class)->change($this->table, S::Reserved);

        $this->sessions->open($this->table->fresh(), $this->owner);

        $this->assertSame(S::Occupied, $this->table->fresh()->status);
    }

    public function test_business_date_follows_the_cutoff(): void
    {
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 18)->setTime(1, 30));

        $session = $this->sessions->open($this->table, $this->owner);

        $this->assertSame('2026-11-17', $session->fresh()->business_date->toDateString());
    }

    public function test_cancel_an_empty_tab_frees_the_table(): void
    {
        $session = $this->sessions->open($this->table, $this->owner);

        $cancelled = $this->sessions->cancel($session, $this->owner, 'walked out');

        $this->assertSame(SessionStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->closed_at);
        $this->assertSame($this->owner->id, $cancelled->closed_by);
        $this->assertSame(S::Available, $this->table->fresh()->status);
        $log = ActivityLog::where('action', 'session.cancelled')->sole();
        $this->assertSame('walked out', $log->new_values['reason']);
    }

    public function test_cancel_is_refused_with_active_items_but_cancelled_items_do_not_block(): void
    {
        $session = $this->sessions->open($this->table, $this->owner);
        $this->addItem($session);

        $this->assertRefused(fn () => $this->sessions->cancel($session, $this->owner, 'x'), 'session', 'Cancel or remove the items first.');
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
        $this->assertSame(S::Occupied, $this->table->fresh()->status);

        $session->items()->update(['order_items.status' => 'cancelled']);
        $this->assertSame(SessionStatus::Cancelled, $this->sessions->cancel($session, $this->owner, 'x')->status);
    }

    public function test_cancel_needs_the_permission(): void
    {
        $session = $this->sessions->open($this->table, $this->owner);
        $cashier = User::factory()->create()->assignRole('Cashier');

        $this->expectException(AuthorizationException::class);
        $this->sessions->cancel($session, $cashier, 'x');
    }

    public function test_an_already_cancelled_tab_cannot_be_cancelled_again(): void
    {
        $session = $this->sessions->open($this->table, $this->owner);
        $this->sessions->cancel($session, $this->owner, 'x');

        $this->assertRefused(fn () => $this->sessions->cancel($session, $this->owner, 'again'), 'session', 'not open');
        $this->assertSame(1, ActivityLog::where('action', 'session.cancelled')->count());
    }
}

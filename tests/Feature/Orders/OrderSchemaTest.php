<?php

namespace Tests\Feature\Orders;

use App\Enums\PaymentStatus;
use App\Enums\SessionStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(DiningTable $table, User $user, string $status = 'open'): TableSession
    {
        $session = new TableSession(['dining_table_id' => $table->id, 'business_date' => '2026-11-18', 'opened_by' => $user->id, 'opened_at' => now()]);
        $session->forceFill(['status' => $status])->save();

        return $session;
    }

    private function order(TableSession $session, User $user, string $reference = 'YG-1'): Order
    {
        return Order::create(['reference' => $reference, 'table_session_id' => $session->id, 'created_by' => $user->id, 'business_date' => '2026-11-18']);
    }

    private function item(Order $order, array $attrs = []): OrderItem
    {
        $product = Product::create(['category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85.00']);

        return OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Red Horse',
            'unit_price' => '85.00', 'quantity' => 2, 'line_total' => '170.00', ...$attrs,
        ]);
    }

    public function test_only_one_open_or_billing_session_per_table(): void
    {
        $user = User::factory()->create();
        $table = DiningTable::create(['label' => 'T1']);
        $this->makeSession($table, $user, 'closed');
        $this->makeSession($table, $user, 'open');

        try {
            DB::transaction(fn () => $this->makeSession($table, $user, 'billing'));
            $this->fail('second live session allowed');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(2, TableSession::count());
        }

        $this->makeSession(DiningTable::create(['label' => 'T2']), $user); // another table is fine
    }

    public function test_lookup_columns_are_indexed(): void
    {
        foreach ([['orders', 'table_session_id'], ['table_sessions', 'business_date']] as [$table, $column]) {
            $found = DB::selectOne(
                "SELECT 1 AS ok FROM pg_indexes WHERE tablename = ? AND indexdef LIKE ?",
                [$table, "%({$column})%"],
            );
            $this->assertNotNull($found, "{$table}.{$column} has no index");
        }
    }

    public function test_invalid_statuses_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('table_sessions')->insert([
            'dining_table_id' => DiningTable::create(['label' => 'T2'])->id, 'status' => 'bogus', 'business_date' => '2026-11-18',
            'opened_by' => $user->id, 'opened_at' => now(),
        ]));
    }

    public function test_invalid_order_and_item_values_are_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->order($this->makeSession(DiningTable::create(['label' => 'T1']), $user), $user);
        $item = $this->item($order);

        $bad = [
            fn () => DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'bogus']),
            fn () => DB::table('orders')->where('id', $order->id)->update(['status' => 'bogus']),
            fn () => DB::table('order_items')->where('id', $item->id)->update(['quantity' => 0]),
            fn () => DB::table('order_items')->where('id', $item->id)->update(['discount' => '-1']),
            fn () => DB::table('order_items')->where('id', $item->id)->update(['line_total' => '-1']),
            fn () => DB::table('order_items')->where('id', $item->id)->update(['status' => 'bogus']),
        ];
        foreach ($bad as $i => $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail("check $i allowed bad value");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_defaults_and_casts(): void
    {
        $user = User::factory()->create();
        $session = $this->makeSession(DiningTable::create(['label' => 'T1']), $user);
        $order = $this->order($session, $user)->fresh();
        $item = $this->item($order)->fresh();

        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame('active', $item->status->value);
        $this->assertSame('0.00', $item->discount);
        $this->assertSame('170.00', $item->line_total);
        $this->assertNull($item->unit_cost);
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
        $this->assertTrue(SessionStatus::Billing->isOpen());
        $this->assertFalse(SessionStatus::Closed->isOpen());
    }

    public function test_relations(): void
    {
        $user = User::factory()->create();
        $table = DiningTable::create(['label' => 'T1']);
        $session = $this->makeSession($table, $user);
        $order = $this->order($session, $user);
        $item = $this->item($order);
        $item->product->delete(); // archived product still resolves

        $this->assertTrue($session->table->is($table));
        $this->assertTrue($session->openedBy->is($user));
        $this->assertTrue($session->orders->first()->is($order));
        $this->assertTrue($session->items->first()->is($item));
        $this->assertTrue($order->session->is($session));
        $this->assertTrue($order->cashier->is($user));
        $this->assertTrue($order->items->first()->is($item));
        $this->assertTrue($item->order->is($order));
        $this->assertSame('Red Horse', $item->fresh()->product->name);
        $this->assertNull($item->variant);
        $this->assertNull($item->cancelledBy);
    }
}

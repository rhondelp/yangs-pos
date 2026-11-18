<?php

namespace Tests\Feature\Orders;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Tables\DiningTableService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpenTabApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    private DiningTable $table;

    private Product $beer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->cashier = User::factory()->create()->assignRole('Cashier');

        $this->actingAs($this->owner);
        $this->table = app(DiningTableService::class)->create(['label' => 'T1']);
        $this->beer = app(ProductService::class)->create([
            'category_id' => Category::create(['name' => 'Beer'])->id, 'name' => 'Red Horse', 'type' => 'drink', 'price' => '85', 'cost' => '52.5',
        ]);
    }

    private function open(): int
    {
        return $this->postJson("/api/v1/tables/{$this->table->id}/sessions")->assertCreated()->json('data.id');
    }

    private function order(int $session, int $quantity = 2): array
    {
        return $this->postJson("/api/v1/sessions/{$session}/orders", ['lines' => [['product_id' => $this->beer->id, 'quantity' => $quantity]]])
            ->assertCreated()->assertJsonPath('data.payment_status', 'unpaid')->json('data');
    }

    public function test_cashier_runs_a_tab_over_http(): void
    {
        $this->actingAs($this->cashier, 'sanctum');
        $id = $this->open();

        $this->getJson("/api/v1/sessions/{$id}")->assertOk()
            ->assertJsonPath('data.status', 'open')->assertJsonPath('data.table.label', 'T1')->assertJsonPath('data.bill.total', '0.00');

        $first = $this->order($id);
        $this->order($id, 1);

        $this->getJson("/api/v1/sessions/{$id}")->assertOk()
            ->assertJsonCount(2, 'data.orders')
            ->assertJsonPath('data.bill.total', '255.00')
            ->assertJsonPath('data.orders.0.payment_status', 'unpaid')
            ->assertJsonPath('data.orders.0.items.0.line_total', '170.00');

        $this->patchJson('/api/v1/order-items/'.$first['items'][0]['id'], ['quantity' => 3])
            ->assertOk()->assertJsonPath('data.quantity', 3)->assertJsonPath('data.line_total', '255.00');

        $this->getJson('/api/v1/sessions')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.table.label', 'T1')->assertJsonPath('data.0.bill.total', '340.00');
    }

    public function test_second_open_is_rejected(): void
    {
        $this->actingAs($this->cashier, 'sanctum');
        $this->open();
        $this->postJson("/api/v1/tables/{$this->table->id}/sessions")->assertUnprocessable()->assertJsonValidationErrors('table');
    }

    public function test_cancelling_items_needs_the_permission(): void
    {
        $this->actingAs($this->cashier, 'sanctum');
        $itemId = $this->order($this->open())['items'][0]['id'];

        $this->postJson("/api/v1/order-items/{$itemId}/cancel", ['reason' => 'Wrong drink'])->assertForbidden();

        Role::findByName('Supervisor', 'web')->givePermissionTo('orders.cancel-item');
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->actingAs($supervisor, 'sanctum');

        $this->postJson("/api/v1/order-items/{$itemId}/cancel", ['reason' => 'Wrong drink', 'quantity' => 1])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.quantity', 1);

        $sessionId = OrderItem::with('order')->find($itemId)->order->table_session_id;
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/sessions/{$sessionId}")->assertJsonPath('data.bill.total', '85.00');
    }

    public function test_editing_an_item_of_a_cancelled_tab_is_refused(): void
    {
        $id = $this->open();
        $itemId = $this->order($id)['items'][0]['id'];

        $this->postJson("/api/v1/order-items/{$itemId}/cancel", ['reason' => 'Wrong drink'])->assertOk();
        $this->postJson("/api/v1/sessions/{$id}/cancel", ['reason' => 'Walked out'])->assertOk();

        $this->patchJson("/api/v1/order-items/{$itemId}", ['quantity' => 3])->assertUnprocessable();
    }

    public function test_cancelling_a_session(): void
    {
        $this->actingAs($this->cashier, 'sanctum');
        $id = $this->open();
        $this->postJson("/api/v1/sessions/{$id}/cancel", ['reason' => 'Walked out'])->assertForbidden();

        $this->actingAs($this->owner, 'sanctum');
        $this->postJson("/api/v1/sessions/{$id}/cancel", ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/sessions/{$id}/cancel", ['reason' => 'Walked out'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->getJson('/api/v1/sessions')->assertJsonCount(0, 'data');
    }

    public function test_validation_errors(): void
    {
        $this->actingAs($this->cashier, 'sanctum');
        $id = $this->open();

        $this->postJson("/api/v1/sessions/{$id}/orders", ['lines' => [['quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.product_id');
        $this->postJson("/api/v1/sessions/{$id}/orders", ['lines' => [['product_id' => $this->beer->id, 'quantity' => 0]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.quantity');
        $this->postJson("/api/v1/sessions/{$id}/orders", ['lines' => [['product_id' => $this->beer->id, 'quantity' => '2']]])->assertCreated();
        $this->postJson("/api/v1/sessions/{$id}/orders", ['lines' => []])->assertUnprocessable()->assertJsonValidationErrors('lines');

        $itemId = OrderItem::first()->id;
        $this->patchJson("/api/v1/order-items/{$itemId}", [])->assertUnprocessable();

        $this->actingAs($this->owner, 'sanctum');
        $this->postJson("/api/v1/order-items/{$itemId}/cancel", ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_unit_cost_is_hidden_from_the_cashier_but_not_the_owner(): void
    {
        $this->actingAs($this->cashier, 'sanctum');
        $id = $this->open();
        $this->assertArrayNotHasKey('unit_cost', $this->order($id)['items'][0]);
        $this->getJson("/api/v1/sessions/{$id}")->assertJsonMissingPath('data.orders.0.items.0.unit_cost');

        $this->actingAs($this->owner, 'sanctum');
        $this->getJson("/api/v1/sessions/{$id}")->assertJsonPath('data.orders.0.items.0.unit_cost', '52.50');
    }

    public function test_guests_are_unauthorised(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/sessions')->assertUnauthorized();
        $this->postJson("/api/v1/tables/{$this->table->id}/sessions")->assertUnauthorized();
        $this->patchJson('/api/v1/order-items/1', ['quantity' => 2])->assertUnauthorized();
    }
}

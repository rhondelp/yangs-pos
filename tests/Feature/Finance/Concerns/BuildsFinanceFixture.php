<?php

namespace Tests\Feature\Finance\Concerns;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\BillService;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Corrections\PaymentCorrectionService;
use App\Services\Corrections\RefundService;
use App\Services\Corrections\VoidService;
use App\Services\Finance\ExpenseService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\RecipeService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/**
 * Two business days of real records with hand-calculated results (plan: docs/superpowers/plans/2026-11-23-profit-and-loss.md).
 *
 * 2026-11-20: Tab A (cashier) gross 850, discounts 112 (line 30 + bill 82), net 738; Tab B (owner) 200.
 *             COGS 220 + 80 = 300; losses: wasted void 40 + wasted cancel 50 + stock-out 100 = 190;
 *             purchases 1000; expenses 1500 (999 voided). Tab C open, Tab D cancelled: not sales.
 * 2026-11-21: refund 50 cash from Tab B; expense Rent 5000.
 */
trait BuildsFinanceFixture
{
    private User $owner;

    private User $cashier;

    /** @var array<string, Product> */
    private array $products = [];

    private Payment $tabBPayment;

    private function buildFinanceFixture(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, PaymentMethodSeeder::class, ExpenseCategorySeeder::class]);
        $this->owner = User::factory()->create(['name' => 'Olivia Owner'])->assignRole('Owner');
        $this->cashier = User::factory()->create(['name' => 'Carlo Cashier'])->assignRole('Cashier');
        $this->travelTo(CarbonImmutable::parse('2026-11-20 21:00', 'Asia/Manila'));
        $this->actingAs($this->owner);

        $gin = app(InventoryItemService::class)->create(['name' => 'Gin', 'base_unit' => 'shot']);
        app(InventoryService::class)->receive($this->owner, ['received_at' => '2026-11-20T15:00:00+08:00',
            'lines' => [['inventory_item_id' => $gin->id, 'quantity' => '20', 'line_cost' => '1000']]]); // average 50.00

        $this->products['Beer'] = $this->product('Beer', 'Beer', '100', '40');
        $this->products['Gin Tonic'] = $this->product('Gin Tonic', 'Cocktails', '150', '0', $gin);
        $this->products['Sisig'] = $this->product('Sisig', 'Food', '250', null);

        // Tab A
        $tabA = $this->openTab('T1', $this->cashier);
        $beer = $this->line($tabA, 'Beer', 4);
        app(OrderService::class)->updateItem($beer, $this->owner, ['discount' => '40']); // line total 360
        $ginTonic = $this->line($tabA, 'Gin Tonic', 3);
        $this->line($tabA, 'Sisig', 1);
        app(OrderService::class)->cancelItem($this->line($tabA, 'Beer', 2), $this->owner, 'Wrong table');
        app(OrderService::class)->cancelItem($this->line($tabA, 'Gin Tonic', 1), $this->owner, 'Spilled', null, false); // loss 50
        $cash100 = $this->pay($tabA, $this->cashier, 'cash', '100');
        app(VoidService::class)->voidItem($ginTonic->fresh(), $this->owner, 1, 'Changed mind'); // back to stock
        app(VoidService::class)->voidItem($beer->fresh(), $this->owner, 1, 'Spilled', [], null, false); // loss 40
        app(BillService::class)->present($tabA, $this->cashier);
        app(BillService::class)->applyDiscount($tabA, $this->owner, 'percent', '10', 'Regular'); // 82.00
        $this->pay($tabA, $this->cashier, 'gcash', '638', 'GC-638'); // closes at 738.00
        app(PaymentCorrectionService::class)->correct($cash100, $this->owner, ['method_id' => $this->method('gcash'), 'reference_no' => 'GC-100', 'reason' => 'Was GCash']);

        // Tab B
        $tabB = $this->openTab('T2', $this->owner);
        $this->line($tabB, 'Beer', 2);
        $this->tabBPayment = $this->pay($tabB, $this->owner, 'cash', '200');

        // Tab C: open, not a sale
        $this->line($this->openTab('T3', $this->cashier), 'Beer', 1);

        // Tab D: cancelled, not a sale
        $tabD = $this->openTab('T4', $this->cashier);
        app(OrderService::class)->cancelItem($this->line($tabD, 'Beer', 1), $this->owner, 'Walked out');
        app(TableSessionService::class)->cancel($tabD->fresh(), $this->owner, 'Walked out');

        $expenses = app(ExpenseService::class);
        $expenses->record($this->owner, $this->expense('Electricity', '1500', '2026-11-20'));
        $expenses->void($expenses->record($this->owner, $this->expense('Other', '999', '2026-11-20')), $this->owner, 'Duplicate');
        app(InventoryService::class)->stockOut($gin->fresh(), $this->owner, ['quantity' => '2', 'reason' => 'damaged']); // loss 100

        $this->travelTo(CarbonImmutable::parse('2026-11-21 21:00', 'Asia/Manila'));
        app(RefundService::class)->refund($this->tabBPayment, $this->owner, ['amount' => '50', 'reason' => 'Flat beer', 'idempotency_key' => (string) Str::uuid()]);
        $expenses->record($this->owner, $this->expense('Rent', '5000', '2026-11-21'));
    }

    private function product(string $name, string $category, string $price, ?string $cost, ?InventoryItem $stock = null): Product
    {
        $product = app(ProductService::class)->create(['category_id' => Category::firstOrCreate(['name' => $category])->id,
            'name' => $name, 'type' => $category === 'Food' ? 'food' : 'drink', 'price' => $price, 'cost' => $cost, 'track_inventory' => $stock !== null]);
        if ($stock) {
            app(RecipeService::class)->save($product, null, [['inventory_item_id' => $stock->id, 'quantity' => '1']]);
        }

        return $product;
    }

    private function openTab(string $label, User $by): TableSession
    {
        return app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => $label, 'capacity' => 4]), $by);
    }

    private function line(TableSession $tab, string $product, int $quantity): OrderItem
    {
        $order = app(OrderService::class)->addOrder($tab, $this->owner, [['product_id' => $this->products[$product]->id, 'quantity' => $quantity]]);

        return $order->items()->sole();
    }

    private function pay(TableSession $tab, User $by, string $method, string $amount, ?string $reference = null): Payment
    {
        return app(PaymentService::class)->record($tab, $by, ['method_id' => $this->method($method), 'amount' => $amount,
            'reference_no' => $reference, 'idempotency_key' => (string) Str::uuid()]);
    }

    private function method(string $code): int
    {
        return PaymentMethod::where('code', $code)->value('id');
    }

    private function expense(string $category, string $amount, string $date): array
    {
        return ['expense_category_id' => ExpenseCategory::where('name', $category)->value('id'), 'description' => "{$category} bill",
            'amount' => $amount, 'expense_date' => $date];
    }
}

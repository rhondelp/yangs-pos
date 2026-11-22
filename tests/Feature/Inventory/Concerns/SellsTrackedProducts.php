<?php

namespace Tests\Feature\Inventory\Concerns;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Catalog\ProductService;
use App\Services\Inventory\RecipeService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use App\Services\Tables\DiningTableService;
use Illuminate\Support\Str;

/** Sells a tracked product that takes one unit of an inventory item per sale. */
trait SellsTrackedProducts
{
    private ?TableSession $tab = null;

    private function trackedProduct(InventoryItem $item, string $perSale = '1'): Product
    {
        $product = app(ProductService::class)->create(['category_id' => Category::firstOrCreate(['name' => 'Drinks'])->id,
            'name' => "{$item->name} drink", 'type' => 'drink', 'price' => '100', 'cost' => '0', 'track_inventory' => true]);
        app(RecipeService::class)->save($product, null, [['inventory_item_id' => $item->id, 'quantity' => $perSale]]);

        return $product;
    }

    /** @param list<array{0: Product, 1: int}> $lines */
    private function sell(User $by, array $lines): Order
    {
        $this->tab ??= app(TableSessionService::class)->open(app(DiningTableService::class)->create(['label' => 'T'.Str::random(4), 'capacity' => 4]), $by);

        return app(OrderService::class)->addOrder($this->tab, $by, array_map(fn (array $l) => ['product_id' => $l[0]->id, 'quantity' => $l[1]], $lines));
    }

    /** Pays the open tab in full in cash, which closes it. Needs PaymentMethodSeeder. */
    private function payTab(User $by, string $amount): Payment
    {
        $payment = app(PaymentService::class)->record($this->tab, $by, [
            'method_id' => PaymentMethod::where('code', 'cash')->value('id'), 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->tab = null;

        return $payment;
    }
}

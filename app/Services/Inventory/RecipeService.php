<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecipeComponent;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What one sale of a product (or one of its options) takes from stock, in base units.
 * Edits only affect future sales: past cost is in order snapshots and past stock in the ledger.
 */
class RecipeService
{
    private const QUANTITY = '/^\d{1,10}(\.\d{1,3})?$/';

    public function __construct(private ActivityLogger $logger) {}

    /**
     * Replaces the recipe of $product sold as $variant (null = without an option).
     *
     * @param  list<array{inventory_item_id: mixed, quantity: mixed}>  $components  empty clears the recipe
     *
     * @throws ValidationException
     */
    public function save(Product $product, ?ProductVariant $variant, array $components): void
    {
        if ($variant !== null && $variant->product_id !== $product->id) {
            $this->reject('product_variant_id', "That is not an option of {$product->name}.");
        }

        $components = array_values($components);
        $items = InventoryItem::whereKey(array_filter(array_column($components, 'inventory_item_id'), 'is_numeric'))->get()->keyBy('id');
        $seen = [];
        foreach ($components as $i => $component) {
            $item = is_numeric($component['inventory_item_id'] ?? null) ? $items->get((int) $component['inventory_item_id']) : null;
            if ($item === null) {
                $this->reject("components.{$i}.inventory_item_id", 'Choose an inventory item that is in use.');
            }
            if (isset($seen[$item->id])) {
                $this->reject("components.{$i}.inventory_item_id", "{$item->name} is listed twice.");
            }
            $quantity = (string) ($component['quantity'] ?? '');
            if (! preg_match(self::QUANTITY, $quantity) || bccomp($quantity, '0', 3) <= 0) {
                $this->reject("components.{$i}.quantity", "Enter how many {$item->base_unit} one sale uses, up to 3 decimals.");
            }
            $seen[$item->id] = true;
            $components[$i] = ['item' => $item, 'quantity' => $quantity];
        }

        DB::transaction(function () use ($product, $variant, $components) {
            $scope = RecipeComponent::where('product_id', $product->id)->where('product_variant_id', $variant?->id);
            $old = $this->describe((clone $scope)->with('item')->get());
            (clone $scope)->delete(); // recipe rows are configuration, not history; the change is logged below

            foreach ($components as $c) {
                RecipeComponent::create(['product_id' => $product->id, 'product_variant_id' => $variant?->id, 'inventory_item_id' => $c['item']->id, 'quantity' => $c['quantity']]);
            }

            $new = $this->describe((clone $scope)->with('item')->get());
            if ($old != $new) {
                $this->logger->log('product.recipe_changed', $product,
                    ['option' => $variant?->name, 'components' => $old],
                    ['option' => $variant?->name, 'components' => $new]);
            }
        });
    }

    /** @return array<string, string> item name => base quantity */
    private function describe($rows): array
    {
        return $rows->mapWithKeys(fn (RecipeComponent $r) => [$r->item->name => $r->quantity])->all();
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

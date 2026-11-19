<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Inventory\InventoryService;

/**
 * Where order code meets inventory. Each call runs inside the caller's transaction, after the session lock;
 * InventoryService then locks the inventory items in ascending id (CLAUDE.md "Inventory").
 */
class StockHook
{
    public function __construct(private InventoryService $inventory) {}

    /** @return list<string> warnings for staff (an item went below zero) */
    public function deductForOrder(Order $order, User $by): array
    {
        return $this->inventory->deductForOrder($order, $by);
    }

    /** @return list<string> */
    public function quantityAdded(OrderItem $item, int $added, User $by): array
    {
        return $this->inventory->deductForQuantity($item, $added, $by);
    }

    /** $item is the cancelled line (it carries the sale's stock snapshot). */
    public function itemCancelled(OrderItem $item, int $quantity, User $by, bool $returnToStock): void
    {
        if ($returnToStock) {
            $this->inventory->returnForLine($item, $quantity, $by);
        }
    }

    /** Before changing several lines at once: lock all their stock in one ascending pass. */
    public function lockStockOf(iterable $lines): void
    {
        $this->inventory->lockStockOf($lines);
    }

    public function itemVoided(OrderItem $item, int $quantity, User $by, bool $returnToStock): void
    {
        if ($returnToStock) {
            $this->inventory->returnForLine($item, $quantity, $by);
        }
    }
}

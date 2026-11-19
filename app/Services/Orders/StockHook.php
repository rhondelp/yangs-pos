<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;

/** Seam for inventory. Phase 3's StockService goes here; both methods run inside the caller's transaction. */
class StockHook
{
    public function deductForOrder(Order $order): void
    {
        // Phase 3: StockService deducts stock for the order's items here (inside the caller's transaction).
    }

    public function quantityAdded(OrderItem $item, int $added): void
    {
        // Phase 3: StockService deducts the extra units here (inside the caller's transaction).
    }

    public function itemCancelled(OrderItem $item, int $quantity): void
    {
        // Phase 3: StockService restores or writes off stock here (inside the caller's transaction).
    }

    public function itemVoided(OrderItem $item, int $quantity): void
    {
        // Phase 3: StockService asks "back to stock?" and restores or writes off the units here (inside the caller's transaction).
    }
}

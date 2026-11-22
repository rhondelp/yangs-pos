<?php

namespace App\Events;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A sale or stock-out moved an item into a worse state (InventoryItem::STATUS_RANK), once per crossing.
 * $from and $to are statusFor() keys. Listened to in Phase 6.
 */
class StockLevelCrossed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public InventoryItem $item, public string $from, public string $to, public InventoryMovement $movement) {}
}

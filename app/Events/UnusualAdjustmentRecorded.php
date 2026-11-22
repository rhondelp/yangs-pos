<?php

namespace App\Events;

use App\Models\InventoryMovement;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A manual stock-out worth at least the unusual_adjustment_amount setting (possible loss or theft). Listened to in Phase 6. */
class UnusualAdjustmentRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public InventoryMovement $movement) {}
}

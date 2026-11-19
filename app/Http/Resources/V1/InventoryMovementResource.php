<?php

namespace App\Http\Resources\V1;

use App\Models\StockReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One ledger row. `reference` names the document that caused it (a stock-in for now). @mixin \App\Models\InventoryMovement */
class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'balance_after' => $this->balance_after,
            'reason' => $this->reason,
            'note' => $this->note,
            'reference' => $this->source instanceof StockReceiptItem ? $this->source->receipt->reference : null,
            'business_date' => $this->business_date->toDateString(),
            'created_by' => $this->createdBy?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

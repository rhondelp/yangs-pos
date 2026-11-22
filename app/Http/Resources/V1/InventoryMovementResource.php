<?php

namespace App\Http\Resources\V1;

use App\Models\OrderItem;
use App\Models\StockReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One ledger row. `reference` names the document that caused it: the stock-in, or the order of a sale or return. @mixin \App\Models\InventoryMovement */
class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'item' => $this->whenLoaded('item', fn () => $this->item->name),
            'base_unit' => $this->whenLoaded('item', fn () => $this->item->base_unit),
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'balance_after' => $this->balance_after,
            'reason' => $this->reason,
            'note' => $this->note,
            'is_unusual' => $this->is_unusual,
            'reference' => match (true) {
                $this->source instanceof StockReceiptItem => $this->source->receipt->reference,
                $this->source instanceof OrderItem => $this->source->order->reference,
                default => null,
            },
            'business_date' => $this->business_date->toDateString(),
            'created_by' => $this->createdBy?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

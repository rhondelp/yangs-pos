<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StockReceipt */
class StockReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'name' => $this->supplier->name] : null),
            'invoice_no' => $this->invoice_no,
            'received_at' => $this->received_at->toIso8601String(),
            'business_date' => $this->business_date->toDateString(),
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy?->name),
            'total_cost' => $this->total_cost,
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'id' => $l->id,
                'inventory_item_id' => $l->inventory_item_id,
                'item' => $l->relationLoaded('item') ? $l->item->name : null,
                'unit_name' => $l->unit_name,
                'unit_factor' => $l->unit_factor,
                'quantity' => $l->quantity,
                'base_quantity' => $l->base_quantity,
                'line_cost' => $l->line_cost,
                'base_unit_cost' => $l->base_unit_cost,
            ])->values()),
        ];
    }
}

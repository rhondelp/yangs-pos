<?php

namespace App\Http\Resources\V1;

use App\Enums\OrderItemStatus;
use App\Services\Orders\RunningBill;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One order round (YANGS_SPEC §4C). `table` needs session.table loaded; `cashier` needs cashier; `total` needs items.voids. @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'table_session_id' => $this->table_session_id,
            'table' => $this->whenLoaded('session', fn () => $this->session->table->label),
            'cashier' => $this->whenLoaded('cashier', fn () => ['id' => $this->cashier->id, 'name' => $this->cashier->name]),
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            // Active lines net of voids; cancelled lines count for nothing.
            'total' => $this->whenLoaded('items', fn () => $this->items
                ->where('status', OrderItemStatus::Active)
                ->reduce(fn (string $sum, $item) => Money::add($sum, RunningBill::lineTotal($item, (int) $item->voids->sum('quantity'))), '0.00')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

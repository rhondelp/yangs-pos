<?php

namespace App\Http\Resources\V1;

use App\Services\Orders\RunningBill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TableSession */
class TableSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'table' => ['id' => $this->dining_table_id, 'label' => $this->whenLoaded('table', fn () => $this->table->label)],
            'business_date' => $this->business_date->toDateString(),
            'opened_at' => $this->opened_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'notes' => $this->notes,
            // shortcut: one bill query set per tab; fine for the handful of live tabs a bar has, batch if it grows.
            'bill' => app(RunningBill::class)->calculate($this->resource),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
        ];
    }
}

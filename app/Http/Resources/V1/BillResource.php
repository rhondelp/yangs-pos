<?php

namespace App\Http\Resources\V1;

use App\Enums\OrderItemStatus;
use App\Enums\SessionStatus;
use App\Services\Orders\RunningBill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A tab seen from the bill screen: status, the running bill with payments, and the receipt once closed. @mixin \App\Models\TableSession */
class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $closed = $this->status === SessionStatus::Closed;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'table' => $this->table->label,
            'receipt_number' => $this->receipt_number,
            'receipt_url' => $closed && $request->user()?->can('receipts.view') ? route('receipts.show', $this->resource) : null,
            'bill' => app(RunningBill::class)->calculate($this->resource),
            'items' => $this->items()->where('order_items.status', OrderItemStatus::Active)->get()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->variant_name ? "{$item->product_name}, {$item->variant_name}" : $item->product_name,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ])->all(),
        ];
    }
}

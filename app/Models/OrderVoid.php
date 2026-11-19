<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['table_session_id', 'order_id', 'order_item_id', 'quantity', 'amount', 'reason', 'voided_by', 'approved_by', 'business_date', 'idempotency_key', 'returned_to_stock'])]
class OrderVoid extends Model
{
    use AppendOnly;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_to_stock' => 'boolean',
            'amount' => 'decimal:2',
            'business_date' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}

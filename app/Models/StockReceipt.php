<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A delivery received from a supplier. Written once by InventoryService::receive. */
class StockReceipt extends Model
{
    use AppendOnly;

    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
            'business_date' => 'date',
            'total_cost' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class)->orderBy('id');
    }
}

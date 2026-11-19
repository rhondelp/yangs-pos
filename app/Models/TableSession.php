<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

// `status`, the bill discount, the frozen totals and the receipt number are not fillable: only the tab and billing services set them.
#[Fillable(['dining_table_id', 'business_date', 'opened_by', 'opened_at', 'closed_by', 'closed_at', 'notes'])]
class TableSession extends Model
{
    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'business_date' => 'date',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'billed_at' => 'immutable_datetime',
            'bill_discount_value' => 'decimal:2',
            'gross_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'net_total' => 'decimal:2',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('id');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, Order::class)->orderBy('order_items.id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }
}

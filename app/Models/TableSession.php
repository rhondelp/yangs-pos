<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

// `status` is deliberately not fillable: only the tab service changes it.
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
}

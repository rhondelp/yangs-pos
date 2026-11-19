<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Name, price and cost are snapshots taken at order time; `status`, `stock_components` and `returned_to_stock`
// are set by services via forceFill (stock fields only by InventoryService and the cancel flow).
#[Fillable([
    'order_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name',
    'unit_price', 'unit_cost', 'quantity', 'discount', 'line_total', 'notes',
])]
class OrderItem extends Model
{
    protected $attributes = ['status' => 'active', 'discount' => '0.00'];

    protected function casts(): array
    {
        return [
            'status' => OrderItemStatus::class,
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
            'cancelled_at' => 'immutable_datetime',
            'stock_components' => 'array',
            'returned_to_stock' => 'boolean',
        ];
    }

    public function voids(): HasMany
    {
        return $this->hasMany(OrderVoid::class)->orderBy('id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

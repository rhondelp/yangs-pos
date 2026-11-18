<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// `status` and `payment_status` are deliberately not fillable: services set them with forceFill.
#[Fillable(['reference', 'table_session_id', 'created_by', 'business_date', 'notes'])]
class Order extends Model
{
    protected $attributes = ['status' => 'open', 'payment_status' => 'unpaid'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'business_date' => 'date',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

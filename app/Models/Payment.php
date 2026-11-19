<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['table_session_id', 'payment_method_id', 'amount', 'tendered', 'change_given', 'reference_no', 'idempotency_key', 'received_by', 'paid_at', 'business_date'])]
class Payment extends Model
{
    use AppendOnly;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tendered' => 'decimal:2',
            'change_given' => 'decimal:2',
            'paid_at' => 'immutable_datetime',
            'business_date' => 'date',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

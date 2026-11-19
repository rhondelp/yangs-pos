<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['table_session_id', 'payment_id', 'payment_method_id', 'amount', 'reference_no', 'reason', 'idempotency_key', 'refunded_by', 'approved_by', 'business_date'])]
class Refund extends Model
{
    use AppendOnly;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'business_date' => 'date',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}

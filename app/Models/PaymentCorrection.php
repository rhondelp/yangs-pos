<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'table_session_id', 'old_payment_method_id', 'new_payment_method_id', 'old_reference_no', 'new_reference_no', 'reason', 'corrected_by', 'approved_by'])]
class PaymentCorrection extends Model
{
    use AppendOnly;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function newMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'new_payment_method_id');
    }

    public function oldMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'old_payment_method_id');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}

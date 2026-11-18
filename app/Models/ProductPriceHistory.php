<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'product_variant_id', 'field', 'old_value', 'new_value', 'changed_by'])]
class ProductPriceHistory extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

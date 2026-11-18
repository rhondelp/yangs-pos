<?php

namespace App\Models;

use App\Models\Concerns\HasAuditedSoftDeletes;
use App\Models\Concerns\HasMargin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An optional option of a product with its own price, e.g. "Bucket of 6". */
#[Fillable(['product_id', 'name', 'price', 'cost', 'sort_order'])]
class ProductVariant extends Model
{
    use HasAuditedSoftDeletes, HasMargin;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

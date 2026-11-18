<?php

namespace App\Models;

use App\Models\Concerns\HasAuditedSoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sort_order'])]
class Category extends Model
{
    use HasAuditedSoftDeletes;

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

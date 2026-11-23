<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Fixed list seeded by ExpenseCategorySeeder. There is no "Product purchases" category: purchases are stock-in. */
#[Fillable(['name', 'is_active'])]
class ExpenseCategory extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// `code` and `is_cash` are fixed once created: cash behaviour (change) belongs to the seeded Cash method.
#[Fillable(['name', 'requires_reference', 'is_active', 'sort_order'])]
class PaymentMethod extends Model
{
    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}

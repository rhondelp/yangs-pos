<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// `status` is deliberately not fillable: only TableStatusService changes it.
#[Fillable(['label', 'capacity', 'is_active', 'sort_order'])]
class DiningTable extends Model
{
    protected $attributes = [
        'status' => 'available',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function floorOrder(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('label');
    }
}

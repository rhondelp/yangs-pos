<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Concerns\HasAuditedSoftDeletes;
use App\Models\Concerns\HasMargin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'name', 'description', 'type', 'price', 'cost', 'image_path', 'is_available', 'track_inventory'])]
class Product extends Model
{
    use HasAuditedSoftDeletes, HasMargin;

    protected $attributes = [
        'is_available' => true,
        'track_inventory' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'is_available' => 'boolean',
            'track_inventory' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class)->orderByDesc('id');
    }

    /** What the POS may sell: not archived, marked available, in an active category. */
    #[Scope]
    protected function sellable(Builder $query): void
    {
        // The category relation includes archived ones for display; the menu must not.
        $query->where('is_available', true)->whereHas('category', fn (Builder $q) => $q->withoutTrashed());
    }

    /**
     * List filters shared by web and API.
     *
     * @param array{q?: ?string, category_id?: ?int, status?: ?string} $filters status: active (default) | archived | all
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        match ($filters['status'] ?? 'active') {
            'archived' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
            default => null,
        };

        $query
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where('name', 'ilike', '%'.addcslashes($term, '%_\\').'%'))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id));
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}

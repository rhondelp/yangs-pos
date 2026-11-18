<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

// Append-only: a database trigger rejects UPDATE and DELETE.
#[Fillable(['user_id', 'user_name', 'approved_by', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip_address'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('activity_logs is append-only; record a new entry instead.');
        static::updating($refuse);
        static::deleting($refuse);
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Viewer filters. Dates are Manila calendar days, both ends inclusive.
     *
     * @param array{user_id?: ?int, action?: ?string, from?: ?string, to?: ?string} $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $tz = config('app.timezone');

        $query
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['action'] ?? null, fn (Builder $q, $action) => $q->where('action', $action))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->where('created_at', '>=', CarbonImmutable::parse($from, $tz)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->where('created_at', '<', CarbonImmutable::parse($to, $tz)->addDay()->startOfDay()));
    }
}

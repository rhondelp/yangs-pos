<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\StockReceiptItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/** Read-only stock reports shared by the web pages and the API. Writes stay in InventoryService. */
class InventoryReportService
{
    /**
     * Items matching the filters, by name, with a valuation summary of those same rows.
     * Value per item = max(on_hand, 0) × average cost, rounded per row; the total is the sum of the rounded rows.
     *
     * @param  array{search: ?string, category_id: ?int, status: ?string, archived: bool}  $filters
     * @return array{items: Collection<int, InventoryItem>, summary: array{total_value: string, item_count: int, low: int, out: int, negative: int}}
     */
    public function levels(array $filters): array
    {
        // shortcut: unpaginated, fine for a bar's item list; paginate past ~500 items
        $items = InventoryItem::query()
            ->when($filters['archived'], fn (Builder $q) => $q->withTrashed())
            ->when($filters['search'], function (Builder $q, string $search) {
                $like = '%'.addcslashes($search, '\%_').'%';
                $q->where(fn (Builder $q) => $q->where('name', 'ilike', $like)->orWhere('sku', 'ilike', $like));
            })
            ->when($filters['category_id'], fn (Builder $q, int $id) => $q->where('category_id', $id))
            // The same rules as InventoryItem::stockStatus(): archived items never need attention. The DB keeps low_stock_level >= 0.
            ->when($filters['status'], fn (Builder $q, string $status) => $q->whereNull('deleted_at'))
            ->when($filters['status'], fn (Builder $q, string $status) => match ($status) {
                'negative' => $q->where('on_hand', '<', 0),
                'out' => $q->where('on_hand', '=', 0),
                'low' => $q->where('on_hand', '>', 0)->whereColumn('on_hand', '<=', 'low_stock_level'),
                'attention' => $q->whereColumn('on_hand', '<=', 'low_stock_level'),
            })
            ->with('units', 'category')
            ->orderBy('name')
            ->get();

        $statuses = $items->map(fn (InventoryItem $item) => $item->stockStatus())->countBy();

        return ['items' => $items, 'summary' => [
            'total_value' => $items->reduce(fn (string $sum, InventoryItem $item) => bcadd($sum, $item->stockValue(), 2), '0.00'),
            'item_count' => $items->count(),
            'low' => $statuses->get('low_stock', 0),
            'out' => $statuses->get('out_of_stock', 0),
            'negative' => $statuses->get('negative_stock', 0),
        ]];
    }

    /**
     * Every movement matching the filters, newest first, 50 a page. Movements of archived items are always included.
     *
     * @param  array{item_id: ?int, type: ?string, from: ?string, to: ?string, user_id: ?int}  $filters  dates are business dates
     */
    public function movements(array $filters): LengthAwarePaginator
    {
        return InventoryMovement::query()
            ->when($filters['item_id'], fn (Builder $q, int $id) => $q->where('inventory_item_id', $id))
            ->when($filters['type'], fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['from'], fn (Builder $q, string $from) => $q->where('business_date', '>=', $from))
            ->when($filters['to'], fn (Builder $q, string $to) => $q->where('business_date', '<=', $to))
            ->when($filters['user_id'], fn (Builder $q, int $id) => $q->where('created_by', $id))
            ->with(['item', 'createdBy', 'source' => fn (MorphTo $source) => $source->morphWith([
                StockReceiptItem::class => ['receipt'],
                OrderItem::class => ['order.session'],
            ])])
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();
    }

    /** Users who recorded at least one movement, for the user filter. @return array<int, string> */
    public function movementUsers(): array
    {
        return User::whereIn('id', InventoryMovement::select('created_by'))->orderBy('name')->pluck('name', 'id')->all();
    }
}

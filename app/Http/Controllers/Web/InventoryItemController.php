<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryLevelFilterRequest;
use App\Http\Requests\Inventory\SaveInventoryItemRequest;
use App\Http\Requests\Inventory\SaveInventoryUnitsRequest;
use App\Http\Requests\Inventory\StockOutRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\StockReceiptItem;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryReportService;
use App\Services\Inventory\InventoryService;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function __construct(private InventoryItemService $items) {}

    public function index(InventoryLevelFilterRequest $request, InventoryReportService $reports): View
    {
        $filters = $request->filters();

        return view('inventory.index', [
            ...$reports->levels($filters),
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
            // The filter also lists archived categories, so items still filed under one can be found.
            'filterCategories' => $this->categoryOptions(all: true),
        ]);
    }

    public function store(SaveInventoryItemRequest $request): RedirectResponse
    {
        $item = $this->items->create($request->itemData());

        return redirect()->route('inventory.show', $item)->with('status', "{$item->name} added. Add its units (for example a case) if you buy it in bigger packs.");
    }

    public function show(InventoryItem $item): View
    {
        return view('inventory.show', [
            'item' => $item->load('units', 'category'),
            'categories' => $this->categoryOptions($item->category_id),
            'movements' => $item->movements()
                ->with(['createdBy', 'source' => fn (MorphTo $source) => $source->morphWith([StockReceiptItem::class => ['receipt']])])
                ->paginate(50),
        ]);
    }

    public function update(SaveInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->items->update($item, $request->itemData()); // ValidationException goes back to the form

        return redirect()->route('inventory.show', $item)->with('status', "{$item->name} saved.");
    }

    public function units(SaveInventoryUnitsRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->items->saveUnits($item, $request->units());

        return redirect()->route('inventory.show', $item)->with('status', 'Units saved.');
    }

    public function stockOut(StockOutRequest $request, InventoryItem $item, InventoryService $inventory): RedirectResponse
    {
        $movement = $inventory->stockOut($item, $request->user(), $request->validated()); // ValidationException goes back to the card

        return redirect()->route('inventory.show', $item)->with('status', 'Stock-out of '.Quantity::format(ltrim($movement->quantity, '-'))." {$item->base_unit} recorded.");
    }

    public function archive(InventoryItem $item): RedirectResponse
    {
        $this->items->archive($item);

        return redirect()->route('inventory.index')->with('status', "{$item->name} archived.");
    }

    public function restore(InventoryItem $item): RedirectResponse
    {
        $this->items->restore($item);

        return redirect()->route('inventory.show', $item)->with('status', "{$item->name} restored.");
    }

    /** Active categories for the item forms, plus the item's own category when it is archived ($all: every category). @return array<int, string> */
    private function categoryOptions(?int $current = null, bool $all = false): array
    {
        return Category::withTrashed()
            ->unless($all, fn ($q) => $q->where(fn ($q) => $q->whereNull('deleted_at')->when($current, fn ($q) => $q->orWhere('id', $current))))
            ->orderBy('sort_order')->orderBy('name')->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->trashed() ? __(':name (archived)', ['name' => $c->name]) : $c->name])
            ->all();
    }
}

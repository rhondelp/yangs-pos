<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryItemRequest;
use App\Http\Requests\Inventory\SaveInventoryUnitsRequest;
use App\Http\Requests\Inventory\StockOutRequest;
use App\Models\InventoryItem;
use App\Models\StockReceiptItem;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function __construct(private InventoryItemService $items) {}

    public function index(): View
    {
        return view('inventory.index', ['items' => InventoryItem::withTrashed()->with('units')->orderBy('name')->get()]);
    }

    public function store(SaveInventoryItemRequest $request): RedirectResponse
    {
        $item = $this->items->create($request->itemData());

        return redirect()->route('inventory.show', $item)->with('status', "{$item->name} added. Add its units (for example a case) if you buy it in bigger packs.");
    }

    public function show(InventoryItem $item): View
    {
        return view('inventory.show', [
            'item' => $item->load('units'),
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
}

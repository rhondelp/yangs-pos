<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockReceiptRequest;
use App\Models\InventoryItem;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockReceiptController extends Controller
{
    public function index(): View
    {
        return view('stock-receipts.index', [
            'receipts' => StockReceipt::with('supplier', 'receivedBy')->withCount('lines')->orderByDesc('received_at')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('stock-receipts.create', [
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id')->all(),
            // What the line editor needs: each item's base unit and its other units.
            'items' => InventoryItem::with('units')->orderBy('name')->get()->map(fn (InventoryItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'base_unit' => $i->base_unit,
                'units' => $i->units->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'factor' => $u->factor])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function store(StockReceiptRequest $request, InventoryService $inventory): RedirectResponse
    {
        $receipt = $inventory->receive($request->user(), $request->validated()); // ValidationException goes back with the lines kept

        return redirect()->route('stock-receipts.show', $receipt)->with('status', "Stock-in {$receipt->reference} recorded.");
    }

    public function show(StockReceipt $receipt): View
    {
        return view('stock-receipts.show', ['receipt' => $receipt->load('lines.item', 'supplier', 'receivedBy')]);
    }
}

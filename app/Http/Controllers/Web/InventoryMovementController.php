<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\MovementFilterRequest;
use App\Models\InventoryItem;
use App\Services\Inventory\InventoryReportService;
use Illuminate\View\View;

// Authorization: route can:inventory.view.
class InventoryMovementController extends Controller
{
    /** Every stock movement across items; ?type=adjustment is the Adjustments tab. */
    public function index(MovementFilterRequest $request, InventoryReportService $reports): View
    {
        $filters = $request->filters();

        return view('inventory.movements', [
            'filters' => $filters,
            'movements' => $reports->movements($filters),
            // Archived items stay in the list: their history is still traceable.
            'items' => InventoryItem::withTrashed()->orderBy('name')->get()
                ->mapWithKeys(fn (InventoryItem $i) => [$i->id => $i->trashed() ? __(':name (archived)', ['name' => $i->name]) : $i->name])->all(),
            'users' => $reports->movementUsers(),
        ]);
    }
}

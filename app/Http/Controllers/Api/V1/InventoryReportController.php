<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryLevelFilterRequest;
use App\Http\Requests\Inventory\MovementFilterRequest;
use App\Http\Resources\V1\InventoryItemResource;
use App\Http\Resources\V1\InventoryMovementResource;
use App\Services\Inventory\InventoryReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Authorization: route can:inventory.view.
class InventoryReportController extends Controller
{
    public function __construct(private InventoryReportService $reports) {}

    /** Stock value per item plus the summary, with the stock-levels page's filters. */
    public function valuation(InventoryLevelFilterRequest $request): AnonymousResourceCollection
    {
        $levels = $this->reports->levels($request->filters());

        return InventoryItemResource::collection($levels['items'])->additional(['summary' => $levels['summary']]);
    }

    /** Every movement across items, newest first, 50 a page, with the movements page's filters. */
    public function movements(MovementFilterRequest $request): AnonymousResourceCollection
    {
        return InventoryMovementResource::collection($this->reports->movements($request->filters()));
    }
}

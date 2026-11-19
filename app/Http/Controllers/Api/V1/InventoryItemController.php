<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryItemRequest;
use App\Http\Requests\Inventory\SaveInventoryUnitsRequest;
use App\Http\Requests\Inventory\StockOutRequest;
use App\Http\Resources\V1\InventoryItemResource;
use App\Http\Resources\V1\InventoryMovementResource;
use App\Models\InventoryItem;
use App\Models\StockReceiptItem;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryItemController extends Controller
{
    public function __construct(private InventoryItemService $items) {}

    public function index(): AnonymousResourceCollection
    {
        return InventoryItemResource::collection(InventoryItem::with('units')->orderBy('name')->get());
    }

    public function show(InventoryItem $item): InventoryItemResource
    {
        return new InventoryItemResource($item->load('units'));
    }

    public function store(SaveInventoryItemRequest $request): JsonResponse
    {
        return (new InventoryItemResource($this->items->create($request->itemData())->fresh()->load('units')))->response()->setStatusCode(201);
    }

    public function update(SaveInventoryItemRequest $request, InventoryItem $item): InventoryItemResource
    {
        return new InventoryItemResource($this->items->update($item, $request->itemData())->load('units'));
    }

    public function units(SaveInventoryUnitsRequest $request, InventoryItem $item): InventoryItemResource
    {
        return new InventoryItemResource($this->items->saveUnits($item, $request->units()));
    }

    public function destroy(InventoryItem $item): InventoryItemResource
    {
        $this->items->archive($item);

        return new InventoryItemResource($item->load('units'));
    }

    public function restore(InventoryItem $item): InventoryItemResource
    {
        $this->items->restore($item);

        return new InventoryItemResource($item->load('units'));
    }

    public function stockOut(StockOutRequest $request, InventoryItem $item, InventoryService $inventory): JsonResponse
    {
        $movement = $inventory->stockOut($item, $request->user(), $request->validated());

        return (new InventoryMovementResource($movement->load('createdBy')))->response()->setStatusCode(201);
    }

    public function movements(InventoryItem $item): AnonymousResourceCollection
    {
        return InventoryMovementResource::collection($item->movements()
            ->with(['createdBy', 'source' => fn (MorphTo $source) => $source->morphWith([StockReceiptItem::class => ['receipt']])])
            ->paginate(50));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockReceiptRequest;
use App\Http\Resources\V1\StockReceiptResource;
use App\Models\StockReceipt;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockReceiptController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return StockReceiptResource::collection(StockReceipt::with('supplier', 'receivedBy')->orderByDesc('received_at')->orderByDesc('id')->paginate(30));
    }

    public function show(StockReceipt $receipt): StockReceiptResource
    {
        return new StockReceiptResource($receipt->load('supplier', 'receivedBy', 'lines.item'));
    }

    public function store(StockReceiptRequest $request, InventoryService $inventory): JsonResponse
    {
        $receipt = $inventory->receive($request->user(), $request->validated());

        return (new StockReceiptResource($receipt->load('supplier', 'receivedBy', 'lines.item')))->response()->setStatusCode(201);
    }
}

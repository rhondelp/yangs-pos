<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelOrderItemRequest;
use App\Http\Requests\Orders\UpdateOrderItemRequest;
use App\Http\Resources\V1\OrderItemResource;
use App\Models\OrderItem;
use App\Services\Orders\OrderService;
use Illuminate\Http\JsonResponse;

class OrderItemController extends Controller
{
    public function update(UpdateOrderItemRequest $request, OrderItem $item, OrderService $orders): OrderItemResource
    {
        return new OrderItemResource($orders->updateItem($item, $request->user(), $request->changes())->load('voids'));
    }

    public function cancel(CancelOrderItemRequest $request, OrderItem $item, OrderService $orders): JsonResponse
    {
        $quantity = $request->validated('quantity');
        $cancelled = $orders->cancelItem($item, $request->user(), $request->validated('reason'), $quantity === null ? null : (int) $quantity);

        // A split creates the cancelled row, which JsonResource would answer with 201.
        return (new OrderItemResource($cancelled->load('voids')))->response()->setStatusCode(200);
    }
}

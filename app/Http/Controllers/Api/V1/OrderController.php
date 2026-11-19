<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\AddOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\TableSession;
use App\Services\Orders\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(AddOrderRequest $request, TableSession $session, OrderService $orders): JsonResponse
    {
        $order = $orders->addOrder($session, $request->user(), $request->lines(), $request->validated('notes'));

        return (new OrderResource($order->load('session.table', 'cashier')))->response()->setStatusCode(201);
    }
}

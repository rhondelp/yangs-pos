<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corrections\CorrectPaymentRequest;
use App\Http\Requests\Corrections\RefundRequest;
use App\Http\Requests\Corrections\VoidItemRequest;
use App\Http\Requests\Corrections\VoidOrderRequest;
use App\Http\Resources\V1\BillResource;
use App\Http\Resources\V1\OrderVoidResource;
use App\Http\Resources\V1\PaymentCorrectionResource;
use App\Http\Resources\V1\RefundResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TableSession;
use App\Services\Corrections\PaymentCorrectionService;
use App\Services\Corrections\RefundService;
use App\Services\Corrections\VoidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Voids, refunds and payment corrections. Each reply carries the new record and the tab's bill. */
class CorrectionController extends Controller
{
    private const APPROVAL = ['approver_username', 'approver_pin'];

    public function voidItem(VoidItemRequest $request, OrderItem $item, VoidService $voids): JsonResponse
    {
        $void = $voids->voidItem($item, $request->user(), (int) $request->validated('quantity'), $request->validated('reason'), $request->only(self::APPROVAL), $request->validated('idempotency_key'));

        return $this->reply($request, new OrderVoidResource($void), $void->table_session_id, 201);
    }

    public function voidOrder(VoidOrderRequest $request, Order $order, VoidService $voids): JsonResponse
    {
        $records = $voids->voidOrder($order, $request->user(), $request->validated('reason'), $request->only(self::APPROVAL), $request->validated('idempotency_key'));

        return $this->reply($request, OrderVoidResource::collection($records), $order->table_session_id, 201);
    }

    /** 201 for a new refund, 200 when the idempotency key was already used for this payment. */
    public function refund(RefundRequest $request, Payment $payment, RefundService $refunds): JsonResponse
    {
        abort_unless(TableSession::visibleTo($request->user())->whereKey($payment->table_session_id)->exists(), 404);
        $refund = $refunds->refund($payment, $request->user(), $request->validated());

        return $this->reply($request, new RefundResource($refund->load('method')), $payment->table_session_id, $refund->wasRecentlyCreated ? 201 : 200);
    }

    public function correct(CorrectPaymentRequest $request, Payment $payment, PaymentCorrectionService $corrections): JsonResponse
    {
        abort_unless(TableSession::visibleTo($request->user())->whereKey($payment->table_session_id)->exists(), 404);
        $correction = $corrections->correct($payment, $request->user(), $request->validated());

        return $this->reply($request, new PaymentCorrectionResource($correction->load('newMethod')), $payment->table_session_id, 201);
    }

    private function reply(Request $request, JsonResource $record, int $sessionId, int $status): JsonResponse
    {
        return $record->additional(['bill' => (new BillResource(TableSession::with('table')->findOrFail($sessionId)))->resolve($request)])
            ->response()
            ->setStatusCode($status);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ApplyDiscountRequest;
use App\Http\Requests\Billing\RecordPaymentRequest;
use App\Http\Resources\V1\BillResource;
use App\Http\Resources\V1\PaymentResource;
use App\Models\TableSession;
use App\Services\Billing\BillService;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(private BillService $bills, private PaymentService $payments) {}

    /** Reading a live tab's bill presents it (table: Awaiting payment), like opening the bill page. */
    public function show(Request $request, TableSession $session): BillResource
    {
        if ($session->status->isOpen()) {
            $this->bills->present($session, $request->user());
        }

        return $this->bill($session);
    }

    /** 201 for a new payment, 200 when the idempotency key was already used on this tab. */
    public function pay(RecordPaymentRequest $request, TableSession $session): JsonResponse
    {
        $payment = $this->payments->record($session, $request->user(), $request->payment());

        return $this->bill($session)
            ->additional(['payment' => (new PaymentResource($payment->load('method')))->resolve($request)])
            ->response()
            ->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }

    public function discount(ApplyDiscountRequest $request, TableSession $session): BillResource
    {
        $this->bills->applyDiscount($session, $request->user(), ...$request->safe()->only(['type', 'value', 'reason']));

        return $this->bill($session);
    }

    public function removeDiscount(Request $request, TableSession $session): BillResource
    {
        $this->bills->removeDiscount($session, $request->user());

        return $this->bill($session);
    }

    public function settle(Request $request, TableSession $session): BillResource
    {
        $this->payments->settleFreeBill($session, $request->user());

        return $this->bill($session);
    }

    private function bill(TableSession $session): BillResource
    {
        return new BillResource($session->fresh()->load('table'));
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BillResource;
use App\Http\Resources\V1\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Models\TableSession;
use App\Services\Billing\BillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The bill and payment screen. Opening it presents the bill; payments go through /api/v1. */
class BillController extends Controller
{
    public function show(Request $request, TableSession $session, BillService $bills): View|RedirectResponse
    {
        $session->load('table');
        $user = $request->user();

        if ($session->status === SessionStatus::Closed) {
            return $user->can('receipts.view')
                ? redirect()->route('receipts.show', $session)
                : redirect()->route('floor')->with('status', "Table {$session->table->label} is paid.");
        }
        if (! $session->status->isOpen()) {
            return redirect()->route('floor')->with('error', "The tab on table {$session->table->label} was cancelled.");
        }

        $bills->present($session, $user);

        return view('pos.bill', ['bootstrap' => [
            'session' => (new BillResource($session))->resolve($request),
            'methods' => PaymentMethodResource::collection(PaymentMethod::active()->get())->resolve($request),
            'urls' => array_filter([
                'bill' => route('api.v1.sessions.bill', $session),
                'payments' => route('api.v1.sessions.payments', $session),
                'settle' => route('api.v1.sessions.settle', $session),
                'discount' => $user->can('orders.discount') ? route('api.v1.sessions.discount', $session) : null,
                'pos' => $user->can('pos.create-order') ? route('pos.show', $session) : null,
                'floor' => route('floor'),
            ]),
        ]]);
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Corrections\CorrectPaymentRequest;
use App\Http\Requests\Corrections\RefundRequest;
use App\Http\Requests\Corrections\TransactionFilterRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Corrections\PaymentCorrectionService;
use App\Services\Corrections\RefundService;
use App\Services\Orders\RunningBill;
use App\Services\Settings\SettingService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Paid (closed) tabs: the list per business day, one tab's detail, and its refunds and corrections. */
class TransactionController extends Controller
{
    public function index(TransactionFilterRequest $request): View
    {
        $date = $request->businessDate();

        return view('transactions.index', [
            'date' => $date,
            'sessions' => $this->visible($request->user())
                ->whereDate('business_date', $date)
                ->with('table', 'closedBy')
                ->withSum('refunds', 'amount')
                ->orderByDesc('closed_at')
                ->get(),
        ]);
    }

    public function show(Request $request, TableSession $session, RunningBill $bill, SettingService $settings): View
    {
        abort_unless($this->visible($request->user())->whereKey($session->getKey())->exists(), 404);
        $session->load('table', 'openedBy', 'closedBy');
        $user = $request->user();

        return view('transactions.show', [
            'session' => $session,
            'bill' => $bill->calculate($session),
            'methods' => PaymentMethod::active()->get(),
            'canRefund' => $user->can('payments.refund'),
            'canCorrect' => $user->can('corrections.create'),
            'canReceipt' => $user->can('receipts.view'),
            'approval' => (bool) $settings->get('require_approval_pin'),
        ]);
    }

    public function refund(RefundRequest $request, Payment $payment, RefundService $refunds): RedirectResponse
    {
        abort_unless(TableSession::visibleTo($request->user())->whereKey($payment->table_session_id)->exists(), 404);
        $refund = $refunds->refund($payment, $request->user(), $request->validated()); // ValidationException goes back to the dialog

        return redirect()->route('transactions.show', $payment->table_session_id)->with('status', 'Refunded '.Money::format($refund->amount).'.');
    }

    public function correct(CorrectPaymentRequest $request, Payment $payment, PaymentCorrectionService $corrections): RedirectResponse
    {
        abort_unless(TableSession::visibleTo($request->user())->whereKey($payment->table_session_id)->exists(), 404);
        $correction = $corrections->correct($payment, $request->user(), $request->validated());

        return redirect()->route('transactions.show', $payment->table_session_id)->with('status', "Payment corrected to {$correction->newMethod->name}.");
    }

    /** Closed tabs this user may see. */
    private function visible(User $user): Builder
    {
        return TableSession::visibleTo($user)->where('status', SessionStatus::Closed);
    }
}

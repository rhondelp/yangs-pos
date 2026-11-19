<?php

namespace App\Http\Controllers\Web;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Corrections\CorrectPaymentRequest;
use App\Http\Requests\Corrections\RefundRequest;
use App\Http\Requests\Corrections\TransactionFilterRequest;
use App\Models\DiningTable;
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
        $f = $request->filters();
        $user = $request->user();

        return view('transactions.index', [
            'date' => $date,
            'filters' => $f,
            'tables' => DiningTable::query()->floorOrder()->pluck('label', 'id')->all(),
            'methods' => PaymentMethod::orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            // Filtering by cashier only means something to users who see everyone's tabs.
            'cashiers' => $user->can('orders.view-all') ? User::orderBy('name')->pluck('name', 'id')->all() : null,
            'sessions' => $this->visible($user)
                ->whereDate('business_date', $date)
                ->when($f['table_id'], fn (Builder $q, int $id) => $q->where('dining_table_id', $id))
                ->when($f['cashier_id'], fn (Builder $q, int $id) => $q->where('closed_by', $id))
                // Every order on a closed tab carries the tab's payment status.
                ->when($f['payment_status'], fn (Builder $q, string $s) => $q->whereHas('orders', fn (Builder $o) => $o->where('payment_status', $s)))
                // The payment's latest correction wins over the method it was recorded with.
                ->when($f['method_id'], fn (Builder $q, int $id) => $q->whereHas('payments', fn (Builder $p) => $p->whereRaw(
                    'COALESCE((SELECT pc.new_payment_method_id FROM payment_corrections pc WHERE pc.payment_id = payments.id ORDER BY pc.id DESC LIMIT 1), payments.payment_method_id) = ?',
                    [$id],
                )))
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

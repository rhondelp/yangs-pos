<?php

namespace App\Services\Corrections;

use App\Enums\PaymentStatus;
use App\Enums\SessionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\BusinessDate;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Money handed back on a closed tab, against one payment and never more than is left of it.
 * Runs with the tab row locked (the same lock PaymentService takes), and a repeated idempotency key
 * returns the refund already made, like payments.
 */
class RefundService
{
    private const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

    public function __construct(private ActivityLogger $logger, private ApprovalService $approvals) {}

    /**
     * @param  array{amount: mixed, reason: mixed, idempotency_key: mixed, method_id?: mixed, reference_no?: ?string, approver_username?: mixed, approver_pin?: mixed}  $input
     *                                                                                                                                                                         method_id defaults to the payment's (corrected) method.
     *
     * @throws AuthorizationException without payments.refund
     * @throws ValidationException when the tab is not closed, or the amount, method, reference, reason or approval is invalid
     */
    public function refund(Payment $payment, User $by, array $input): Refund
    {
        if (! $by->can('payments.refund')) {
            throw new AuthorizationException('You cannot refund payments.');
        }

        $key = $input['idempotency_key'] ?? null;
        if (! is_string($key) || ! Str::isUuid($key)) {
            $this->reject('idempotency_key', 'This refund is missing its request key. Reload the page and try again.');
        }
        if ($earlier = $this->replay($key, $payment)) {
            return $earlier;
        }

        $amount = (string) ($input['amount'] ?? '');
        if (! preg_match(self::MONEY, $amount) || bccomp($amount, '0', 2) <= 0) {
            $this->reject('amount', 'Enter an amount above ₱0.00 with up to two decimals.');
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            $this->reject('reason', 'Give a reason for the refund.');
        }
        if (mb_strlen($reason) > 200) {
            $this->reject('reason', 'The reason may not be longer than 200 characters.');
        }

        $methodId = $input['method_id'] ?? $payment->load('method', 'corrections.newMethod')->effective()[0]->id; // a corrected payment refunds by its corrected method
        $method = is_numeric($methodId) ? PaymentMethod::whereKey((int) $methodId)->where('is_active', true)->first() : null;
        if ($method === null) {
            $this->reject('method_id', 'Choose an active payment method.');
        }
        $reference = $method->is_cash ? null : (trim((string) ($input['reference_no'] ?? '')) ?: null);
        if ($reference === null && $method->requires_reference) {
            $this->reject('reference_no', "Enter the {$method->name} reference number.");
        }
        if ($reference !== null && mb_strlen($reference) > 60) {
            $this->reject('reference_no', 'The reference number may not be longer than 60 characters.');
        }

        $approver = $this->approvals->approve($input, 'refund');

        try {
            return DB::transaction(function () use ($payment, $by, $key, $amount, $reason, $method, $reference, $approver) {
                $session = TableSession::whereKey($payment->table_session_id)->lockForUpdate()->firstOrFail();
                if ($earlier = $this->replay($key, $payment)) {
                    return $earlier;
                }
                if ($session->status !== SessionStatus::Closed) {
                    $this->reject('session', 'Only a paid, closed tab can be refunded. Void items while the tab is open.');
                }

                $left = Money::sub($payment->amount, Refund::where('payment_id', $payment->id)->sum('amount'));
                if (bccomp($left, '0', 2) <= 0) {
                    $this->reject('amount', 'This payment is already fully refunded.');
                }
                if (bccomp($amount, $left, 2) > 0) {
                    $this->reject('amount', 'The most this payment can refund is '.Money::format($left).'.');
                }

                $refund = Refund::create([
                    'table_session_id' => $session->id,
                    'payment_id' => $payment->id,
                    'payment_method_id' => $method->id,
                    'amount' => Money::round($amount),
                    'reference_no' => $reference,
                    'reason' => $reason,
                    'idempotency_key' => $key,
                    'refunded_by' => $by->id,
                    'approved_by' => $approver?->id,
                    'business_date' => BusinessDate::for(now()),
                ]);

                $total = Money::round((string) Refund::where('table_session_id', $session->id)->sum('amount'));
                $status = bccomp($total, $session->net_total, 2) >= 0 ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
                // Derived from the refunds ledger, which is the audited record; no per-order log.
                Order::where('table_session_id', $session->id)->update(['payment_status' => $status->value, 'updated_at' => now()]);

                $this->logger->log('payment.refunded', $refund, [], array_filter([
                    'payment' => $payment->id,
                    'method' => $method->name,
                    'amount' => $refund->amount,
                    'reference_no' => $reference,
                    'reason' => $reason,
                ], fn ($v) => $v !== null), $by, $approver);

                return $refund;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $this->replay($key, $payment) ?? throw $e; // two identical requests raced; the database kept the first
        }
    }

    private function replay(string $key, Payment $payment): ?Refund
    {
        $refund = Refund::where('idempotency_key', $key)->first();
        if ($refund !== null && $refund->payment_id !== $payment->getKey()) {
            $this->reject('idempotency_key', 'This request key was already used for another refund. Reload the page and try again.');
        }

        return $refund;
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

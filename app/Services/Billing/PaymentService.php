<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Orders\Concerns\LocksLiveSession;
use App\Services\Orders\RunningBill;
use App\Services\Settings\SettingService;
use App\Services\Support\ReferenceGenerator;
use App\Services\Tables\TableStatusService;
use App\Support\BusinessDate;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records payments against a tab and closes it once they cover the total (CLAUDE.md "Payments").
 * Every payment runs in one transaction holding the tab's row lock, so two tablets can never
 * pay the same balance twice; a repeated idempotency key returns the payment already made.
 */
class PaymentService
{
    use LocksLiveSession;

    private const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

    public function __construct(
        private ActivityLogger $logger,
        private RunningBill $bill,
        private TableStatusService $tables,
        private ReferenceGenerator $references,
        private SettingService $settings,
    ) {}

    /**
     * @param  array{method_id: mixed, idempotency_key: mixed, amount?: ?string, tendered?: ?string, reference_no?: ?string}  $input
     *                                                                                                                                amount defaults to the balance; cash tendered defaults to the amount.
     *
     * @throws AuthorizationException without payments.record
     * @throws ValidationException when the tab is closed, the method or an amount is invalid, or the amount exceeds the balance
     */
    public function record(TableSession $session, User $by, array $input): Payment
    {
        if (! $by->can('payments.record')) {
            throw new AuthorizationException('You cannot take payments.');
        }

        $key = $input['idempotency_key'] ?? null;
        if (! is_string($key) || ! Str::isUuid($key)) {
            $this->reject('idempotency_key', 'This payment is missing its request key. Reload the page and try again.');
        }
        if ($earlier = $this->replay($key, $session)) {
            return $earlier;
        }

        $method = is_numeric($input['method_id'] ?? null)
            ? PaymentMethod::whereKey((int) $input['method_id'])->where('is_active', true)->first()
            : null;
        if ($method === null) {
            $this->reject('method_id', 'Choose an active payment method.');
        }

        $amount = $this->money($input['amount'] ?? null, 'amount', 'Enter an amount above ₱0.00 with up to two decimals.');
        $tendered = $method->is_cash ? $this->money($input['tendered'] ?? null, 'tendered', 'Enter the cash received with up to two decimals.') : null;
        $reference = $method->is_cash ? null : (trim((string) ($input['reference_no'] ?? '')) ?: null);
        if ($reference === null && $method->requires_reference) {
            $this->reject('reference_no', "Enter the {$method->name} reference number.");
        }
        if ($reference !== null && mb_strlen($reference) > 60) {
            $this->reject('reference_no', 'The reference number may not be longer than 60 characters.');
        }

        try {
            return DB::transaction(function () use ($session, $by, $key, $method, $amount, $tendered, $reference) {
                $locked = $this->lockSession($session->getKey());
                if ($earlier = $this->replay($key, $session)) {
                    return $earlier; // the same request finished while this one waited for the lock (and may have closed the tab)
                }
                $this->ensureLive($locked);

                $bill = $this->bill->calculate($locked);
                if (bccomp($bill['balance'], '0', 2) <= 0) {
                    $this->reject('session', 'Nothing is left to pay on this tab.');
                }
                $amount ??= $bill['balance'];
                if (bccomp($amount, $bill['balance'], 2) > 0) {
                    $this->reject('amount', 'The most this tab can take is '.Money::format($bill['balance']).'.');
                }

                $change = '0.00';
                if ($method->is_cash) {
                    $tendered ??= $amount;
                    if (bccomp($tendered, $amount, 2) < 0) {
                        $this->reject('tendered', 'Cash received must be at least '.Money::format($amount).'.');
                    }
                    $change = Money::sub($tendered, $amount);
                    // No note is ₱1,000 over the bill: more change is a typo, and payments can never be edited.
                    if (bccomp($change, '1000', 2) >= 0) {
                        $this->reject('tendered', 'Change of '.Money::format($change).' is too much. Check the cash received.');
                    }
                }

                $payment = Payment::create([
                    'table_session_id' => $locked->id,
                    'payment_method_id' => $method->id,
                    'amount' => $amount,
                    'tendered' => $tendered,
                    'change_given' => $change,
                    'reference_no' => $reference,
                    'idempotency_key' => $key,
                    'received_by' => $by->id,
                    'paid_at' => now(),
                    'business_date' => BusinessDate::for(now()),
                ]);
                $this->logger->log('payment.recorded', $payment, [], array_filter([
                    'session' => $locked->id,
                    'method' => $method->name,
                    'amount' => $payment->amount,
                    'tendered' => $payment->tendered,
                    'change_given' => $payment->change_given,
                    'reference_no' => $reference,
                ], fn ($v) => $v !== null), $by);

                $paid = Money::add($bill['paid'], $payment->amount);
                if (bccomp($paid, $bill['total'], 2) >= 0) {
                    $this->closeTab($locked, $by, $bill, $paid);
                } else {
                    $this->markOrders($locked, PaymentStatus::Partial);
                }
                $session->setRawAttributes($locked->getAttributes(), true);

                return $payment;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two identical requests raced past both checks; the database kept the first.
            return $this->replay($key, $session) ?? throw $e;
        }
    }

    /**
     * Closes a tab whose bill was discounted to ₱0.00; there is nothing to pay.
     *
     * @throws AuthorizationException|ValidationException
     */
    public function settleFreeBill(TableSession $session, User $by): TableSession
    {
        if (! $by->can('payments.record')) {
            throw new AuthorizationException('You cannot take payments.');
        }

        return DB::transaction(function () use ($session, $by) {
            $locked = $this->lockLiveSession($session->getKey());
            $bill = $this->bill->calculate($locked);

            if (bccomp($bill['balance'], '0', 2) > 0) {
                $this->reject('session', 'This bill still has '.Money::format($bill['balance']).' to pay.');
            }
            if ($bill['item_count'] === 0) {
                $this->reject('session', 'This tab has no items. Cancel the tab instead.');
            }

            $this->closeTab($locked, $by, $bill, $bill['paid']);
            $session->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    /** Freezes the totals, numbers the receipt, marks the orders paid and frees the table. Caller holds the session lock. */
    public function closeTab(TableSession $locked, User $by, array $bill, string $paid): void
    {
        $was = $locked->status->value;
        $locked->forceFill([
            'status' => SessionStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $by->id,
            'business_date' => BusinessDate::for(now()), // a tab is a sale of the day it closes (ARCHITECTURE §3.7)
            'gross_total' => $bill['gross'],
            'discount_total' => Money::add($bill['discounts'], $bill['bill_discount']),
            'net_total' => $bill['total'],
            'receipt_number' => $this->references->next('RCP', BusinessDate::for(now())),
        ])->save();
        $this->markOrders($locked, PaymentStatus::Paid);

        $table = $this->settings->get('clean_table_after_payment') ? TableStatus::Cleaning : TableStatus::Available;
        $this->tables->change($locked->table, $table, system: true, actor: $by);

        $this->logger->log('session.closed', $locked, ['status' => $was], [
            'status' => 'closed',
            'receipt_number' => $locked->receipt_number,
            'total' => $bill['total'],
            'paid' => $paid,
            'table_status' => $table->value,
        ], $by);
    }

    private function markOrders(TableSession $session, PaymentStatus $status): void
    {
        // Derived from the payments ledger, which is the audited record; no per-order log.
        Order::where('table_session_id', $session->id)->update(['payment_status' => $status->value, 'updated_at' => now()]);
    }

    private function replay(string $key, TableSession $session): ?Payment
    {
        $payment = Payment::where('idempotency_key', $key)->first();
        if ($payment !== null && $payment->table_session_id !== $session->getKey()) {
            $this->reject('idempotency_key', 'This request key was already used for another tab. Reload the page and try again.');
        }

        return $payment;
    }

    /** A positive 2-decimal string, or null when left blank. */
    private function money(mixed $value, string $key, string $message): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        if (! preg_match(self::MONEY, $value) || bccomp($value, '0', 2) <= 0) {
            $this->reject($key, $message);
        }

        return Money::round($value);
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

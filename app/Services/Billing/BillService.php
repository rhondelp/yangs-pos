<?php

namespace App\Services\Billing;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Orders\Concerns\LocksLiveSession;
use App\Services\Orders\RunningBill;
use App\Services\Tables\TableStatusService;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Presents the bill (table: Awaiting payment) and sets or removes the bill-level discount. */
class BillService
{
    use LocksLiveSession;

    public function __construct(private ActivityLogger $logger, private TableStatusService $tables, private RunningBill $bill) {}

    /**
     * Marks the bill presented. Doing it again is a no-op; a new order reopens the tab (OrderService).
     *
     * @throws AuthorizationException without payments.record
     * @throws ValidationException when the tab is closed
     */
    public function present(TableSession $session, User $by): TableSession
    {
        $this->authorize($by, 'payments.record', 'You cannot take payments.');

        return DB::transaction(function () use ($session, $by) {
            $locked = $this->lockLiveSession($session->getKey());

            if ($locked->status === SessionStatus::Open) {
                $locked->forceFill(['status' => SessionStatus::Billing, 'billed_at' => now()])->save();
                $this->tables->change($locked->table, TableStatus::AwaitingPayment, system: true, actor: $by);
                $this->logger->log('session.billed', $locked, ['status' => 'open'], ['status' => 'billing', 'total' => $this->bill->calculate($locked)['total']], $by);
            }
            $session->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    /**
     * @param  string  $type  amount | percent
     *
     * @throws AuthorizationException without orders.discount
     * @throws ValidationException when the tab is closed, a value is out of range, or the bill would drop below what is paid
     */
    public function applyDiscount(TableSession $session, User $by, string $type, string $value, string $reason): TableSession
    {
        $this->authorize($by, 'orders.discount', 'You cannot give discounts.');
        if (! in_array($type, ['amount', 'percent'], true)) {
            $this->reject('type', 'Choose an amount or a percent.');
        }
        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
            $this->reject('value', 'Enter the discount as a number with up to two decimals.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            $this->reject('reason', 'Give a reason for the discount.');
        }
        if (mb_strlen($reason) > 200) {
            $this->reject('reason', 'The reason may not be longer than 200 characters.');
        }

        return DB::transaction(function () use ($session, $by, $type, $value, $reason) {
            $locked = $this->lockLiveSession($session->getKey());
            $bill = $this->bill->calculate($locked);

            if ($type === 'percent' && (bccomp($value, '0', 2) <= 0 || bccomp($value, '100', 2) > 0)) {
                $this->reject('value', 'The discount must be between 0.01% and 100%.');
            }
            if ($type === 'amount' && (bccomp($value, '0', 2) <= 0 || bccomp($value, $bill['subtotal'], 2) > 0)) {
                $this->reject('value', 'The discount must be between ₱0.01 and '.Money::format($bill['subtotal']).'.');
            }
            $total = Money::sub($bill['subtotal'], RunningBill::billDiscount($bill['subtotal'], $type, $value));
            // Once money is in, the total must stay above it: equal would leave a paid-up tab that never closes.
            if (bccomp($bill['paid'], '0', 2) > 0 && bccomp($total, $bill['paid'], 2) <= 0) {
                $this->reject('value', 'The bill must stay above the '.Money::format($bill['paid']).' already paid.');
            }

            $old = $this->discountOf($locked);
            $locked->forceFill([
                'bill_discount_type' => $type,
                'bill_discount_value' => Money::round($value),
                'bill_discount_reason' => $reason,
                'bill_discount_by' => $by->id,
            ])->save();
            $this->logger->log('session.discounted', $locked, $old, $this->discountOf($locked), $by);
            $session->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    /** @throws AuthorizationException|ValidationException */
    public function removeDiscount(TableSession $session, User $by): TableSession
    {
        $this->authorize($by, 'orders.discount', 'You cannot give discounts.');

        return DB::transaction(function () use ($session, $by) {
            $locked = $this->lockLiveSession($session->getKey());
            if ($locked->bill_discount_type === null) {
                $this->reject('session', 'This bill has no discount.');
            }
            // Removing it would raise what the customer owes after paying part of the old total.
            if ($locked->payments()->exists()) {
                $this->reject('session', 'Payment was taken on this tab. The discount can no longer be removed.');
            }

            $old = $this->discountOf($locked);
            $locked->forceFill(['bill_discount_type' => null, 'bill_discount_value' => null, 'bill_discount_reason' => null, 'bill_discount_by' => null])->save();
            $this->logger->log('session.discount_removed', $locked, $old, [], $by);
            $session->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    private function discountOf(TableSession $session): array
    {
        return $session->bill_discount_type === null ? [] : [
            'type' => $session->bill_discount_type,
            'value' => $session->bill_discount_value,
            'reason' => $session->bill_discount_reason,
        ];
    }

    private function authorize(User $by, string $permission, string $message): void
    {
        if (! $by->can($permission)) {
            throw new AuthorizationException($message);
        }
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

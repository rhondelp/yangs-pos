<?php

namespace App\Services\Corrections;

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderVoid;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Billing\PaymentService;
use App\Services\Orders\Concerns\LocksLiveSession;
use App\Services\Orders\RunningBill;
use App\Services\Orders\StockHook;
use App\Support\BusinessDate;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voids units on a live tab that already took money (before that, items are cancelled).
 * A void is a new order_voids row; the order item is never changed. If the voids bring the bill
 * down to exactly what was paid, the tab closes in the same transaction.
 */
class VoidService
{
    use LocksLiveSession;

    public function __construct(
        private ActivityLogger $logger,
        private RunningBill $bill,
        private StockHook $stock,
        private PaymentService $payments,
        private ApprovalService $approvals,
    ) {}

    /**
     * @param  array{approver_username?: mixed, approver_pin?: mixed}  $approval
     * @param  ?string  $key  idempotency key: a resent request returns the void already made
     *
     * @throws AuthorizationException without orders.void
     * @throws ValidationException when the tab is unpaid or closed, or the reason, quantity or approval is invalid
     */
    public function voidItem(OrderItem $item, User $by, int $quantity, string $reason, array $approval = [], ?string $key = null): OrderVoid
    {
        if ($key !== null && ($earlier = OrderVoid::where('idempotency_key', $key)->where('order_item_id', $item->getKey())->first())) {
            return $earlier;
        }
        $reason = $this->check($by, $reason);
        $approver = $this->approvals->approve($approval, 'void');

        return DB::transaction(function () use ($item, $by, $quantity, $reason, $approver, $key) {
            $session = $this->lockLiveSession(Order::whereKey($item->order_id)->value('table_session_id'));
            if ($key !== null && ($earlier = OrderVoid::where('idempotency_key', $key)->where('order_item_id', $item->getKey())->first())) {
                return $earlier; // the same request finished while this one waited for the lock
            }
            $this->refuseUnpaid($session);
            $locked = OrderItem::whereKey($item->getKey())->lockForUpdate()->with('voids')->firstOrFail();
            if ($locked->status === OrderItemStatus::Cancelled) {
                $this->reject('item', 'This item is cancelled.');
            }

            $left = $locked->quantity - (int) $locked->voids->sum('quantity');
            if ($quantity < 1 || $quantity > $left) {
                $this->reject('quantity', "You can void between 1 and {$left}.");
            }

            $void = $this->record($session, $locked, $quantity, $reason, $by, $approver, $key);
            $total = $this->settle($session, $by);
            $this->logger->log('order_item.voided', $locked, ['quantity_left' => $left],
                ['quantity' => $quantity, 'amount' => $void->amount, 'reason' => $reason, 'total' => $total], $by, $approver);

            return $void;
        });
    }

    /**
     * Voids every unit still on the order.
     *
     * @return Collection<int, OrderVoid>
     *
     * @throws AuthorizationException|ValidationException
     */
    public function voidOrder(Order $order, User $by, string $reason, array $approval = [], ?string $key = null): Collection
    {
        if ($key !== null && ($earlier = $this->replayOrder($order, $key))->isNotEmpty()) {
            return $earlier;
        }
        $reason = $this->check($by, $reason);
        $approver = $this->approvals->approve($approval, 'void');

        return DB::transaction(function () use ($order, $by, $reason, $approver, $key) {
            $session = $this->lockLiveSession($order->table_session_id);
            if ($key !== null && ($earlier = $this->replayOrder($order, $key))->isNotEmpty()) {
                return $earlier;
            }
            $this->refuseUnpaid($session);
            $items = OrderItem::where('order_id', $order->getKey())->where('status', OrderItemStatus::Active)
                ->orderBy('id')->lockForUpdate()->with('voids')->get();

            $voids = $items
                ->map(fn (OrderItem $item) => [$item, $item->quantity - (int) $item->voids->sum('quantity')])
                ->filter(fn (array $pair) => $pair[1] > 0)
                ->map(fn (array $pair) => $this->record($session, $pair[0], $pair[1], $reason, $by, $approver, $key))
                ->values();
            if ($voids->isEmpty()) {
                $this->reject('order', 'Nothing is left to void on this order.');
            }

            $total = $this->settle($session, $by);
            $this->logger->log('order.voided', $order, [], [
                'items' => $voids->count(),
                'amount' => $voids->reduce(fn (string $sum, OrderVoid $v) => Money::add($sum, $v->amount), '0.00'),
                'reason' => $reason,
                'total' => $total,
            ], $by, $approver);

            return $voids;
        });
    }

    private function record(TableSession $session, OrderItem $item, int $quantity, string $reason, User $by, ?User $approver, ?string $key): OrderVoid
    {
        $already = (int) $item->voids->sum('quantity');
        $void = OrderVoid::create([
            'table_session_id' => $session->id,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'quantity' => $quantity,
            'amount' => Money::sub(RunningBill::lineTotal($item, $already), RunningBill::lineTotal($item, $already + $quantity)),
            'reason' => $reason,
            'voided_by' => $by->id,
            'approved_by' => $approver?->id,
            'business_date' => BusinessDate::for(now()),
            'idempotency_key' => $key,
        ]);
        $this->stock->itemVoided($item, $quantity);

        return $void;
    }

    /** Refuses a bill below what was paid; closes the tab when it lands exactly on it. Returns the new total. */
    private function settle(TableSession $session, User $by): string
    {
        $bill = $this->bill->calculate($session);
        if (bccomp($bill['total'], $bill['paid'], 2) < 0) {
            $this->reject('quantity', 'This void would take the bill below the '.Money::format($bill['paid']).' already paid.');
        }
        if (bccomp($bill['balance'], '0', 2) === 0) {
            $this->payments->closeTab($session, $by, $bill, $bill['paid']);
        }

        return $bill['total'];
    }

    private function refuseUnpaid(TableSession $session): void
    {
        if (! $session->payments()->exists()) {
            $this->reject('session', 'No payment yet: cancel the item instead.');
        }
    }

    /** @return Collection<int, OrderVoid> */
    private function replayOrder(Order $order, string $key): Collection
    {
        return OrderVoid::where('idempotency_key', $key)->where('order_id', $order->getKey())->orderBy('id')->get();
    }

    private function check(User $by, string $reason): string
    {
        if (! $by->can('orders.void')) {
            throw new AuthorizationException('You cannot void items.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            $this->reject('reason', 'Give a reason for the void.');
        }
        if (mb_strlen($reason) > 200) {
            $this->reject('reason', 'The reason may not be longer than 200 characters.');
        }

        return $reason;
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

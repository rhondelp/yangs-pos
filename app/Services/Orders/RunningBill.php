<?php

namespace App\Services\Orders;

use App\Enums\OrderItemStatus;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Support\Money;

/** What the tab owes so far. Cancelled items are listed but never counted; voided units are taken off their line. */
class RunningBill
{
    /**
     * gross − line discounts = subtotal; subtotal − bill discount = total; total − paid = balance (never below 0).
     * gross, discounts and item_count are net of voids; `voided` is what the voids took off. `refunded` is money handed back after close.
     *
     * @return array{gross: string, discounts: string, subtotal: string, bill_discount: string, voided: string,
     *               bill_discount_type: ?string, bill_discount_value: ?string, bill_discount_reason: ?string,
     *               total: string, paid: string, balance: string, item_count: int,
     *               orders: list<array{id: int, reference: string, created_at: string, total: string}>,
     *               items: list<array{id: int, order_id: int, name: string, quantity: int, voided_quantity: int, unit_price: string, discount: string, line_total: string}>,
     *               voids: list<array{id: int, product_name: string, quantity: int, amount: string, reason: string}>,
     *               cancelled: list<array{id: int, product_name: string, quantity: int, cancel_reason: ?string}>,
     *               payments: list<array{id: int, method: string, amount: string, tendered: ?string, change_given: string, reference_no: ?string, paid_at: string}>}
     */
    public function calculate(TableSession $session): array
    {
        $gross = $discounts = $voided = '0.00';
        $count = 0;
        $orders = $cancelled = $items = $voids = [];

        foreach ($session->orders()->with('items.voids')->get() as $order) {
            $orderTotal = '0.00';

            foreach ($order->items as $item) {
                if ($item->status === OrderItemStatus::Cancelled) {
                    $cancelled[] = ['id' => $item->id, 'product_name' => $item->product_name, 'quantity' => $item->quantity, 'cancel_reason' => $item->cancel_reason];

                    continue;
                }

                $v = (int) $item->voids->sum('quantity');
                $left = $item->quantity - $v;
                $discount = self::lineDiscount($item, $v);
                $lineGross = Money::mul($item->unit_price, $left);
                $lineTotal = Money::sub($lineGross, $discount);

                $gross = Money::add($gross, $lineGross);
                $discounts = Money::add($discounts, $discount);
                $voided = Money::add($voided, Money::sub($item->line_total, $lineTotal));
                $orderTotal = Money::add($orderTotal, $lineTotal);
                $count += $left;

                foreach ($item->voids as $void) {
                    $voids[] = ['id' => $void->id, 'product_name' => $item->product_name, 'quantity' => $void->quantity, 'amount' => $void->amount, 'reason' => $void->reason];
                }
                if ($left > 0) {
                    $items[] = [
                        'id' => $item->id,
                        'order_id' => $order->id,
                        'name' => $item->variant_name ? "{$item->product_name}, {$item->variant_name}" : $item->product_name,
                        'quantity' => $left,
                        'voided_quantity' => $v,
                        'unit_price' => $item->unit_price,
                        'discount' => $discount,
                        'line_total' => $lineTotal,
                    ];
                }
            }

            $orders[] = ['id' => $order->id, 'reference' => $order->reference, 'created_at' => $order->created_at->toIso8601String(), 'total' => $orderTotal];
        }

        $subtotal = Money::sub($gross, $discounts);
        $billDiscount = self::billDiscount($subtotal, $session->bill_discount_type, $session->bill_discount_value);
        $total = Money::sub($subtotal, $billDiscount);

        $paid = $refunded = '0.00';
        $payments = [];
        foreach ($session->payments()->with('method', 'refunds.method', 'corrections.newMethod')->get() as $payment) {
            [$method, $reference] = $payment->effective();
            $paid = Money::add($paid, $payment->amount);
            $paymentRefunded = $payment->refunds->reduce(fn (string $sum, $r) => Money::add($sum, $r->amount), '0.00');
            $refunded = Money::add($refunded, $paymentRefunded);
            $payments[] = [
                'id' => $payment->id,
                'method_id' => $method->id,
                'method' => $method->name,
                'amount' => $payment->amount,
                'tendered' => $payment->tendered,
                'change_given' => $payment->change_given,
                'reference_no' => $reference,
                'corrected' => $payment->corrections->isNotEmpty(),
                'paid_at' => $payment->paid_at->toIso8601String(),
                'refunded' => $paymentRefunded,
                'refunds' => $payment->refunds->map(fn ($r) => [
                    'id' => $r->id,
                    'method' => $r->method->name,
                    'amount' => $r->amount,
                    'reference_no' => $r->reference_no,
                    'reason' => $r->reason,
                    'refunded_at' => $r->created_at->toIso8601String(),
                ])->all(),
            ];
        }
        $balance = Money::sub($total, $paid);

        return [
            'gross' => $gross,
            'discounts' => $discounts,
            'subtotal' => $subtotal,
            'bill_discount' => $billDiscount,
            'voided' => $voided,
            'bill_discount_type' => $session->bill_discount_type,
            'bill_discount_value' => $session->bill_discount_value,
            'bill_discount_reason' => $session->bill_discount_reason,
            'total' => $total,
            'paid' => $paid,
            'refunded' => $refunded,
            'balance' => bccomp($balance, '0', 2) > 0 ? $balance : '0.00',
            'item_count' => $count,
            'orders' => $orders,
            'items' => $items,
            'voids' => $voids,
            'cancelled' => $cancelled,
            'payments' => $payments,
        ];
    }

    /** What is left of a line's total once $voided of its units are voided. */
    public static function lineTotal(OrderItem $item, int $voided): string
    {
        return Money::sub(Money::mul($item->unit_price, $item->quantity - $voided), self::lineDiscount($item, $voided));
    }

    /** The line discount still applying: voided units take their pro-rata share with them. Computed from the total voided, so part voids never drift. */
    private static function lineDiscount(OrderItem $item, int $voided): string
    {
        return $voided === 0 ? $item->discount : Money::sub($item->discount, Money::prorate($item->discount, $voided, $item->quantity));
    }

    /** A percent of the subtotal (half-up), or a fixed amount capped at the subtotal. */
    public static function billDiscount(string $subtotal, ?string $type, ?string $value): string
    {
        return match ($type) {
            'percent' => Money::percentage($subtotal, $value),
            'amount' => bccomp($value, $subtotal, 2) > 0 ? $subtotal : Money::round($value),
            default => '0.00',
        };
    }
}

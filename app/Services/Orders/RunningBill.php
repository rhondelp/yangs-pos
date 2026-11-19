<?php

namespace App\Services\Orders;

use App\Enums\OrderItemStatus;
use App\Models\TableSession;
use App\Support\Money;

/** What the tab owes so far. Cancelled items are listed but never counted. */
class RunningBill
{
    /**
     * gross − line discounts = subtotal; subtotal − bill discount = total; total − paid = balance (never below 0).
     *
     * @return array{gross: string, discounts: string, subtotal: string, bill_discount: string,
     *               bill_discount_type: ?string, bill_discount_value: ?string, bill_discount_reason: ?string,
     *               total: string, paid: string, balance: string, item_count: int,
     *               orders: list<array{id: int, reference: string, created_at: string, total: string}>,
     *               cancelled: list<array{id: int, product_name: string, quantity: int, cancel_reason: ?string}>,
     *               payments: list<array{id: int, method: string, amount: string, tendered: ?string, change_given: string, reference_no: ?string, paid_at: string}>}
     */
    public function calculate(TableSession $session): array
    {
        $gross = $discounts = '0.00';
        $count = 0;
        $orders = $cancelled = [];

        foreach ($session->orders()->with('items')->get() as $order) {
            $orderTotal = '0.00';

            foreach ($order->items as $item) {
                if ($item->status === OrderItemStatus::Cancelled) {
                    $cancelled[] = ['id' => $item->id, 'product_name' => $item->product_name, 'quantity' => $item->quantity, 'cancel_reason' => $item->cancel_reason];

                    continue;
                }

                $gross = Money::add($gross, Money::mul($item->unit_price, $item->quantity));
                $discounts = Money::add($discounts, $item->discount);
                $orderTotal = Money::add($orderTotal, $item->line_total);
                $count += $item->quantity;
            }

            $orders[] = ['id' => $order->id, 'reference' => $order->reference, 'created_at' => $order->created_at->toIso8601String(), 'total' => $orderTotal];
        }

        $subtotal = Money::sub($gross, $discounts);
        $billDiscount = self::billDiscount($subtotal, $session->bill_discount_type, $session->bill_discount_value);
        $total = Money::sub($subtotal, $billDiscount);

        $paid = '0.00';
        $payments = [];
        foreach ($session->payments()->with('method')->get() as $payment) {
            $paid = Money::add($paid, $payment->amount);
            $payments[] = [
                'id' => $payment->id,
                'method' => $payment->method->name,
                'amount' => $payment->amount,
                'tendered' => $payment->tendered,
                'change_given' => $payment->change_given,
                'reference_no' => $payment->reference_no,
                'paid_at' => $payment->paid_at->toIso8601String(),
            ];
        }
        $balance = Money::sub($total, $paid);

        return [
            'gross' => $gross,
            'discounts' => $discounts,
            'subtotal' => $subtotal,
            'bill_discount' => $billDiscount,
            'bill_discount_type' => $session->bill_discount_type,
            'bill_discount_value' => $session->bill_discount_value,
            'bill_discount_reason' => $session->bill_discount_reason,
            'total' => $total,
            'paid' => $paid,
            'balance' => bccomp($balance, '0', 2) > 0 ? $balance : '0.00',
            'item_count' => $count,
            'orders' => $orders,
            'cancelled' => $cancelled,
            'payments' => $payments,
        ];
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

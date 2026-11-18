<?php

namespace App\Services\Orders;

use App\Enums\OrderItemStatus;
use App\Models\TableSession;
use App\Support\Money;

/** What the tab owes so far. Cancelled items are listed but never counted. */
class RunningBill
{
    /**
     * @return array{gross: string, discounts: string, total: string, item_count: int,
     *               orders: list<array{id: int, reference: string, created_at: string, total: string}>,
     *               cancelled: list<array{id: int, product_name: string, quantity: int, cancel_reason: ?string}>}
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

        return [
            'gross' => $gross,
            'discounts' => $discounts,
            'total' => Money::sub($gross, $discounts),
            'item_count' => $count,
            'orders' => $orders,
            'cancelled' => $cancelled,
        ];
    }
}

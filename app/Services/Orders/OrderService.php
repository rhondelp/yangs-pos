<?php

namespace App\Services\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Orders\Concerns\LocksLiveSession;
use App\Services\Support\ReferenceGenerator;
use App\Services\Tables\TableStatusService;
use App\Support\BusinessDate;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orders on an open tab. Every write locks the session row first, then the item it changes,
 * so concurrent edits to one tab run one at a time. Payment status is never written here.
 */
class OrderService
{
    use LocksLiveSession;

    private const MAX_QUANTITY = 99;

    public function __construct(
        private ActivityLogger $logger,
        private ReferenceGenerator $references,
        private StockHook $stock,
        private TableStatusService $tables,
    ) {}

    /**
     * @param  list<array{product_id: int, variant_id?: ?int, quantity: int, notes?: ?string}>  $lines
     *
     * @throws AuthorizationException without pos.create-order
     * @throws ValidationException when the tab is closed or a line cannot be sold
     */
    public function addOrder(TableSession $session, User $by, array $lines, ?string $notes = null): Order
    {
        $this->authorize($by, 'pos.create-order', 'You cannot take orders.');
        if ($lines === []) {
            $this->reject('lines', 'Add at least one item.');
        }

        return DB::transaction(function () use ($session, $by, $lines, $notes) {
            $locked = $this->lockLiveSession($session->getKey());
            $items = $this->snapshotLines(array_values($lines));

            if ($locked->status === SessionStatus::Billing) {
                // A new order after the bill was shown: the tab is running again.
                $locked->forceFill(['status' => SessionStatus::Open, 'billed_at' => null])->save();
                $this->tables->change($locked->table, TableStatus::Occupied, system: true, actor: $by);
                $this->logger->log('session.reopened', $locked, ['status' => 'billing'], ['status' => 'open'], $by);
                $session->setRawAttributes($locked->getAttributes(), true);
            }

            // Inside the transaction, so a rollback also rolls back the counter: numbering stays gap-free.
            $businessDate = BusinessDate::for(now());
            $order = Order::create([
                'reference' => $this->references->next('ORD', $businessDate),
                'table_session_id' => $session->getKey(),
                'created_by' => $by->id,
                'business_date' => $businessDate,
                'notes' => $notes,
            ]);
            $order->items()->createMany($items);
            $order->load('items');

            $this->stock->deductForOrder($order);
            $this->logger->log('order.created', $order, [], [
                'reference' => $order->reference,
                'items' => count($items),
                'total' => array_reduce($items, fn (string $sum, array $i) => Money::add($sum, $i['line_total']), '0.00'),
            ], $by);

            return $order;
        });
    }

    /**
     * @param  array{quantity?: int, discount?: string, notes?: ?string}  $changes
     *
     * @throws AuthorizationException without pos.create-order (quantity, notes) or orders.discount (discount)
     * @throws ValidationException when the tab is closed, the item is cancelled or a value is out of range
     */
    public function updateItem(OrderItem $item, User $by, array $changes): OrderItem
    {
        return DB::transaction(function () use ($item, $by, $changes) {
            $locked = $this->lockLiveItem($item);
            $oldQuantity = $locked->quantity;

            if (array_key_exists('quantity', $changes) && $changes['quantity'] !== $locked->quantity) {
                $quantity = $changes['quantity'];
                if (! is_int($quantity) || $quantity > self::MAX_QUANTITY) {
                    $this->reject('quantity', 'Quantity must be between 1 and '.self::MAX_QUANTITY.'.');
                }
                if ($quantity < $locked->quantity) {
                    $this->reject('quantity', 'Use cancel to remove items; it needs a reason.');
                }
                $this->authorize($by, 'pos.create-order', 'You cannot add to orders.');
                $locked->quantity = $quantity;
            }

            if (array_key_exists('notes', $changes) && $changes['notes'] !== $locked->notes) {
                $this->authorize($by, 'pos.create-order', 'You cannot change orders.');
                $locked->notes = $changes['notes'];
            }

            $gross = Money::mul($locked->unit_price, $locked->quantity);
            if (array_key_exists('discount', $changes)) {
                $discount = (string) $changes['discount'];
                if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $discount) || bccomp($discount, $gross, 2) > 0) {
                    $this->reject('discount', "The discount must be between 0.00 and {$gross}.");
                }
                if (! Money::equals($discount, $locked->discount)) {
                    $this->authorize($by, 'orders.discount', 'You cannot give discounts.');
                    $this->refuseOncePaid($locked);
                    $locked->discount = Money::round($discount);
                }
            }

            $locked->line_total = Money::sub($gross, $locked->discount);
            $changed = array_keys($locked->getDirty());

            if ($changed !== []) {
                $old = array_intersect_key($locked->getOriginal(), array_flip($changed));
                $locked->save();
                $this->logger->log('order_item.updated', $locked, $old, $locked->only($changed), $by);
            }
            $item->setRawAttributes($locked->getAttributes(), true);

            if ($locked->quantity > $oldQuantity) {
                $this->stock->quantityAdded($item, $locked->quantity - $oldQuantity);
            }

            return $locked;
        });
    }

    /**
     * Cancels the whole line, or splits it when $quantity is below the line's: the original keeps the rest
     * and a new cancelled line takes the cancelled units and their pro-rata share of the discount.
     *
     * @throws AuthorizationException without orders.cancel-item
     * @throws ValidationException when the tab is closed, the item is cancelled, or the reason or quantity is invalid
     */
    public function cancelItem(OrderItem $item, User $by, string $reason, ?int $quantity = null): OrderItem
    {
        $this->authorize($by, 'orders.cancel-item', 'You cannot cancel items.');
        $reason = trim($reason);
        if ($reason === '') {
            $this->reject('reason', 'Give a reason for cancelling.');
        }
        if (mb_strlen($reason) > 200) {
            $this->reject('reason', 'The reason may not be longer than 200 characters.');
        }

        return DB::transaction(function () use ($item, $by, $reason, $quantity) {
            $locked = $this->lockLiveItem($item);
            $this->refuseOncePaid($locked);
            $before = $locked->only(['quantity', 'discount', 'line_total']);
            $quantity ??= $locked->quantity;

            if ($quantity < 1 || $quantity > $locked->quantity) {
                $this->reject('quantity', "You can cancel between 1 and {$locked->quantity}.");
            }

            $cancelled = $locked;
            if ($quantity < $locked->quantity) {
                $share = Money::prorate($locked->discount, $quantity, $locked->quantity);
                $cancelled = $locked->replicate();
                $cancelled->fill(['quantity' => $quantity, 'discount' => $share, 'line_total' => Money::sub(Money::mul($locked->unit_price, $quantity), $share)]);

                $rest = $locked->quantity - $quantity;
                $discount = Money::sub($locked->discount, $share);
                $locked->fill(['quantity' => $rest, 'discount' => $discount, 'line_total' => Money::sub(Money::mul($locked->unit_price, $rest), $discount)])->save();
            }

            $cancelled->forceFill([
                'status' => OrderItemStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
            ])->save();

            if (! OrderItem::where('order_id', $locked->order_id)->where('status', OrderItemStatus::Active)->exists()) {
                Order::whereKey($locked->order_id)->firstOrFail()->forceFill(['status' => OrderStatus::Cancelled])->save();
            }

            $this->stock->itemCancelled($cancelled, $quantity);
            $new = ['quantity' => $quantity, 'reason' => $reason, 'line_total' => $cancelled->line_total];
            if ($cancelled->isNot($locked)) {
                $new['split_from'] = $locked->id;
                $new['remaining'] = $locked->only(['quantity', 'discount', 'line_total']);
            }
            $this->logger->log('order_item.cancelled', $cancelled, $before, $new, $by);
            $item->setRawAttributes($locked->getAttributes(), true);

            return $cancelled;
        });
    }

    /** Validates every line against the menu (two queries in all) and returns the order_items rows. */
    private function snapshotLines(array $lines): array
    {
        $products = Product::sellable()->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');
        $variants = ProductVariant::whereKey(array_filter(array_column($lines, 'variant_id')))->get()->keyBy('id');

        return array_map(function (array $line, int $i) use ($products, $variants) {
            $product = $products->get($line['product_id']);
            if ($product === null) {
                $name = Product::withTrashed()->whereKey($line['product_id'])->value('name') ?? "#{$line['product_id']}";
                $this->reject("lines.{$i}.product_id", "{$name} is not on the menu right now.");
            }

            $variant = null;
            if (($line['variant_id'] ?? null) !== null) {
                $variant = $variants->get($line['variant_id']);
                if ($variant === null || $variant->product_id !== $product->id) {
                    $this->reject("lines.{$i}.variant_id", "That is not an option of {$product->name}.");
                }
            }

            $quantity = $line['quantity'] ?? null;
            if (! is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
                $this->reject("lines.{$i}.quantity", 'Quantity must be between 1 and '.self::MAX_QUANTITY.'.');
            }

            $price = $variant?->price ?? $product->price;

            return [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'unit_price' => $price,
                'unit_cost' => $variant?->cost ?? $product->cost,
                'quantity' => $quantity,
                'discount' => '0.00',
                'line_total' => Money::mul($price, $quantity),
                'notes' => $line['notes'] ?? null,
            ];
        }, $lines, array_keys($lines));
    }

    /** Locks the item's session, then the item; refuses a closed tab or a cancelled item. */
    private function lockLiveItem(OrderItem $item): OrderItem
    {
        $this->lockLiveSession(Order::whereKey($item->order_id)->value('table_session_id'));
        $locked = OrderItem::whereKey($item->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->status === OrderItemStatus::Cancelled) {
            $this->reject('item', 'This item is cancelled.');
        }

        return $locked;
    }

    /** Lowering the bill after money was taken could leave it below what was paid; refunds are out of scope. */
    private function refuseOncePaid(OrderItem $locked): void
    {
        $sessionId = Order::whereKey($locked->order_id)->value('table_session_id');
        if (DB::table('payments')->where('table_session_id', $sessionId)->exists()) {
            $this->reject('session', 'Payment was taken on this tab. Items can no longer be cancelled or discounted.');
        }
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

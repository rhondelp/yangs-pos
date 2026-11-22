<?php

namespace App\Services\Inventory;

use App\Enums\OrderItemStatus;
use App\Events\StockLevelCrossed;
use App\Events\UnusualAdjustmentRecorded;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecipeComponent;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Settings\SettingService;
use App\Services\Support\ReferenceGenerator;
use App\Support\BusinessDate;
use App\Support\Money;
use App\Support\Quantity;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The only writer of the stock ledger (inventory_movements) and of inventory_items.on_hand / avg_unit_cost
 * (CLAUDE.md "Inventory", ARCHITECTURE §3.4). Every change runs in one transaction with the items locked in id order.
 */
class InventoryService
{
    private const QUANTITY = '/^\d{1,10}(\.\d{1,3})?$/';

    private const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

    private const SCALE = 10; // working precision before rounding to the column's scale

    private const MAX_QUANTITY = '9999999999.999'; // leaves on_hand (14,3) room to grow

    private const MAX_UNIT_COST = '99999999.999999'; // decimal(14,6)

    private const MAX_TOTAL = '9999999999.99'; // decimal(12,2)

    public function __construct(private ActivityLogger $logger, private ReferenceGenerator $references, private SettingService $settings) {}

    /**
     * Records a delivery. Purchases are inventory, never operating expenses (ARCHITECTURE §3.6).
     *
     * @param  array{supplier_id?: mixed, invoice_no?: ?string, received_at: mixed, notes?: ?string,
     *               lines: list<array{inventory_item_id: mixed, inventory_unit_id?: mixed, quantity: mixed, line_cost: mixed}>}  $input
     *
     * @throws AuthorizationException without inventory.stock-in
     * @throws ValidationException
     */
    public function receive(User $by, array $input): StockReceipt
    {
        if (! $by->can('inventory.stock-in')) {
            throw new AuthorizationException('You cannot receive stock.');
        }

        $receivedAt = $this->receivedAt($input['received_at'] ?? null);
        $supplier = null;
        if (($input['supplier_id'] ?? null) !== null && $input['supplier_id'] !== '') {
            $supplier = is_numeric($input['supplier_id']) ? Supplier::find((int) $input['supplier_id']) : null;
            $supplier ?? $this->reject('supplier_id', 'Choose a supplier that is in use.');
        }
        $invoiceNo = trim((string) ($input['invoice_no'] ?? '')) ?: null;
        $notes = trim((string) ($input['notes'] ?? '')) ?: null;
        if ($invoiceNo !== null && mb_strlen($invoiceNo) > 60) {
            $this->reject('invoice_no', 'The invoice number may not be longer than 60 characters.');
        }
        if ($notes !== null && mb_strlen($notes) > 500) {
            $this->reject('notes', 'Notes may not be longer than 500 characters.');
        }
        $lines = $this->lines($input['lines'] ?? null);
        $total = array_reduce($lines, fn (string $sum, array $l) => bcadd($sum, $l['line_cost'], 2), '0.00');
        if (bccomp($total, self::MAX_TOTAL, 2) > 0) {
            $this->reject('lines', 'The stock-in total is too large. Split it into smaller stock-ins.');
        }

        return DB::transaction(function () use ($by, $receivedAt, $supplier, $invoiceNo, $notes, $lines, $total) {
            // Lock order is always ascending id, so two receipts sharing items cannot deadlock.
            $items = InventoryItem::whereKey(array_unique(array_column($lines, 'item_id')))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            // Re-check what was validated before the lock: an item archived, or a unit changed, in the meantime.
            $units = InventoryUnit::whereKey(array_filter(array_map(fn ($l) => $l['unit']?->id, $lines)))->get()->keyBy('id');
            foreach ($lines as $i => $line) {
                $items->has($line['item_id']) || $this->reject("lines.{$i}.inventory_item_id", 'Choose an inventory item that is in use.');
                if ($line['unit'] !== null && bccomp((string) ($units[$line['unit']->id]->factor ?? '0'), $line['factor'], 3) !== 0) {
                    $this->reject("lines.{$i}.inventory_unit_id", 'This unit was just changed. Check the line and try again.');
                }
            }
            $businessDate = BusinessDate::for($receivedAt);

            $receipt = StockReceipt::forceCreate([
                'reference' => $this->references->next('SR', $businessDate),
                'supplier_id' => $supplier?->id,
                'invoice_no' => $invoiceNo,
                'received_at' => $receivedAt,
                'business_date' => $businessDate,
                'received_by' => $by->id,
                'total_cost' => $total,
                'notes' => $notes,
            ]);

            $logged = [];
            foreach ($lines as $line) {
                $item = $items[$line['item_id']];
                [$baseQuantity, $unitCost] = [$line['base_quantity'], $line['unit_cost']];

                $receiptLine = StockReceiptItem::forceCreate([
                    'stock_receipt_id' => $receipt->id,
                    'inventory_item_id' => $item->id,
                    'inventory_unit_id' => $line['unit']?->id,
                    'unit_name' => $line['unit']?->name ?? $item->base_unit,
                    'unit_factor' => $line['factor'],
                    'quantity' => $line['quantity'],
                    'base_quantity' => $baseQuantity,
                    'line_cost' => $line['line_cost'],
                    'base_unit_cost' => $unitCost,
                ]);
                $this->stockIn($item, $baseQuantity, $line['line_cost'], $unitCost, $receiptLine, 'receipt', $businessDate, $by);

                $logged[] = [
                    'item' => $item->name,
                    'quantity' => $receiptLine->quantity.' '.$receiptLine->unit_name,
                    'base_quantity' => $receiptLine->base_quantity,
                    'line_cost' => $receiptLine->line_cost,
                ];
            }

            $this->logger->log('stock.received', $receipt, [], [
                'reference' => $receipt->reference,
                'supplier' => $supplier?->name,
                'invoice_no' => $invoiceNo,
                'total_cost' => $receipt->total_cost,
                'lines' => $logged,
            ], $by);

            return $receipt->load('lines', 'supplier');
        });
    }

    /**
     * Deducts every tracked line of a new order (ARCHITECTURE §3.3) and snapshots its cost: one sale movement per line
     * and recipe component. Runs inside the order's transaction, after the session lock.
     *
     * @return list<string> warnings for staff when an item went below zero (only when allow_negative_stock is on)
     *
     * @throws ValidationException on lines.N.quantity when stock would go below zero and that is not allowed
     */
    public function deductForOrder(Order $order, User $by): array
    {
        $lines = $order->items->values();
        $recipes = RecipeComponent::whereIn('product_id', $lines->pluck('product_id')->unique())->orderBy('id')->get()
            ->groupBy(fn (RecipeComponent $r) => $r->product_id.':'.($r->product_variant_id ?? 0));
        $tracked = Product::withTrashed()->whereKey($lines->pluck('product_id')->unique())->where('track_inventory', true)->pluck('id')->flip();

        $plan = [];
        foreach ($lines as $i => $line) {
            $components = $tracked->has($line->product_id) ? $recipes->get($line->product_id.':'.($line->product_variant_id ?? 0)) : null;
            if ($components !== null && $line->status === OrderItemStatus::Active) {
                $plan[] = ['line' => $line, 'units' => $line->quantity, 'key' => "lines.{$i}.quantity",
                    'components' => $components->map(fn (RecipeComponent $c) => ['inventory_item_id' => $c->inventory_item_id, 'quantity' => (string) $c->quantity])->all()];
            }
        }

        return $this->deduct($plan, $by, snapshot: true);
    }

    /**
     * Deducts the extra units of a raised quantity, using what the line took at sale time.
     *
     * @return list<string>
     *
     * @throws ValidationException on quantity
     */
    public function deductForQuantity(OrderItem $line, int $added, User $by): array
    {
        if ($line->stock_components === null || $added < 1) {
            return [];
        }

        return $this->deduct([['line' => $line, 'units' => $added, 'key' => 'quantity', 'components' => $line->stock_components]], $by, snapshot: false);
    }

    /**
     * Puts $units of a cancelled or voided line back on the shelf at the cost they left at, re-averaging (§3.4).
     * Lines that never took stock are ignored.
     */
    public function returnForLine(OrderItem $line, int $units, User $by): void
    {
        $components = $line->stock_components ?? [];
        if ($components === [] || $units < 1) {
            return;
        }
        $stock = $this->lockItems(array_column($components, 'inventory_item_id'));
        $businessDate = BusinessDate::for(now());
        foreach ($components as $component) {
            $quantity = Money::round(bcmul($component['quantity'], (string) $units, self::SCALE), 3);
            $exact = bcmul($quantity, $component['unit_cost'], self::SCALE);
            $this->stockIn($stock[$component['inventory_item_id']], $quantity, $exact, $component['unit_cost'], $line, 'void_return', $businessDate, $by);
        }
    }

    /** Reasons a manual stock-out may give (YANGS_SPEC §6B). */
    public const STOCK_OUT_REASONS = ['damaged', 'expired', 'missing', 'internal_use', 'other'];

    /**
     * Takes stock off the shelf by hand at the current average cost. Never below zero, whatever the setting:
     * you cannot write off stock that is not there.
     *
     * @param  array{quantity: mixed, inventory_unit_id?: mixed, reason: mixed, note?: ?string}  $input
     *
     * @throws AuthorizationException without inventory.adjust
     * @throws ValidationException
     */
    public function stockOut(InventoryItem $item, User $by, array $input): InventoryMovement
    {
        if (! $by->can('inventory.adjust')) {
            throw new AuthorizationException('You cannot adjust stock.');
        }
        $reason = $input['reason'] ?? null;
        if (! in_array($reason, self::STOCK_OUT_REASONS, true)) {
            $this->reject('reason', 'Choose why the stock is going out.');
        }
        $note = trim((string) ($input['note'] ?? '')) ?: null;
        if ($reason === 'other' && $note === null) {
            $this->reject('note', 'Say what happened when the reason is "Other".');
        }
        if ($note !== null && mb_strlen($note) > 300) {
            $this->reject('note', 'The note may not be longer than 300 characters.');
        }
        try { // the same unit, quantity and bounds rules as a stock-in line
            $line = $this->lines([['inventory_item_id' => $item->id, 'inventory_unit_id' => $input['inventory_unit_id'] ?? null, 'quantity' => $input['quantity'] ?? null, 'line_cost' => '0']])[0];
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [str_replace('lines.0.', '', $k) => $m])->all());
        }
        $line['quantity'] = $line['base_quantity']; // from here on, base units

        return DB::transaction(function () use ($item, $by, $reason, $note, $line) {
            $locked = $this->lockItems([$item->id])->get($item->id) ?? $this->reject('quantity', 'Choose an inventory item that is in use.');
            $after = Money::round(bcsub((string) $locked->on_hand, $line['quantity'], self::SCALE), 3);
            if (bccomp($after, '0', 3) < 0) {
                $this->reject('quantity', 'Only '.Quantity::format(bccomp((string) $locked->on_hand, '0', 3) > 0 ? (string) $locked->on_hand : '0')." {$locked->base_unit} of {$locked->name} on hand.");
            }

            $before = (string) $locked->on_hand;
            $value = Money::round(bcmul($line['quantity'], (string) $locked->avg_unit_cost, self::SCALE));
            // Flagged against today's threshold and stored, so a later setting change never rewrites history.
            $threshold = (string) ($this->settings->get('unusual_adjustment_amount') ?: '0');
            $unusual = bccomp($threshold, '0', 2) > 0 && bccomp($value, $threshold, 2) >= 0;
            $locked->forceFill(['on_hand' => $after])->save(); // the average does not change when stock leaves
            $movement = InventoryMovement::forceCreate([
                'inventory_item_id' => $locked->id,
                'type' => 'adjustment',
                'quantity' => bcsub('0', $line['quantity'], 3),
                'unit_cost' => $locked->avg_unit_cost,
                'total_cost' => $value,
                'balance_after' => $after,
                'reason' => $reason,
                'note' => $note,
                'is_unusual' => $unusual,
                'business_date' => BusinessDate::for(now()),
                'created_by' => $by->id,
            ]);
            $this->logger->log('stock.adjusted', $locked, ['on_hand' => $before], [
                'item' => $locked->name,
                'quantity' => $line['quantity'],
                'reason' => $reason,
                'note' => $note,
                'value' => $movement->total_cost,
                'on_hand' => $after,
                'unusual' => $unusual,
            ], $by);
            $this->crossed($locked, $before, $movement);
            if ($unusual) {
                UnusualAdjustmentRecorded::dispatch($movement);
            }

            return $movement;
        });
    }

    /** @param list<array{line: OrderItem, units: int, key: string, components: list<array{inventory_item_id: int, quantity: string}>}> $plan */
    private function deduct(array $plan, User $by, bool $snapshot): array
    {
        if ($plan === []) {
            return [];
        }
        $stock = $this->lockItems(array_merge(...array_map(fn (array $p) => array_column($p['components'], 'inventory_item_id'), $plan)));
        $allowNegative = (bool) $this->settings->get('allow_negative_stock');
        $businessDate = BusinessDate::for(now());
        $warnings = [];

        foreach ($plan as $p) {
            if ($snapshot) {
                // Cost of one sold unit = Σ component quantity × the item's average now (ARCHITECTURE §3.4 "snapshot at send").
                $components = array_map(fn (array $c) => [...$c, 'quantity' => Money::round($c['quantity'], 3), 'unit_cost' => (string) $stock[$c['inventory_item_id']]->avg_unit_cost], $p['components']);
                $unitCost = array_reduce($components, fn (string $sum, array $c) => bcadd($sum, bcmul($c['quantity'], $c['unit_cost'], self::SCALE), self::SCALE), '0');
                $p['line']->forceFill(['unit_cost' => Money::round($unitCost), 'stock_components' => $components])->save();
                $p['components'] = $components;
            }

            foreach ($p['components'] as $component) {
                $item = $stock[$component['inventory_item_id']];
                $quantity = Money::round(bcmul($component['quantity'], (string) $p['units'], self::SCALE), 3);
                $before = (string) $item->on_hand;
                $after = Money::round(bcsub($before, $quantity, self::SCALE), 3);

                if (bccomp($after, '0', 3) < 0) {
                    if (! $allowNegative) {
                        $left = bccomp($before, '0', 3) > 0 ? Quantity::format($before) : '0';
                        $this->reject($p['key'], "Only {$left} {$item->base_unit} of {$item->name} left.");
                    }
                    $warnings[] = "{$item->name} is below zero: ".Quantity::format($after)." {$item->base_unit}.";
                    if (bccomp($before, '0', 3) >= 0) { // flag the crossing once, not every sale after it
                        $this->logger->log('stock.went_negative', $item, ['on_hand' => $before], ['item' => $item->name, 'on_hand' => $after], $by);
                    }
                }

                $item->forceFill(['on_hand' => $after])->save(); // a sale leaves the average unchanged
                $movement = InventoryMovement::forceCreate([
                    'inventory_item_id' => $item->id,
                    'type' => 'sale',
                    'quantity' => bcsub('0', $quantity, 3),
                    'unit_cost' => $item->avg_unit_cost,
                    'total_cost' => Money::round(bcmul($quantity, (string) $item->avg_unit_cost, self::SCALE)),
                    'balance_after' => $after,
                    'source_type' => $p['line']->getMorphClass(),
                    'source_id' => $p['line']->id,
                    'business_date' => $businessDate,
                    'created_by' => $by->id,
                ]);
                // $before is the running balance, so two lines of one item fire once per real crossing.
                $this->crossed($item, $before, $movement);
            }
        }

        return $warnings;
    }

    /** Fires StockLevelCrossed when a movement left the item in a worse state than $before put it in (after commit only). */
    private function crossed(InventoryItem $item, string $before, InventoryMovement $movement): void
    {
        $from = InventoryItem::statusFor($before, (string) $item->low_stock_level);
        $to = InventoryItem::statusFor((string) $item->on_hand, (string) $item->low_stock_level);
        if (InventoryItem::STATUS_RANK[$to] > InventoryItem::STATUS_RANK[$from]) {
            StockLevelCrossed::dispatch($item, $from, $to, $movement);
        }
    }

    /** Locks up front every item the lines took stock from, so a multi-line void cannot lock them out of order. */
    public function lockStockOf(iterable $lines): void
    {
        $ids = [];
        foreach ($lines as $line) {
            $ids = [...$ids, ...array_column($line->stock_components ?? [], 'inventory_item_id')];
        }
        if ($ids !== []) {
            $this->lockItems($ids);
        }
    }

    /** Locks inventory items in ascending id (the one lock order, so concurrent tablets never deadlock). Archived items still count. */
    private function lockItems(array $ids): Collection
    {
        return InventoryItem::withTrashed()->whereKey(array_unique($ids))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * Adds stock to a locked item at a known cost and re-averages (ARCHITECTURE §3.4):
     * on hand > 0 → (on_hand × avg + cost) / (on_hand + qty); otherwise the new stock's own cost.
     * $totalCost is exact (used for the average); the movement stores it rounded to money.
     */
    private function stockIn(InventoryItem $item, string $quantity, string $totalCost, string $unitCost, Model $source, string $type, string $businessDate, User $by): void
    {
        $onHand = (string) $item->on_hand;
        $average = bccomp($onHand, '0', 3) > 0
            ? Money::round(bcdiv(bcadd(bcmul($onHand, (string) $item->avg_unit_cost, self::SCALE), $totalCost, self::SCALE), bcadd($onHand, $quantity, self::SCALE), self::SCALE), 6)
            : $unitCost;
        $balance = Money::round(bcadd($onHand, $quantity, self::SCALE), 3);

        $item->forceFill(['on_hand' => $balance, 'avg_unit_cost' => $average])->save();
        InventoryMovement::forceCreate([
            'inventory_item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => Money::round($totalCost),
            'balance_after' => $balance,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'business_date' => $businessDate,
            'created_by' => $by->id,
        ]);
    }

    /** @return list<array{item_id: int, unit: ?InventoryUnit, factor: string, quantity: string, line_cost: string}> */
    private function lines(mixed $lines): array
    {
        if (! is_array($lines) || $lines === []) {
            $this->reject('lines', 'Add at least one item.');
        }
        $lines = array_values($lines);
        $items = InventoryItem::whereKey(array_filter(array_column($lines, 'inventory_item_id'), 'is_numeric'))->get()->keyBy('id');
        $units = InventoryUnit::whereKey(array_filter(array_column($lines, 'inventory_unit_id'), 'is_numeric'))->get()->keyBy('id');

        return array_map(function (array $line, int $i) use ($items, $units) {
            $item = is_numeric($line['inventory_item_id'] ?? null) ? $items->get((int) $line['inventory_item_id']) : null;
            $item ?? $this->reject("lines.{$i}.inventory_item_id", 'Choose an inventory item that is in use.');

            $unit = null;
            if (($line['inventory_unit_id'] ?? null) !== null && $line['inventory_unit_id'] !== '') {
                $unit = is_numeric($line['inventory_unit_id']) ? $units->get((int) $line['inventory_unit_id']) : null;
                if ($unit === null || $unit->inventory_item_id !== $item->id) {
                    $this->reject("lines.{$i}.inventory_unit_id", "That unit is not one of {$item->name}'s units.");
                }
            }

            $quantity = (string) ($line['quantity'] ?? '');
            if (! preg_match(self::QUANTITY, $quantity) || bccomp($quantity, '0', 3) <= 0) {
                $this->reject("lines.{$i}.quantity", 'Enter a quantity above 0, up to 3 decimals.');
            }
            $cost = (string) ($line['line_cost'] ?? '');
            if (! preg_match(self::MONEY, $cost)) {
                $this->reject("lines.{$i}.line_cost", 'Enter what the line cost, ₱0.00 or more.');
            }

            // Bounds of the columns the figures land in, checked before any division or insert.
            $factor = $unit?->factor ?? '1.000';
            $base = Money::round(bcmul($quantity, $factor, self::SCALE), 3);
            if (bccomp($base, '0', 3) === 0) {
                $this->reject("lines.{$i}.quantity", "This is less than 0.001 {$item->base_unit}. Enter a bigger quantity.");
            }
            if (bccomp($base, self::MAX_QUANTITY, 3) > 0) {
                $this->reject("lines.{$i}.quantity", 'That is more stock than the system can hold. Check the quantity.');
            }
            $unitCost = Money::round(bcdiv($cost, $base, self::SCALE), 6);
            if (bccomp($unitCost, self::MAX_UNIT_COST, 6) > 0) {
                $this->reject("lines.{$i}.line_cost", "That works out to more than ₱99,999,999 per {$item->base_unit}. Check the quantity and cost.");
            }

            return [
                'item_id' => $item->id,
                'unit' => $unit,
                'factor' => $factor,
                'quantity' => Money::round($quantity, 3),
                'line_cost' => Money::round($cost),
                'base_quantity' => $base,
                'unit_cost' => $unitCost,
            ];
        }, $lines, array_keys($lines));
    }

    private function receivedAt(mixed $value): CarbonImmutable
    {
        try {
            $at = CarbonImmutable::parse((string) $value, config('app.timezone'));
        } catch (Throwable) {
            $this->reject('received_at', 'Enter the date received.');
        }
        if ($value === null || $value === '') {
            $this->reject('received_at', 'Enter the date received.');
        }
        // An explicit offset (an API client's "…Z") is kept by parse(); store the same moment in the bar's zone,
        // since the database column is written without an offset.
        $at = $at->setTimezone(config('app.timezone'));
        if ($at->isAfter(now()->addMinutes(5))) { // small allowance for tablet clock drift
            $this->reject('received_at', 'The date received cannot be in the future.');
        }

        return $at;
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

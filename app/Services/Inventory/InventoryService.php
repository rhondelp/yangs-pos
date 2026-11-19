<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Support\ReferenceGenerator;
use App\Support\BusinessDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
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

    public function __construct(private ActivityLogger $logger, private ReferenceGenerator $references) {}

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
                $this->stockIn($item, $baseQuantity, $line['line_cost'], $unitCost, $receiptLine, $businessDate, $by);

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
     * Adds stock to a locked item at a known cost and re-averages (ARCHITECTURE §3.4):
     * on hand > 0 → (on_hand × avg + cost) / (on_hand + qty); otherwise the new stock's own cost.
     */
    private function stockIn(InventoryItem $item, string $quantity, string $totalCost, string $unitCost, StockReceiptItem $source, string $businessDate, User $by): void
    {
        $onHand = (string) $item->on_hand;
        $average = bccomp($onHand, '0', 3) > 0
            ? Money::round(bcdiv(bcadd(bcmul($onHand, (string) $item->avg_unit_cost, self::SCALE), $totalCost, self::SCALE), bcadd($onHand, $quantity, self::SCALE), self::SCALE), 6)
            : $unitCost;
        $balance = Money::round(bcadd($onHand, $quantity, self::SCALE), 3);

        $item->forceFill(['on_hand' => $balance, 'avg_unit_cost' => $average])->save();
        InventoryMovement::forceCreate([
            'inventory_item_id' => $item->id,
            'type' => 'receipt',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
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

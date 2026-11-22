<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\RecipeComponent;
use App\Models\StockReceiptItem;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Inventory items and their units. Stock and cost are never set here: only InventoryService moves them. */
class InventoryItemService
{
    private const FIELDS = ['name', 'sku', 'base_unit', 'low_stock_level', 'category_id'];

    private const QUANTITY = '/^\d{1,10}(\.\d{1,3})?$/';

    public function __construct(private ActivityLogger $logger) {}

    public function create(array $data): InventoryItem
    {
        return DB::transaction(function () use ($data) {
            $item = InventoryItem::create(Arr::only($data, self::FIELDS));
            $this->logger->log('inventory_item.created', $item, new: $item->fresh()->only(self::FIELDS));

            return $item;
        });
    }

    /** @throws ValidationException when the base unit would change on an item that already has stock history */
    public function update(InventoryItem $item, array $data): InventoryItem
    {
        return DB::transaction(function () use ($item, $data) {
            // Locked, so a first stock-in cannot slip in between the history check and the save.
            InventoryItem::whereKey($item->getKey())->lockForUpdate()->first();
            $item->fill(Arr::only($data, self::FIELDS));
            if ($item->isDirty('base_unit')) {
                $this->guardBaseUnit($item);
            }

            $changed = array_keys($item->getDirty());
            $old = array_intersect_key($item->getOriginal(), array_flip($changed));
            $item->save();
            if ($changed !== []) {
                $this->logger->log('inventory_item.updated', $item, $old, $item->only($changed));
            }

            return $item;
        });
    }

    public function archive(InventoryItem $item): void
    {
        DB::transaction(fn () => $item->delete()); // logs inventory_item.archived; history stays
    }

    public function restore(InventoryItem $item): void
    {
        DB::transaction(fn () => $item->restore());
    }

    /**
     * Replaces the item's units with $units (rows with an id are kept and updated, missing ones removed).
     *
     * @param  list<array{id?: ?int, name: string, factor: string}>  $units
     *
     * @throws ValidationException (keys units.N.name / units.N.factor / units.N.id)
     */
    public function saveUnits(InventoryItem $item, array $units): InventoryItem
    {
        $units = array_values($units);
        $seen = [];
        foreach ($units as $i => $unit) {
            $name = trim((string) ($unit['name'] ?? ''));
            $key = mb_strtolower($name);
            if ($name === '' || mb_strlen($name) > 30) {
                $this->reject("units.{$i}.name", 'Give the unit a name of up to 30 characters.');
            }
            if ($key === mb_strtolower($item->base_unit)) {
                $this->reject("units.{$i}.name", 'That is the base unit already.');
            }
            if (isset($seen[$key])) {
                $this->reject("units.{$i}.name", 'Each unit needs its own name.');
            }
            $factor = (string) ($unit['factor'] ?? '');
            if (! preg_match(self::QUANTITY, $factor) || bccomp($factor, '0', 3) <= 0) {
                $this->reject("units.{$i}.factor", "Enter how many {$item->base_unit} make one {$name}, up to 3 decimals.");
            }
            $seen[$key] = true;
            $units[$i] = ['id' => isset($unit['id']) ? (int) $unit['id'] : null, 'name' => $name, 'factor' => $factor];
        }

        return DB::transaction(function () use ($item, $units) {
            // The item row lock serialises unit edits with stock-ins (InventoryService locks the same row).
            InventoryItem::whereKey($item->getKey())->lockForUpdate()->first();
            $current = InventoryUnit::where('inventory_item_id', $item->id)->lockForUpdate()->get()->keyBy('id');
            $old = $current->mapWithKeys(fn (InventoryUnit $u) => [$u->name => $u->factor])->all();
            $keep = array_filter(array_column($units, 'id'));

            foreach ($current->except($keep) as $removed) {
                // Receipt lines keep their own name and factor snapshots, but the foreign key still points here.
                if (StockReceiptItem::where('inventory_unit_id', $removed->id)->exists()) {
                    $this->reject('units', "\"{$removed->name}\" was used on a stock-in. Keep it or rename it.");
                }
                $removed->delete();
            }
            // Park kept rows on temporary names first, so swapping or reusing names never trips the unique index mid-save.
            foreach ($units as $unit) {
                if ($unit['id'] !== null && $current->has($unit['id'])) {
                    $current[$unit['id']]->update(['name' => '#'.$unit['id']]);
                }
            }
            foreach ($units as $i => $unit) {
                if ($unit['id'] === null) {
                    InventoryUnit::create(['inventory_item_id' => $item->id, 'name' => $unit['name'], 'factor' => $unit['factor']]);
                } elseif ($current->has($unit['id'])) {
                    $current[$unit['id']]->update(['name' => $unit['name'], 'factor' => $unit['factor']]);
                } else {
                    $this->reject("units.{$i}.id", 'That unit belongs to another item.');
                }
            }

            $new = $item->units()->get()->mapWithKeys(fn (InventoryUnit $u) => [$u->name => $u->factor])->all();
            if ($old != $new) {
                $this->logger->log('inventory_item.units_changed', $item, ['units' => $old], ['units' => $new]);
            }

            return $item->load('units');
        });
    }

    /** Movements, units ("case = 24") and recipes are all counted in the base unit; changing it would silently change them. */
    private function guardBaseUnit(InventoryItem $item): void
    {
        $old = $item->getOriginal('base_unit');
        if (InventoryUnit::where('inventory_item_id', $item->id)->whereRaw('lower(name) = ?', [mb_strtolower($item->base_unit)])->exists()) {
            $this->reject('base_unit', 'That is the name of one of its units.');
        }
        if (InventoryMovement::where('inventory_item_id', $item->id)->exists()) {
            $this->reject('base_unit', 'This item already has stock history. Its base unit cannot change.');
        }
        if (InventoryUnit::where('inventory_item_id', $item->id)->exists() || RecipeComponent::where('inventory_item_id', $item->id)->exists()) {
            $this->reject('base_unit', "Units or recipes are counted in {$old}. Remove them first, or create a new item.");
        }
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

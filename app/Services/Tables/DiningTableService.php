<?php

namespace App\Services\Tables;

use App\Models\DiningTable;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Owner-side table setup. Status changes go through TableStatusService.
class DiningTableService
{
    public function __construct(private ActivityLogger $logger) {}

    /** @param array{label: string, capacity?: ?int, sort_order?: int} $data */
    public function create(array $data): DiningTable
    {
        return DB::transaction(function () use ($data) {
            $table = DiningTable::create([
                'sort_order' => (DiningTable::max('sort_order') ?? 0) + 10,
                ...Arr::only($data, ['label', 'capacity', 'sort_order']),
            ]);
            $this->logger->log('table.created', $table, new: $table->only('label', 'capacity', 'sort_order'));

            return $table;
        });
    }

    /** @param array{label?: string, capacity?: ?int, sort_order?: int} $data */
    public function update(DiningTable $table, array $data): DiningTable
    {
        return DB::transaction(function () use ($table, $data) {
            $table->fill(Arr::only($data, ['label', 'capacity', 'sort_order']));
            $new = $table->getDirty();
            $old = array_intersect_key($table->getRawOriginal(), $new);
            $table->save();

            if ($new) {
                $this->logger->log('table.updated', $table, $old, $new);
            }

            return $table;
        });
    }

    /** @throws ValidationException when deactivating a table that has a tab */
    public function setActive(DiningTable $table, bool $active): DiningTable
    {
        return DB::transaction(function () use ($table, $active) {
            $locked = DiningTable::whereKey($table->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->is_active === $active) {
                return $locked;
            }
            if (! $active && ! $locked->status->isManual()) {
                throw ValidationException::withMessages(['table' => "Table {$locked->label} has an open tab. Close it before taking the table out of use."]);
            }

            $locked->update(['is_active' => $active]);
            $this->logger->log($active ? 'table.activated' : 'table.deactivated', $locked, ['is_active' => ! $active], ['is_active' => $active]);

            return $locked;
        });
    }
}

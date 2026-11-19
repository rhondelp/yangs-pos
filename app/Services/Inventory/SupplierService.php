<?php

namespace App\Services\Inventory;

use App\Models\Supplier;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    private const FIELDS = ['name', 'contact_person', 'phone', 'email', 'address', 'notes'];

    public function __construct(private ActivityLogger $logger) {}

    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::create(Arr::only($data, self::FIELDS));
            $this->logger->log('supplier.created', $supplier, new: $supplier->only(self::FIELDS));

            return $supplier;
        });
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data) {
            $supplier->fill(Arr::only($data, self::FIELDS));
            $changed = array_keys($supplier->getDirty());
            $old = array_intersect_key($supplier->getOriginal(), array_flip($changed));
            $supplier->save();
            if ($changed !== []) {
                $this->logger->log('supplier.updated', $supplier, $old, $supplier->only($changed));
            }

            return $supplier;
        });
    }

    public function archive(Supplier $supplier): void
    {
        DB::transaction(fn () => $supplier->delete()); // logs supplier.archived; past receipts keep pointing at it
    }

    public function restore(Supplier $supplier): void
    {
        DB::transaction(fn () => $supplier->restore()); // logs supplier.restored
    }
}

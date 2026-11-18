<?php

namespace App\Services\Tables;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of dining_tables.status. People (floor, API) switch between Available, Reserved
 * and Cleaning; the tab service passes $system = true for Occupied / Awaiting payment and calls this
 * inside its own transaction so the table and the tab change together.
 */
class TableStatusService
{
    public function __construct(private ActivityLogger $logger) {}

    /** @throws ValidationException (key "status") when the change is not allowed */
    public function change(DiningTable $table, TableStatus $to, bool $system = false, ?string $reason = null): DiningTable
    {
        return DB::transaction(function () use ($table, $to, $system, $reason) {
            $locked = DiningTable::whereKey($table->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }

            if (! $locked->is_active) {
                $this->reject("Table {$locked->label} is not in use. Reactivate it first.");
            }

            if (! $from->canMoveTo($to, $system)) {
                $this->reject(match (true) {
                    ! $system && ! $to->isManual() => 'Open a tab on the table to mark it '.strtolower($to->label()).'.',
                    ! $system && ! $from->isManual() => "Table {$locked->label} has an open tab. Close or move the tab first.",
                    default => "Table {$locked->label} cannot go from ".strtolower($from->label()).' to '.strtolower($to->label()).'.',
                });
            }

            $locked->forceFill(['status' => $to])->save();
            $this->logger->log('table.status_changed', $locked,
                ['status' => $from->value],
                array_filter(['status' => $to->value, 'reason' => $reason]));

            $table->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}

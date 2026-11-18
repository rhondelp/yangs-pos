<?php

namespace App\Services\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Tables\TableStatusService;
use App\Support\BusinessDate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Opens and cancels tabs; the table and the tab always change in one transaction. */
class TableSessionService
{
    public function __construct(private ActivityLogger $logger, private TableStatusService $tables) {}

    /** @throws ValidationException when the table cannot take a tab */
    public function open(DiningTable $table, User $by, ?string $notes = null): TableSession
    {
        try {
            return DB::transaction(function () use ($table, $by, $notes) {
                $locked = DiningTable::whereKey($table->getKey())->lockForUpdate()->firstOrFail();

                if (! $locked->is_active) {
                    $this->reject('table', "Table {$locked->label} is not in use.");
                }
                if (TableSession::where('dining_table_id', $locked->id)->whereIn('status', ['open', 'billing'])->exists()) {
                    $this->reject('table', "Table {$locked->label} already has an open tab.");
                }
                if (! in_array($locked->status, [TableStatus::Available, TableStatus::Reserved], true)) {
                    $this->reject('table', "Table {$locked->label} is not free. Mark it available first.");
                }

                $this->tables->change($locked, TableStatus::Occupied, system: true, actor: $by);

                $session = TableSession::create([
                    'dining_table_id' => $locked->id,
                    'business_date' => BusinessDate::for(now()),
                    'opened_by' => $by->id,
                    'opened_at' => now(),
                    'notes' => $notes,
                ]);
                $this->logger->log('session.opened', $session, [], ['table' => $locked->label], $by);
                $table->setRawAttributes($locked->getAttributes(), true);

                return $session;
            });
        } catch (UniqueConstraintViolationException) {
            $this->reject('table', "Table {$table->label} already has an open tab.");
        }
    }

    /**
     * @throws AuthorizationException without pos.cancel-session
     * @throws ValidationException when the tab is not live or still has active items
     */
    public function cancel(TableSession $session, User $by, string $reason): TableSession
    {
        if (! $by->can('pos.cancel-session')) {
            throw new AuthorizationException('You cannot cancel a tab.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            $this->reject('reason', 'Give a reason for cancelling.');
        }
        if (mb_strlen($reason) > 200) {
            $this->reject('reason', 'The reason may not be longer than 200 characters.');
        }

        return DB::transaction(function () use ($session, $by, $reason) {
            $locked = TableSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isOpen()) {
                $this->reject('session', 'This tab is not open.');
            }
            if ($locked->items()->where('order_items.status', OrderItemStatus::Active->value)->exists()) {
                $this->reject('session', 'Cancel or remove the items first.');
            }

            $was = $locked->status->value;
            $locked->forceFill(['status' => SessionStatus::Cancelled, 'closed_at' => now(), 'closed_by' => $by->id])->save();
            $this->tables->change($locked->table, TableStatus::Available, system: true, actor: $by);
            $this->logger->log('session.cancelled', $locked,
                ['status' => $was], ['status' => 'cancelled', 'reason' => $reason], $by);
            $session->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

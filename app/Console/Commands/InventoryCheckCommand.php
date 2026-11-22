<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only consistency check: for every inventory item (archived ones too), the sum of its ledger
 * movements must equal the cached inventory_items.on_hand. Exit code 1 when any item differs.
 */
class InventoryCheckCommand extends Command
{
    protected $signature = 'inventory:check';

    protected $description = 'Check that every inventory item\'s on-hand quantity equals the sum of its stock movements';

    public function handle(): int
    {
        $rows = DB::table('inventory_items as i')
            ->leftJoin('inventory_movements as m', 'm.inventory_item_id', '=', 'i.id')
            ->groupBy('i.id', 'i.name', 'i.on_hand')
            ->orderBy('i.id')
            ->select('i.id', 'i.name', 'i.on_hand', DB::raw('COALESCE(SUM(m.quantity), 0) AS ledger'))
            ->get();

        $wrong = $rows->filter(fn ($r) => bccomp((string) $r->on_hand, (string) $r->ledger, 3) !== 0);
        if ($wrong->isEmpty()) {
            $this->info("All {$rows->count()} items match their stock ledger.");

            return self::SUCCESS;
        }

        $this->error("{$wrong->count()} of {$rows->count()} items do not match their stock ledger.");
        $this->table(['ID', 'Item', 'On hand', 'Ledger sum', 'Difference'], $wrong->map(fn ($r) => [
            $r->id,
            $r->name,
            bcadd((string) $r->on_hand, '0', 3),
            bcadd((string) $r->ledger, '0', 3),
            bcsub((string) $r->on_hand, (string) $r->ledger, 3),
        ])->all());

        return self::FAILURE;
    }
}

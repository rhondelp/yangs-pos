<?php

/*
 * One concurrent caller for tests/Feature/Concurrency/ConcurrencyTest.php.
 * Usage: php tests/Concurrency/worker.php <scenario> <startAt microtime> <json args>
 * Boots the app with the parent's (testing) environment, waits for the shared start time so all
 * workers hit PostgreSQL together, runs one service call and prints "ok:<id>" or "refused:<message>",
 * then "|lead=<seconds it waited at the start line>".
 */

use App\Models\DiningTable;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Corrections\VoidService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TableSessionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $scenario, $startAt, $json] = $argv;
$args = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

if (DB::connection()->getDatabaseName() !== 'yangs_test') {
    fwrite(STDERR, "refusing to run outside the test database\n");
    exit(2);
}
DB::connection()->getPdo(); // connect before the start line, so only the work itself races

$lead = (float) $startAt - microtime(true); // > 0: this worker was ready before the start line, so it really raced
while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $by = User::findOrFail($args['user_id']);
    $id = match ($scenario) {
        'pay' => app(PaymentService::class)->record(TableSession::findOrFail($args['session_id']), $by, [
            'method_id' => $args['method_id'], 'idempotency_key' => $args['key'],
        ])->id,
        'open' => app(TableSessionService::class)->open(DiningTable::findOrFail($args['table_id']), $by)->id,
        // A quantity raise takes no reference counter, so only the inventory row lock can serialise these.
        'raise' => app(OrderService::class)->updateItem(\App\Models\OrderItem::findOrFail($args['item_id']), $by, ['quantity' => 2])->id,
        'sell' => app(OrderService::class)->addOrder(TableSession::findOrFail($args['session_id']), $by, [['product_id' => $args['product_id'], 'quantity' => 1]])->id,
        'void' => app(VoidService::class)->voidItem(OrderItem::findOrFail($args['item_id']), $by, 1, 'Spilled', [], $args['key'])->id,
    };
    echo "ok:{$id}";
} catch (ValidationException $e) {
    echo 'refused:'.collect($e->errors())->flatten()->first();
}
echo '|lead='.round($lead, 3);

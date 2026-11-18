<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tables\ChangeTableStatusRequest;
use App\Http\Requests\Tables\TableFilterRequest;
use App\Models\DiningTable;
use App\Services\Tables\TableStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// The cashier's floor: active tables only.
class FloorController extends Controller
{
    public function index(TableFilterRequest $request): View
    {
        $status = $request->status();

        return view('floor.index', [
            'tables' => DiningTable::query()->active()->when($status, fn ($q) => $q->where('status', $status))->floorOrder()->get(),
            'counts' => DiningTable::query()->active()->selectRaw('status, count(*) as total')->groupBy('status')
                ->pluck('total', 'status')->map(fn ($n) => (int) $n)->all(),
            'current' => $status,
        ]);
    }

    public function updateStatus(ChangeTableStatusRequest $request, DiningTable $table, TableStatusService $statuses): RedirectResponse
    {
        $table = $statuses->change($table, $request->targetStatus(), reason: $request->validated('reason'));

        return back()->with('status', "Table {$table->label} is now ".strtolower($table->status->label()).'.');
    }
}

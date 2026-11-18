<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tables\ChangeTableStatusRequest;
use App\Http\Requests\Tables\TableFilterRequest;
use App\Http\Resources\V1\TableResource;
use App\Models\DiningTable;
use App\Services\Tables\TableStatusService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TableController extends Controller
{
    public function index(TableFilterRequest $request): AnonymousResourceCollection
    {
        $status = $request->status();

        return TableResource::collection(
            DiningTable::query()
                ->when(! $request->includeInactive(), fn ($q) => $q->active())
                ->when($status, fn ($q) => $q->where('status', $status))
                ->floorOrder()
                ->get()
        );
    }

    public function updateStatus(ChangeTableStatusRequest $request, DiningTable $table, TableStatusService $statuses): TableResource
    {
        return new TableResource($statuses->change($table, $request->targetStatus(), reason: $request->validated('reason')));
    }
}

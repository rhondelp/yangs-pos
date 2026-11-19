<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelSessionRequest;
use App\Http\Requests\Orders\OpenSessionRequest;
use App\Http\Resources\V1\TableSessionResource;
use App\Models\DiningTable;
use App\Models\TableSession;
use App\Services\Orders\TableSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TableSessionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TableSessionResource::collection(
            TableSession::query()
                ->whereIn('status', [SessionStatus::Open, SessionStatus::Billing])
                ->with('table')
                ->orderBy('opened_at')
                ->get()
        );
    }

    public function show(TableSession $session): TableSessionResource
    {
        return new TableSessionResource($session->load('table', 'orders.items.voids', 'orders.cashier'));
    }

    public function store(OpenSessionRequest $request, DiningTable $table, TableSessionService $sessions): JsonResponse
    {
        $session = $sessions->open($table, $request->user(), $request->validated('notes'));

        return (new TableSessionResource($session->load('table', 'orders.items.voids', 'orders.cashier')))->response()->setStatusCode(201);
    }

    public function cancel(CancelSessionRequest $request, TableSession $session, TableSessionService $sessions): TableSessionResource
    {
        return new TableSessionResource($sessions->cancel($session, $request->user(), $request->validated('reason'))->load('table', 'orders.items.voids', 'orders.cashier'));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ActivityLogFilterRequest;
use App\Http\Resources\V1\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function index(ActivityLogFilterRequest $request): AnonymousResourceCollection
    {
        return ActivityLogResource::collection(
            ActivityLog::query()
                ->visibleTo($request->user())
                ->filter($request->filters())
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 25))
                ->withQueryString()
        );
    }
}

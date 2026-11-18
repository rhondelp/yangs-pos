<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ActivityLogFilterRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(ActivityLogFilterRequest $request): View
    {
        return view('activity-logs.index', [
            'logs' => ActivityLog::query()->filter($request->filters())->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $request->filters(),
            'users' => User::orderBy('name')->get(['id', 'name', 'username']),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function show(ActivityLog $activityLog): View
    {
        $old = $activityLog->old_values ?? [];
        $new = $activityLog->new_values ?? [];

        return view('activity-logs.show', [
            'log' => $activityLog,
            'fields' => array_values(array_unique([...array_keys($old), ...array_keys($new)])),
            'old' => $old,
            'new' => $new,
        ]);
    }
}

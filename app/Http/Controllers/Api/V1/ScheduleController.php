<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CopyWeekRequest;
use App\Http\Requests\Staff\ReasonRequest;
use App\Http\Requests\Staff\ScheduleFilterRequest;
use App\Http\Requests\Staff\ScheduleRequest;
use App\Http\Requests\Staff\ShiftRequest;
use App\Http\Resources\V1\ScheduleResource;
use App\Http\Resources\V1\ShiftResource;
use App\Models\Schedule;
use App\Models\Shift;
use App\Services\Staff\ScheduleService;
use App\Services\Staff\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

// Authorization: routes can:schedules.view (lists) and can:schedules.manage (writes, templates).
class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $schedules, private ShiftService $shifts) {}

    /** Live shifts of ?date= (one business day) or ?week= (ISO, default this week), optional ?employee_id=. */
    public function index(ScheduleFilterRequest $request): AnonymousResourceCollection
    {
        $rows = $request->validated('date')
            ? $this->schedules->day($request->businessDate())
            : collect($this->schedules->week($request->businessWeek())['cells'])->flatten()->sortBy('starts_at')->values();
        if ($employee = $request->validated('employee_id')) {
            $rows = $rows->where('employee_id', (int) $employee)->values();
        }

        return ScheduleResource::collection($rows->each->loadMissing('employee', 'shift'));
    }

    public function store(ScheduleRequest $request): JsonResponse
    {
        return (new ScheduleResource($this->schedules->create($request->user(), $request->scheduleData())))->response()->setStatusCode(201);
    }

    public function update(ScheduleRequest $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->update($schedule, $request->user(), $request->scheduleData())->load('employee', 'shift'));
    }

    public function cancel(ReasonRequest $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->cancel($schedule, $request->user(), $request->validated('reason'))->load('employee', 'shift'));
    }

    /** {copied, skipped: [{employee, date, reason}]} */
    public function copyWeek(CopyWeekRequest $request): JsonResource
    {
        return new JsonResource($this->schedules->copyWeek($request->user(), $request->validated('from_week'), $request->validated('to_week')));
    }

    public function shifts(): AnonymousResourceCollection
    {
        return ShiftResource::collection(Shift::orderBy('id')->get());
    }

    public function updateShift(ShiftRequest $request, Shift $shift): ShiftResource
    {
        return new ShiftResource($this->shifts->update($shift, $request->user(), $request->shiftData()));
    }
}

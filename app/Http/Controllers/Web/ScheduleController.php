<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CopyWeekRequest;
use App\Http\Requests\Staff\ReasonRequest;
use App\Http\Requests\Staff\ScheduleFilterRequest;
use App\Http\Requests\Staff\ScheduleRequest;
use App\Models\ActivityLog;
use App\Models\Schedule;
use App\Models\Shift;
use App\Services\Staff\BonusService;
use App\Services\Staff\ScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// Authorization: routes can:schedules.view (week, day) and can:schedules.manage (writes). Bonuses show with employees.manage.
class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $schedules, private BonusService $bonuses) {}

    public function week(ScheduleFilterRequest $request): View
    {
        $week = $request->businessWeek();
        $grid = $this->schedules->week($week);
        $monday = ScheduleService::monday($week);
        $ids = collect($grid['cells'])->flatten()->pluck('id');

        return view('schedule.week', [
            ...$grid,
            'week' => $week,
            'label' => $monday->format('M j').' – '.$monday->addDays(6)->format('M j, Y'),
            'prev' => ScheduleService::weekOf($monday->subWeek()->toDateString()),
            'next' => ScheduleService::weekOf($monday->addWeek()->toDateString()),
            'templates' => Shift::where('is_active', true)->orderBy('start_time')->get(),
            'history' => ActivityLog::where('subject_type', (new Schedule)->getMorphClass())->whereIn('subject_id', $ids)
                ->where('action', 'schedule.changed')->orderBy('id')->get()->groupBy('subject_id'),
            'copyTargets' => collect(range(1, 4))->mapWithKeys(function (int $i) use ($monday) {
                $target = $monday->addWeeks($i);

                return [ScheduleService::weekOf($target->toDateString()) => $target->format('M j').' – '.$target->addDays(6)->format('M j')];
            })->all(),
        ]);
    }

    public function day(ScheduleFilterRequest $request): View
    {
        $date = $request->businessDate();
        $canPay = $request->user()->can('employees.manage');

        return view('schedule.day', [
            'date' => $date,
            'shifts' => $this->schedules->day($date),
            'bonuses' => $canPay ? $this->bonuses->forNight($date) : collect(),
            'canPay' => $canPay,
            'week' => ScheduleService::weekOf($date),
        ]);
    }

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $shift = $this->schedules->create($request->user(), $request->scheduleData());

        return $this->toWeek($shift, "Shift added for {$shift->employee->full_name}.");
    }

    public function update(ScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $shift = $this->schedules->update($schedule, $request->user(), $request->scheduleData());

        return $this->toWeek($shift, 'Shift changed.');
    }

    public function cancel(ReasonRequest $request, Schedule $schedule): RedirectResponse
    {
        $shift = $this->schedules->cancel($schedule, $request->user(), $request->validated('reason'));

        return $this->toWeek($shift, 'Shift cancelled.');
    }

    public function copyWeek(CopyWeekRequest $request): RedirectResponse
    {
        $result = $this->schedules->copyWeek($request->user(), $request->validated('from_week'), $request->validated('to_week'));
        $message = 'Copied '.$result['copied'].' '.str('shift')->plural($result['copied']).'.';
        if ($result['skipped'] !== []) {
            $message .= ' Skipped '.count($result['skipped']).': '.collect($result['skipped'])
                ->map(fn (array $s) => $s['employee'].', '.CarbonImmutable::parse($s['date'])->format('D M j').' – '.$s['reason'])->implode('; ').'.';
        }

        return redirect()->route('schedule.week', ['week' => $request->validated('to_week')])->with('status', $message);
    }

    private function toWeek(Schedule $shift, string $status): RedirectResponse
    {
        return redirect()->route('schedule.week', ['week' => ScheduleService::weekOf($shift->duty_date->toDateString())])->with('status', $status);
    }
}

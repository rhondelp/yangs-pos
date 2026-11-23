<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ShiftRequest;
use App\Models\Shift;
use App\Services\Staff\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// Authorization: routes can:schedules.manage.
class ShiftController extends Controller
{
    public function __construct(private ShiftService $shifts) {}

    public function index(): View
    {
        return view('shifts.index', ['shifts' => Shift::orderBy('start_time')->get()]);
    }

    public function update(ShiftRequest $request, Shift $shift): RedirectResponse
    {
        $this->shifts->update($shift, $request->user(), $request->shiftData());

        return redirect()->route('shifts.index')->with('status', "{$shift->name} saved. Shifts already on the schedule keep their times.");
    }
}

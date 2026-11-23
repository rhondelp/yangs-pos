<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BonusRequest;
use App\Http\Requests\Staff\EmployeeRequest;
use App\Http\Requests\Staff\RateRequest;
use App\Http\Requests\Staff\ReasonRequest;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeRate;
use App\Models\User;
use App\Services\Staff\BonusService;
use App\Services\Staff\EmployeeService;
use App\Support\BusinessDate;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Authorization: routes can:employees.view (list, page) and can:employees.manage (everything else). Pay shows only with manage.
class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $employees, private BonusService $bonuses) {}

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', 'in:active,on_leave,resigned,terminated,archived']]);
        $status = $request->query('status');
        $list = Employee::query()
            ->when($status === 'archived', fn ($q) => $q->onlyTrashed(), fn ($q) => $q->when($status, fn ($q, $s) => $q->where('employment_status', $s)))
            ->orderBy('full_name')->get();
        $canPay = $request->user()->can('employees.manage');
        $today = BusinessDate::for(now());

        return view('employees.index', [
            'employees' => $list,
            'status' => $status,
            'pay' => $canPay ? $list->mapWithKeys(fn (Employee $e) => [$e->id => $this->employees->rateOn($e, $today)])->all() : [],
        ]);
    }

    public function create(): View
    {
        return view('employees.form', ['employee' => null, 'logins' => $this->logins(null)]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = $this->employees->create($request->user(), $request->employeeData());

        return redirect()->route('employees.show', $employee)->with('status', "{$employee->full_name} added.");
    }

    public function show(Request $request, Employee $employee): View
    {
        $canPay = $request->user()->can('employees.manage');

        return view('employees.show', [
            'employee' => $employee->load('user'),
            'current' => $canPay ? $this->employees->rateOn($employee, BusinessDate::for(now())) : null,
            'rates' => $canPay ? $employee->rates()->with('createdBy', 'replacedBy')->orderByDesc('effective_from')->orderByDesc('id')->get() : collect(),
            'bonuses' => $canPay ? $employee->bonuses()->with('givenBy', 'voidedBy', 'replacedBy')->orderByDesc('business_date')->orderByDesc('id')->limit(30)->get() : collect(),
            'canPay' => $canPay,
            'today' => BusinessDate::for(now()),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('employees.form', ['employee' => $employee, 'logins' => $this->logins($employee)]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employees->update($employee, $request->user(), $request->employeeData());

        return redirect()->route('employees.show', $employee)->with('status', 'Saved.');
    }

    public function archive(Request $request, Employee $employee): RedirectResponse
    {
        $this->employees->archive($employee, $request->user());

        return redirect()->route('employees.index')->with('status', "{$employee->full_name} archived.");
    }

    public function restore(Request $request, Employee $employee): RedirectResponse
    {
        $this->employees->restore($employee, $request->user());

        return redirect()->route('employees.show', $employee)->with('status', "{$employee->full_name} restored.");
    }

    public function storeRate(RateRequest $request, Employee $employee): RedirectResponse
    {
        $rate = $this->employees->addRate($employee, $request->user(), $request->rateData());

        return redirect()->route('employees.show', $employee)->with('status', 'New pay of '.Money::format($rate->rate).' from '.$rate->effective_from->format('M j, Y').'.');
    }

    public function updateRate(RateRequest $request, EmployeeRate $rate): RedirectResponse
    {
        $this->employees->editRate($rate, $request->user(), $request->rateData());

        return redirect()->route('employees.show', $rate->employee_id)->with('status', 'Pay changed. The old version stays in the history.');
    }

    public function storeBonus(BonusRequest $request, Employee $employee): RedirectResponse
    {
        $bonus = $this->bonuses->give($request->user(), [...$request->bonusData(), 'employee_id' => $employee->id]);
        if ($request->input('return_to') === 'day') { // given from the schedule's day view
            return redirect()->route('schedule.day', ['date' => $bonus->business_date->toDateString()])->with('status', 'Bonus of '.Money::format($bonus->amount)." recorded for {$employee->full_name}.");
        }

        return redirect()->route('employees.show', $employee)->with('status', 'Bonus of '.Money::format($bonus->amount).' recorded.');
    }

    public function updateBonus(BonusRequest $request, EmployeeBonus $bonus): RedirectResponse
    {
        $this->bonuses->edit($bonus, $request->user(), ['employee_id' => $bonus->employee_id, ...$request->bonusData()]);

        return redirect()->route('employees.show', $bonus->employee_id)->with('status', 'Bonus changed. The old one stays in the history.');
    }

    public function voidBonus(ReasonRequest $request, EmployeeBonus $bonus): RedirectResponse
    {
        $this->bonuses->void($bonus, $request->user(), $request->validated('reason'));

        return redirect()->route('employees.show', $bonus->employee_id)->with('status', 'Bonus voided.');
    }

    /** Active logins not linked to another employee, plus this employee's own. @return array<int, string> */
    private function logins(?Employee $employee): array
    {
        $taken = Employee::withTrashed()->whereNotNull('user_id')->when($employee, fn ($q) => $q->whereKeyNot($employee->id))->pluck('user_id');

        return User::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $employee?->user_id)) // keep a deactivated linked login
            ->whereNotIn('id', $taken)->orderBy('name')->pluck('name', 'id')->all();
    }
}

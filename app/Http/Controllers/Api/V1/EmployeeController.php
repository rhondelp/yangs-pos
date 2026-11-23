<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BonusRequest;
use App\Http\Requests\Staff\EmployeeRequest;
use App\Http\Requests\Staff\RateRequest;
use App\Http\Requests\Staff\ReasonRequest;
use App\Http\Resources\V1\EmployeeBonusResource;
use App\Http\Resources\V1\EmployeeRateResource;
use App\Http\Resources\V1\EmployeeResource;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeRate;
use App\Services\Staff\BonusService;
use App\Services\Staff\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Authorization: routes can:employees.view (list, show) and can:employees.manage (writes, rates, bonuses).
class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $employees, private BonusService $bonuses) {}

    /** ?status=active|on_leave|resigned|terminated */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:active,on_leave,resigned,terminated']]);

        return EmployeeResource::collection(Employee::when($request->query('status'), fn ($q, $s) => $q->where('employment_status', $s))->orderBy('full_name')->get());
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee);
    }

    /** Needs pay_type and rate: the first rate starts on date_hired. */
    public function store(EmployeeRequest $request): JsonResponse
    {
        return (new EmployeeResource($this->employees->create($request->user(), $request->employeeData())))->response()->setStatusCode(201);
    }

    public function update(EmployeeRequest $request, Employee $employee): EmployeeResource
    {
        return new EmployeeResource($this->employees->update($employee, $request->user(), $request->employeeData()));
    }

    public function archive(Request $request, Employee $employee): EmployeeResource
    {
        $this->employees->archive($employee, $request->user());

        return new EmployeeResource($employee);
    }

    public function restore(Request $request, Employee $employee): EmployeeResource
    {
        $this->employees->restore($employee, $request->user());

        return new EmployeeResource($employee);
    }

    /** Every version, newest first; replaced ones are history. */
    public function rates(Employee $employee): AnonymousResourceCollection
    {
        return EmployeeRateResource::collection($employee->rates()->with('createdBy')->orderByDesc('effective_from')->orderByDesc('id')->get());
    }

    public function storeRate(RateRequest $request, Employee $employee): JsonResponse
    {
        return (new EmployeeRateResource($this->employees->addRate($employee, $request->user(), $request->rateData())))->response()->setStatusCode(201);
    }

    /** Keeps the old version: the result replaces $rate. */
    public function updateRate(RateRequest $request, EmployeeRate $rate): JsonResponse
    {
        return (new EmployeeRateResource($this->employees->editRate($rate, $request->user(), $request->rateData())))->response()->setStatusCode(200);
    }

    public function bonuses(Employee $employee): AnonymousResourceCollection
    {
        return EmployeeBonusResource::collection($employee->bonuses()->with('employee', 'givenBy')->orderByDesc('business_date')->orderByDesc('id')->get());
    }

    public function storeBonus(BonusRequest $request, Employee $employee): JsonResponse
    {
        $bonus = $this->bonuses->give($request->user(), [...$request->bonusData(), 'employee_id' => $employee->id]);

        return (new EmployeeBonusResource($bonus))->response()->setStatusCode(201);
    }

    /** Voids $bonus and records the corrected one (employee_id defaults to the same person). */
    public function updateBonus(BonusRequest $request, EmployeeBonus $bonus): JsonResponse
    {
        $new = $this->bonuses->edit($bonus, $request->user(), ['employee_id' => $bonus->employee_id, ...$request->bonusData()]);

        return (new EmployeeBonusResource($new))->response()->setStatusCode(200); // a new version, but the edit itself is a PUT
    }

    public function voidBonus(ReasonRequest $request, EmployeeBonus $bonus): EmployeeBonusResource
    {
        return new EmployeeBonusResource($this->bonuses->void($bonus, $request->user(), $request->validated('reason')));
    }
}

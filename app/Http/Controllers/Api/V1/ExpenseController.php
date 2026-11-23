<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ExpenseFilterRequest;
use App\Http\Requests\Finance\RecordExpenseRequest;
use App\Http\Requests\Finance\VoidExpenseRequest;
use App\Http\Resources\V1\ExpenseCategoryResource;
use App\Http\Resources\V1\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Finance\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Authorization: routes can:expenses.view (list, show, categories) and can:expenses.manage (record, void).
// The receipt route uses Web\ExpenseController::attachment, so both share one response and its headers.
class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    /** Paginated expenses plus meta.totals: expenses (voided excluded) and inventory_purchases (stock-in, separate). */
    public function index(ExpenseFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();

        return ExpenseResource::collection($this->expenses->list($filters))->additional(['meta' => ['totals' => $this->expenses->totals($filters)]]);
    }

    public function show(Expense $expense): ExpenseResource
    {
        return new ExpenseResource($expense->load('category', 'recordedBy', 'voidedBy'));
    }

    /** Multipart when a receipt is attached. */
    public function store(RecordExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->record($request->user(), $request->expenseData(), $request->file('attachment'));

        return (new ExpenseResource($expense->load('category', 'recordedBy', 'voidedBy')))->response()->setStatusCode(201);
    }

    public function void(VoidExpenseRequest $request, Expense $expense): ExpenseResource
    {
        return new ExpenseResource($this->expenses->void($expense, $request->user(), $request->validated('reason'))->load('category', 'recordedBy', 'voidedBy'));
    }

    public function categories(): AnonymousResourceCollection
    {
        return ExpenseCategoryResource::collection(ExpenseCategory::orderBy('id')->get());
    }
}

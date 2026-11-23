<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ExpenseFilterRequest;
use App\Http\Requests\Finance\RecordExpenseRequest;
use App\Http\Requests\Finance\VoidExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Finance\ExpenseService;
use App\Support\BusinessDate;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Authorization: routes can:expenses.view (list, page, receipt) and can:expenses.manage (record, void).
class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(ExpenseFilterRequest $request): View
    {
        $filters = $request->filters();

        return view('expenses.index', [
            'filters' => $filters,
            'totals' => $this->expenses->totals($filters),
            'expenses' => $this->expenses->list($filters),
            'categories' => ExpenseCategory::orderBy('id')->pluck('name', 'id')->all(),
        ]);
    }

    /** ?from={id} prefills the form from a voided expense ("Record again"). */
    public function create(Request $request): View
    {
        $source = Expense::whereKey($request->integer('from'))->whereNotNull('voided_at')->first();

        return view('expenses.create', [
            'categories' => ExpenseCategory::active()->orderBy('id')->pluck('name', 'id')->all(),
            'prefill' => [
                'expense_category_id' => $source?->expense_category_id,
                'description' => $source?->description,
                'amount' => $source?->amount,
                'expense_date' => $source?->expense_date->toDateString() ?? BusinessDate::for(now()),
            ],
        ]);
    }

    public function store(RecordExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->record($request->user(), $request->expenseData(), $request->file('attachment')); // errors go back to the form

        return redirect()->route('expenses.show', $expense)->with('status', 'Expense of '.Money::format($expense->amount).' recorded.');
    }

    public function show(Expense $expense): View
    {
        return view('expenses.show', ['expense' => $expense->load('category', 'recordedBy', 'voidedBy')]);
    }

    public function void(VoidExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->void($expense, $request->user(), $request->validated('reason'));

        return redirect()->route('expenses.show', $expense)->with('status', 'Expense voided.');
    }

    /** The receipt from the private disk; never a public URL. */
    public function attachment(Expense $expense): StreamedResponse
    {
        abort_if($expense->attachment_path === null || ! Storage::disk(ExpenseService::DISK)->exists($expense->attachment_path), 404);

        return Storage::disk(ExpenseService::DISK)->response($expense->attachment_path, $expense->attachment_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}

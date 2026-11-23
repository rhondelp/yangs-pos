<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceFilterRequest;
use App\Services\Finance\FinancialSummaryService;
use Illuminate\View\View;

// Authorization: route can:finance.view.
class FinanceController extends Controller
{
    public function __construct(private FinancialSummaryService $finance) {}

    public function index(FinanceFilterRequest $request): View
    {
        $filters = $request->filters();

        return view('finance.index', [  
            'filters' => $filters,
            'summary' => $this->finance->summary($filters['from'], $filters['to']),
            'breakdown' => $this->finance->breakdown($filters['from'], $filters['to'], $filters['by']),
        ]);
    }
}

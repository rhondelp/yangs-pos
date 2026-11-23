<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceFilterRequest;
use App\Http\Resources\V1\FinanceReportResource;
use App\Services\Finance\FinancialSummaryService;

// Authorization: routes can:finance.view.
class FinanceController extends Controller
{
    public function __construct(private FinancialSummaryService $finance) {}

    public function summary(FinanceFilterRequest $request): FinanceReportResource
    {
        ['from' => $from, 'to' => $to] = $request->filters();

        return new FinanceReportResource([...$this->finance->summary($from, $to), 'from' => $from, 'to' => $to]);
    }

    /** ?by=day|week|month|year|product|category|cashier|payment_method (default day). */
    public function breakdown(FinanceFilterRequest $request): FinanceReportResource
    {
        ['from' => $from, 'to' => $to, 'by' => $by] = $request->filters();

        return new FinanceReportResource(['by' => $by, 'from' => $from, 'to' => $to, ...$this->finance->breakdown($from, $to, $by)]);
    }
}

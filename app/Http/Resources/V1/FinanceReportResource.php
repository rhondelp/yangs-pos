<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A P&L summary or breakdown from FinancialSummaryService, as built there. Money is strings with 2 decimals. */
class FinanceReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

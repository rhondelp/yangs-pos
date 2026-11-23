<?php

namespace App\Http\Requests\Finance;

use App\Services\Finance\FinancialSummaryService;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Authorization: route can:finance.view.
class FinanceFilterRequest extends FormRequest
{
    private const MAX_DAYS = 1826; // 5 years

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'by' => ['nullable', Rule::in(FinancialSummaryService::BREAKDOWNS)],
        ];
    }

    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The end date cannot be before the start date.',
            'by.in' => 'Choose day, week, month, year, product, category, cashier or payment method.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            ['from' => $from, 'to' => $to] = $this->range($this->input('from'), $this->input('to'));
            $field = $this->filled('to') ? 'to' : 'from'; // name the date the caller gave
            if ($from > $to) { // only when "to" defaulted to today; otherwise after_or_equal caught it
                $validator->errors()->add('from', 'The start date cannot be after the end date.');
            } elseif (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > self::MAX_DAYS) {
                $validator->errors()->add($field, 'Choose a range of 5 years or less.');
            }
        }];
    }

    /** Defaults to this month so far, by business date; an end date alone starts at the first of its month. @return array{from: string, to: string, by: string} */
    public function filters(): array
    {
        return [...$this->range($this->validated('from'), $this->validated('to')), 'by' => $this->validated('by') ?? 'day'];
    }

    /** @return array{from: string, to: string} */
    private function range(?string $from, ?string $to): array
    {
        $to ??= BusinessDate::for(now());

        return ['from' => $from ?? substr($to, 0, 8).'01', 'to' => $to];
    }
}

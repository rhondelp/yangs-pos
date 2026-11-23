<?php

namespace App\Http\Requests\Finance;

use App\Support\BusinessDate;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:expenses.view.
class ExpenseFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'voided' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['to.after_or_equal' => 'The end date cannot be before the start date.'];
    }

    /** Defaults to this month so far, by business date; an end date alone starts at the first of its month. @return array{from: string, to: string, category_id: ?int, voided: bool} */
    public function filters(): array
    {
        $to = $this->validated('to') ?? BusinessDate::for(now());

        return [
            'from' => $this->validated('from') ?? substr($to, 0, 8).'01',
            'to' => $to,
            'category_id' => $this->validated('category_id') === null ? null : (int) $this->validated('category_id'),
            'voided' => $this->boolean('voided'),
        ];
    }
}

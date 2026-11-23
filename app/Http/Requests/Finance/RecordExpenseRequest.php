<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:expenses.manage. Amount, date, category and file rules live in ExpenseService::record.
class RecordExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required'], // a string or a JSON number; ExpenseService checks the format
            'expense_date' => ['required', 'string', 'max:10'],
            'attachment' => ['nullable', 'file'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Choose a category.',
            'description.required' => 'Say what the expense was for.',
            'amount.required' => 'Enter the amount.',
            'expense_date.required' => 'Enter the date.',
            'attachment.uploaded' => 'The receipt could not be uploaded. It may be larger than the server allows; keep it under 5 MB.',
        ];
    }

    /** @return array{expense_category_id: mixed, description: mixed, amount: mixed, expense_date: mixed} */
    public function expenseData(): array
    {
        $data = $this->safe()->except('attachment');
        $data['amount'] = is_int($data['amount']) || is_float($data['amount']) ? (string) $data['amount'] : $data['amount'];

        return $data;
    }
}

<?php

namespace App\Http\Requests\Corrections;

use App\Support\BusinessDate;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:orders.view-own; TransactionController narrows to own tabs without orders.view-all.
class TransactionFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['date' => ['nullable', 'date_format:Y-m-d']];
    }

    /** The business day to list; today's by default. */
    public function businessDate(): string
    {
        return $this->validated('date') ?? BusinessDate::for(now());
    }
}

<?php

namespace App\Http\Requests\Corrections;

use App\Enums\PaymentStatus;
use App\Support\BusinessDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Authorization: route can:orders.view-own; TransactionController narrows to own tabs without orders.view-all.
class TransactionFilterRequest extends FormRequest
{
    /** Payment statuses a closed tab can have. */
    public const STATUSES = [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'table_id' => ['nullable', 'integer'],
            'cashier_id' => ['nullable', 'integer'],
            'payment_status' => ['nullable', Rule::in(array_map(fn (PaymentStatus $s) => $s->value, self::STATUSES))],
            'method_id' => ['nullable', 'integer'],
        ];
    }

    /** The business day to list; today's by default. */
    public function businessDate(): string
    {
        return $this->validated('date') ?? BusinessDate::for(now());
    }

    /** @return array{table_id: ?int, cashier_id: ?int, payment_status: ?string, method_id: ?int} */
    public function filters(): array
    {
        $int = fn (string $key) => $this->validated($key) === null ? null : (int) $this->validated($key);

        return [
            'table_id' => $int('table_id'),
            'cashier_id' => $int('cashier_id'),
            'payment_status' => $this->validated('payment_status'),
            'method_id' => $int('method_id'),
        ];
    }
}

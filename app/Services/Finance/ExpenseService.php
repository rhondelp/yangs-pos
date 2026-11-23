<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\BusinessDate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Operating expenses (ARCHITECTURE §3.6). The only writer of `expenses`: record once, void once, never edit or delete.
 * Stock purchases are not expenses; they are stock-in, shown beside the expense total as "Inventory purchases".
 */
class ExpenseService
{
    public const DISK = 'local'; // storage/app/private: never served without a permission check

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @param  array{expense_category_id: mixed, description: mixed, amount: mixed, expense_date: mixed}  $input
     *
     * @throws AuthorizationException without expenses.manage
     * @throws ValidationException keyed by the input names and `attachment`
     */
    public function record(User $by, array $input, ?UploadedFile $attachment = null): Expense
    {
        if (! $by->can('expenses.manage')) {
            throw new AuthorizationException('You cannot record expenses.');
        }
        $input['description'] = trim((string) ($input['description'] ?? ''));
        $data = Validator::make([...$input, 'attachment' => $attachment], [
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BusinessDate::for(now())],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:5120'], // content and name
        ], [
            'expense_category_id.exists' => 'Choose a category that is in use.',
            'amount.regex' => 'Enter the amount in pesos, up to 2 decimals.',
            'amount.not_regex' => 'The amount must be more than ₱0.00.',
            'expense_date.before_or_equal' => 'The date cannot be after today.',
            'attachment.mimes' => 'Attach a photo (JPG, PNG, WebP) or a PDF.',
            'attachment.extensions' => 'Attach a photo (JPG, PNG, WebP) or a PDF.',
            'attachment.uploaded' => 'The receipt could not be uploaded. It may be larger than the server allows; keep it under 5 MB.',
            'attachment.max' => 'The receipt may not be larger than 5 MB.',
        ])->validate();

        // The file is stored first, so a failed save must remove it again.
        $path = $attachment?->store('expenses/'.str_replace('-', '/', substr($data['expense_date'], 0, 7)), self::DISK);
        if ($path === false) { // the local disk reports a failed write (disk full, no permission) as false
            throw ValidationException::withMessages(['attachment' => 'The receipt could not be saved. Try again.']);
        }
        try {
            return DB::transaction(function () use ($by, $data, $attachment, $path) {
                $expense = Expense::forceCreate([
                    'expense_category_id' => (int) $data['expense_category_id'],
                    'description' => $data['description'],
                    'amount' => $data['amount'],
                    'expense_date' => $data['expense_date'],
                    'attachment_path' => $path,
                    'attachment_name' => $attachment ? mb_substr($attachment->getClientOriginalName(), 0, 255) : null,
                    'recorded_by' => $by->id,
                ])->fresh();
                $this->logger->log('expense.recorded', $expense, [], [
                    'category' => ExpenseCategory::whereKey($expense->expense_category_id)->value('name'),
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                    'expense_date' => $data['expense_date'],
                    'attachment' => $path !== null,
                ], $by);

                return $expense;
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $e;
        }
    }

    /**
     * @throws AuthorizationException without expenses.manage
     * @throws ValidationException on `reason` (missing, too long, or already voided)
     */
    public function void(Expense $expense, User $by, ?string $reason): Expense
    {
        if (! $by->can('expenses.manage')) {
            throw new AuthorizationException('You cannot void expenses.');
        }
        $reason = trim((string) $reason);
        if ($reason === '' || mb_strlen($reason) > 300) {
            throw ValidationException::withMessages(['reason' => 'Say why this expense is voided (up to 300 characters).']);
        }

        return DB::transaction(function () use ($expense, $by, $reason) {
            $locked = Expense::whereKey($expense->getKey())->lockForUpdate()->firstOrFail(); // two voids at once: one wins
            if ($locked->isVoided()) {
                throw ValidationException::withMessages(['reason' => 'This expense is already voided.']);
            }
            $locked->forceFill(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason])->save();
            $this->logger->log('expense.voided', $locked, [], ['reason' => $reason, 'amount' => $locked->amount], $by);

            return $locked;
        });
    }

    /**
     * Expense total (voided ones never count) and, separately, stock-in purchases by business date in the same range.
     *
     * @param  array{from: string, to: string, category_id: ?int, voided: bool}  $filters
     * @return array{expenses: string, inventory_purchases: string}
     */
    public function totals(array $filters): array
    {
        $expenses = $this->query($filters)->whereNull('voided_at')->sum('amount');
        $purchases = DB::table('stock_receipts')->whereBetween('business_date', [$filters['from'], $filters['to']])->sum('total_cost');

        return ['expenses' => bcadd((string) $expenses, '0', 2), 'inventory_purchases' => bcadd((string) $purchases, '0', 2)];
    }

    /** @param  array{from: string, to: string, category_id: ?int, voided: bool}  $filters */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->unless($filters['voided'], fn (Builder $q) => $q->whereNull('voided_at'))
            ->with('category', 'recordedBy', 'voidedBy')
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();
    }

    private function query(array $filters): Builder
    {
        return Expense::query()
            ->whereBetween('expense_date', [$filters['from'], $filters['to']])
            ->when($filters['category_id'], fn (Builder $q, int $id) => $q->where('expense_category_id', $id));
    }
}

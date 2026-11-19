<?php

namespace App\Services\Billing;

use App\Models\PaymentMethod;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Owner-managed payment methods. New ones are never cash: change-giving belongs to the seeded Cash method. */
class PaymentMethodService
{
    private const FIELDS = ['name', 'requires_reference', 'is_active', 'sort_order'];

    public function __construct(private ActivityLogger $logger) {}

    /** @param array{name: string, requires_reference?: bool, is_active?: bool, sort_order?: int} $data */
    public function create(array $data): PaymentMethod
    {
        return DB::transaction(function () use ($data) {
            $method = new PaymentMethod(['requires_reference' => false, 'is_active' => true, 'sort_order' => 0, ...Arr::only($data, self::FIELDS)]);
            $method->forceFill(['code' => $this->uniqueCode($method->name), 'is_cash' => false])->save();
            $this->logger->log('payment_method.created', $method, new: $method->only(self::FIELDS));

            return $method;
        });
    }

    /**
     * @param  array{name?: string, requires_reference?: bool, is_active?: bool, sort_order?: int}  $data
     *
     * @throws ValidationException when it would switch off the last active method
     */
    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        return DB::transaction(function () use ($method, $data) {
            // Lock every method so two owners cannot each switch off one of the last two.
            PaymentMethod::lockForUpdate()->get(['id']);
            $method->fill(Arr::only($data, self::FIELDS));

            if ($method->isDirty('is_active') && ! $method->is_active
                && ! PaymentMethod::whereKeyNot($method->getKey())->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Keep at least one payment method switched on.']);
            }

            $changed = array_keys($method->getDirty());
            $old = array_intersect_key($method->getOriginal(), array_flip($changed));
            $method->save();

            if ($changed !== []) {
                $this->logger->log('payment_method.updated', $method, $old, $method->only($changed));
            }

            return $method;
        });
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::limit(Str::slug($name), 36, '') ?: 'method';
        $code = $base;
        for ($i = 2; PaymentMethod::where('code', $code)->exists(); $i++) {
            $code = "{$base}-{$i}";
        }

        return $code;
    }
}

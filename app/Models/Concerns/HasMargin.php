<?php

namespace App\Models\Concerns;

use App\Support\Money;

/** Gross margin for models with decimal `price` and nullable `cost` columns. */
trait HasMargin
{
    /** @return array{amount: ?string, percent: ?string} amount = price − cost; percent of price, 2 dp half-up */
    public function margin(): array
    {
        if ($this->cost === null || $this->price === null) {
            return ['amount' => null, 'percent' => null];
        }

        $amount = Money::sub((string) $this->price, (string) $this->cost);

        return [
            'amount' => $amount,
            'percent' => bccomp((string) $this->price, '0', 2) === 0 ? null : Money::percentOf($amount, (string) $this->price),
        ];
    }
}

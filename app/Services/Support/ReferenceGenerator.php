<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;

/**
 * Daily document numbers such as ORD-20261118-0001 and RCP-20261118-0001.
 * Call inside the caller's transaction so a rolled-back document does not use up a number.
 */
class ReferenceGenerator
{
    /** One atomic upsert, so concurrent requests never get the same number. */
    public function next(string $prefix, string $businessDate): string
    {
        $row = DB::selectOne(
            'INSERT INTO reference_counters (prefix, business_date, last_number, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())
             ON CONFLICT (prefix, business_date) DO UPDATE SET last_number = reference_counters.last_number + 1, updated_at = NOW()
             RETURNING last_number',
            [$prefix, $businessDate],
        );

        return sprintf('%s-%s-%04d', $prefix, str_replace('-', '', $businessDate), $row->last_number);
    }
}

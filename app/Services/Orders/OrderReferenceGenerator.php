<?php

namespace App\Services\Orders;

use Illuminate\Support\Facades\DB;

class OrderReferenceGenerator
{
    /** One atomic upsert, so concurrent requests never get the same number. */
    public function next(string $businessDate): string
    {
        $row = DB::selectOne(
            'INSERT INTO order_reference_counters (business_date, last_number, created_at, updated_at) VALUES (?, 1, NOW(), NOW())
             ON CONFLICT (business_date) DO UPDATE SET last_number = order_reference_counters.last_number + 1, updated_at = NOW()
             RETURNING last_number',
            [$businessDate],
        );

        return sprintf('ORD-%s-%04d', str_replace('-', '', $businessDate), $row->last_number);
    }
}

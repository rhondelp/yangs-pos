<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Production-safe: adds Cash and GCash if missing; never changes a method the owner has edited.
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('payment_methods')->insertOrIgnore([
            ['code' => 'cash', 'name' => 'Cash', 'is_cash' => true, 'requires_reference' => false, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'gcash', 'name' => 'GCash', 'is_cash' => false, 'requires_reference' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// One counter table for every daily number (ORD orders, RCP receipts, ...). Existing order counters keep their values.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE order_reference_counters RENAME TO reference_counters');
        DB::statement("ALTER TABLE reference_counters ADD COLUMN prefix varchar(10) NOT NULL DEFAULT 'ORD'");
        DB::statement('ALTER TABLE reference_counters DROP CONSTRAINT order_reference_counters_pkey');
        DB::statement('ALTER TABLE reference_counters ADD PRIMARY KEY (prefix, business_date)');
        DB::statement('ALTER TABLE reference_counters ALTER COLUMN prefix DROP DEFAULT');
    }

    public function down(): void
    {
        DB::statement("DELETE FROM reference_counters WHERE prefix <> 'ORD'");
        DB::statement('ALTER TABLE reference_counters DROP CONSTRAINT reference_counters_pkey');
        DB::statement('ALTER TABLE reference_counters DROP COLUMN prefix');
        DB::statement('ALTER TABLE reference_counters ADD CONSTRAINT order_reference_counters_pkey PRIMARY KEY (business_date)');
        DB::statement('ALTER TABLE reference_counters RENAME TO order_reference_counters');
    }
};

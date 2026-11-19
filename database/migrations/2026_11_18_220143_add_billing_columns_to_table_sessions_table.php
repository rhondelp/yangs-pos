<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            // Bill-level discount: a percent follows the bill until it is paid; an amount stays fixed.
            $table->string('bill_discount_type', 10)->nullable();
            $table->decimal('bill_discount_value', 12, 2)->nullable();
            $table->string('bill_discount_reason', 200)->nullable();
            $table->foreignId('bill_discount_by')->nullable()->constrained('users');
            $table->timestampTz('billed_at')->nullable();
            // Frozen when the tab closes, for reports.
            $table->decimal('gross_total', 12, 2)->nullable();
            $table->decimal('discount_total', 12, 2)->nullable();
            $table->decimal('net_total', 12, 2)->nullable();
            $table->string('receipt_number', 30)->nullable()->unique();
        });

        DB::statement("ALTER TABLE table_sessions ADD CONSTRAINT table_sessions_bill_discount_check CHECK (
            (bill_discount_type IS NULL AND bill_discount_value IS NULL)
            OR (bill_discount_type = 'amount' AND bill_discount_value > 0)
            OR (bill_discount_type = 'percent' AND bill_discount_value > 0 AND bill_discount_value <= 100))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE table_sessions DROP CONSTRAINT table_sessions_bill_discount_check');
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_discount_by');
            $table->dropUnique(['receipt_number']);
            $table->dropColumn(['bill_discount_type', 'bill_discount_value', 'bill_discount_reason', 'billed_at', 'gross_total', 'discount_total', 'net_total', 'receipt_number']);
        });
    }
};

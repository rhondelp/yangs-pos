<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Append-only: a wrong payment is corrected by a later refund or void record, never by an edit.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained('table_sessions');
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->decimal('amount', 12, 2); // applied to the bill
            $table->decimal('tendered', 12, 2)->nullable(); // cash handed over
            $table->decimal('change_given', 12, 2)->default(0);
            $table->string('reference_no', 60)->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('received_by')->constrained('users');
            $table->timestampTz('paid_at');
            $table->date('business_date');
            $table->timestampsTz();
            $table->index('table_session_id');
            $table->index('business_date');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_change_consistent CHECK (change_given >= 0 AND (tendered IS NULL OR tendered = amount + change_given))');
        // reject_append_only_change() is created by the activity_logs migration.
        DB::unprepared('CREATE TRIGGER payments_append_only BEFORE UPDATE OR DELETE ON payments FOR EACH ROW EXECUTE FUNCTION reject_append_only_change()');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Append-only: money handed back on a closed tab, always against one payment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained('table_sessions');
            $table->foreignId('payment_id')->constrained('payments');
            $table->foreignId('payment_method_id')->constrained('payment_methods'); // how the money went back
            $table->decimal('amount', 12, 2);
            $table->string('reference_no', 60)->nullable();
            $table->string('reason', 200);
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('refunded_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('business_date');
            $table->timestampsTz();
            $table->index('table_session_id');
            $table->index('payment_id');
            $table->index('business_date');
        });

        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_positive CHECK (amount > 0)');
        DB::unprepared('CREATE TRIGGER refunds_append_only BEFORE UPDATE OR DELETE ON refunds FOR EACH ROW EXECUTE FUNCTION reject_append_only_change()');
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

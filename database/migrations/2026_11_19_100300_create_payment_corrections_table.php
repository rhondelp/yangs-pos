<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Append-only: a payment recorded with the wrong method or reference. The payment row stays as recorded; the latest correction wins.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments');
            $table->foreignId('table_session_id')->constrained('table_sessions');
            $table->foreignId('old_payment_method_id')->constrained('payment_methods');
            $table->foreignId('new_payment_method_id')->constrained('payment_methods');
            $table->string('old_reference_no', 60)->nullable();
            $table->string('new_reference_no', 60)->nullable();
            $table->string('reason', 200);
            $table->foreignId('corrected_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestampsTz();
            $table->index('payment_id');
        });

        DB::unprepared('CREATE TRIGGER payment_corrections_append_only BEFORE UPDATE OR DELETE ON payment_corrections FOR EACH ROW EXECUTE FUNCTION reject_append_only_change()');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_corrections');
    }
};

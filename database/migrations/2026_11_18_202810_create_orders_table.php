<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('table_session_id')->constrained('table_sessions');
            $table->foreignId('created_by')->constrained('users');
            $table->string('status', 20)->default('open');
            $table->string('payment_status', 20)->default('unpaid');
            $table->date('business_date');
            $table->string('notes', 500)->nullable();
            $table->timestampsTz();
            $table->index('table_session_id');
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('open', 'cancelled'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('unpaid', 'partial', 'paid'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

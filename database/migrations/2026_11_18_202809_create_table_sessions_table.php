<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_table_id')->constrained('dining_tables');
            $table->string('status', 20)->default('open'); // written only by the tab service
            $table->date('business_date');
            $table->foreignId('opened_by')->constrained('users');
            $table->timestampTz('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestampTz('closed_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestampsTz();
            $table->index('business_date');
        });

        DB::statement("ALTER TABLE table_sessions ADD CONSTRAINT table_sessions_status_check CHECK (status IN ('open', 'billing', 'closed', 'cancelled'))");
        DB::statement("CREATE UNIQUE INDEX table_sessions_one_open_per_table ON table_sessions (dining_table_id) WHERE status IN ('open', 'billing')");
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};

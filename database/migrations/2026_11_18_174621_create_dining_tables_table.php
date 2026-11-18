<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('label', 30); // "T1", "Bar 2"
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status', 20)->default('available'); // written only by TableStatusService
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement('CREATE UNIQUE INDEX dining_tables_label_unique ON dining_tables (lower(label))');
        DB::statement("ALTER TABLE dining_tables ADD CONSTRAINT dining_tables_status_check CHECK (status IN ('available', 'occupied', 'awaiting_payment', 'reserved', 'cleaning'))");
        DB::statement('ALTER TABLE dining_tables ADD CONSTRAINT dining_tables_capacity_check CHECK (capacity IS NULL OR capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};

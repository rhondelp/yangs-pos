<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reference_counters', function (Blueprint $table) {
            $table->date('business_date')->primary();
            $table->unsignedInteger('last_number');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reference_counters');
    }
};

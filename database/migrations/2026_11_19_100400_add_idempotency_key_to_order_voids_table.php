<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A resent void request (lost reply on tablet Wi-Fi) returns the first void instead of voiding again.
// Not unique: a whole-order void writes one row per item under the same key.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_voids', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('order_voids', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};

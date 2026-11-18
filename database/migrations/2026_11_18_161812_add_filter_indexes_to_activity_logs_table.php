<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The log viewer filters by user and action; the table grows with every business action.
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['action']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Usernames are stored lowercase (see User::username()), so this index is case-insensitive in practice.
            $table->string('username', 50)->unique()->after('name');
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'is_active']);
            $table->string('email')->nullable(false)->change();
        });
    }
};

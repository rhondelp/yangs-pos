<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Names are unique among active categories, ignoring case; an archived name can be reused.
        DB::statement('CREATE UNIQUE INDEX categories_name_active_unique ON categories (lower(name)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

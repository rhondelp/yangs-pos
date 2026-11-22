<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Optional grouping, reusing the product categories (one list to maintain).
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->index('category_id'); // PostgreSQL does not index foreign keys by itself.
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            // Set when the movement is written, against that day's threshold; movements are never updated.
            $table->boolean('is_unusual')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->dropColumn('is_unusual'));
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
    }
};

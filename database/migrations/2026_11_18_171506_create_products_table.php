<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('type', 20)->default('drink');
            $table->decimal('price', 12, 2);
            $table->decimal('cost', 12, 2)->nullable(); // cost of one sold unit; null = unknown
            $table->string('image_path')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('track_inventory')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['category_id', 'deleted_at']);
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_type_check CHECK (type IN ('drink', 'food', 'other'))");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_check CHECK (price >= 0 AND (cost IS NULL OR cost >= 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

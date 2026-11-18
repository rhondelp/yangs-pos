<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('name', 80); // e.g. "Bucket of 6", "Large"
            $table->decimal('price', 12, 2);
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_price_check CHECK (price >= 0 AND (cost IS NULL OR cost >= 0))');
        DB::statement('CREATE UNIQUE INDEX product_variants_name_active_unique ON product_variants (product_id, lower(name)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

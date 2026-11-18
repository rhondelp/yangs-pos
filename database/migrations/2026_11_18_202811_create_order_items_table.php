<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            // Snapshots: later price or cost changes never alter history.
            $table->string('product_name', 120);
            $table->string('variant_name', 80)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->string('status', 20)->default('active');
            $table->string('cancel_reason', 200)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('notes', 200)->nullable();
            $table->timestampsTz();

            $table->index(['order_id', 'status']);
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_discount_check CHECK (discount >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_total_check CHECK (line_total >= 0)');
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_status_check CHECK (status IN ('active', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

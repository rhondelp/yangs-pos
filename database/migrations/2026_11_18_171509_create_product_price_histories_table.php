<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->string('field', 10); // price | cost
            $table->decimal('old_value', 12, 2)->nullable(); // null on the first record
            $table->decimal('new_value', 12, 2)->nullable(); // null when a cost is cleared
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });

        DB::statement("ALTER TABLE product_price_histories ADD CONSTRAINT product_price_histories_field_check CHECK (field IN ('price', 'cost'))");
        // reject_append_only_change() is created by the activity_logs migration.
        DB::unprepared('CREATE TRIGGER product_price_histories_append_only BEFORE UPDATE OR DELETE ON product_price_histories FOR EACH ROW EXECUTE FUNCTION reject_append_only_change()');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
    }
};

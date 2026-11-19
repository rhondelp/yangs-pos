<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Append-only: units taken off a paid-into tab. The order_items row is never changed; the bill subtracts these.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_voids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained('table_sessions');
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('order_item_id')->constrained('order_items');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('amount', 12, 2); // this void's share of the line total, for reports
            $table->string('reason', 200);
            $table->foreignId('voided_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('business_date');
            $table->timestampsTz();
            $table->index('table_session_id');
            $table->index('order_item_id');
            $table->index('business_date');
        });

        DB::statement('ALTER TABLE order_voids ADD CONSTRAINT order_voids_quantity_positive CHECK (quantity > 0 AND amount >= 0)');
        DB::unprepared('CREATE TRIGGER order_voids_append_only BEFORE UPDATE OR DELETE ON order_voids FOR EACH ROW EXECUTE FUNCTION reject_append_only_change()');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_voids');
    }
};

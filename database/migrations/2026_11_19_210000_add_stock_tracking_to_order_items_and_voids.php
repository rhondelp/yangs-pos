<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sale deduction (Phase 3). stock_components snapshots what one unit of the line took from stock at sale time
 * ([{inventory_item_id, quantity, unit_cost}]), so returns put back exactly that, whatever the recipe says later.
 * returned_to_stock records the "Put back in stock?" answer of a cancel or void; false = an inventory loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->jsonb('stock_components')->nullable(); // null = the line does not track stock
            $table->boolean('returned_to_stock')->nullable(); // set on cancelled lines that tracked stock
        });
        Schema::table('order_voids', function (Blueprint $table) {
            $table->boolean('returned_to_stock')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('order_voids', fn (Blueprint $table) => $table->dropColumn('returned_to_stock'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['stock_components', 'returned_to_stock']));
    }
};

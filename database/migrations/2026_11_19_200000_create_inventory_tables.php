<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 3 inventory (docs/ARCHITECTURE.md §2, §3.4–3.5). Quantities are decimal(14,3) base units, per-unit costs
 * decimal(14,6), money totals decimal(12,2). on_hand and avg_unit_cost are written only by InventoryService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
        DB::statement('CREATE UNIQUE INDEX suppliers_name_active_unique ON suppliers (lower(name)) WHERE deleted_at IS NULL');

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('sku', 60)->nullable();
            $table->string('base_unit', 20); // the smallest unit counted or sold: bottle, ml, pc
            $table->decimal('on_hand', 14, 3)->default(0); // cache of the ledger sum
            $table->decimal('avg_unit_cost', 14, 6)->default(0); // per base unit, moving weighted average
            $table->decimal('low_stock_level', 14, 3)->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
        DB::statement('CREATE UNIQUE INDEX inventory_items_name_active_unique ON inventory_items (lower(name)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX inventory_items_sku_active_unique ON inventory_items (lower(sku)) WHERE deleted_at IS NULL AND sku IS NOT NULL');
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_avg_cost_not_negative CHECK (avg_unit_cost >= 0 AND low_stock_level >= 0)');

        // Other units of an item; the base unit itself is implicit with factor 1. Case = 24 bottles.
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->string('name', 30);
            $table->decimal('factor', 14, 3); // base units per one of this unit
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX inventory_units_item_name_unique ON inventory_units (inventory_item_id, lower(name))');
        DB::statement('ALTER TABLE inventory_units ADD CONSTRAINT inventory_units_factor_positive CHECK (factor > 0)');

        // What one sale of a product (or one of its options) uses. Several rows make a cocktail.
        Schema::create('recipe_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants'); // null = sold without an option
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->decimal('quantity', 14, 3); // base units per one sold
            $table->timestampsTz();
            $table->index('inventory_item_id');
        });
        DB::statement('CREATE UNIQUE INDEX recipe_components_unique ON recipe_components (product_id, COALESCE(product_variant_id, 0), inventory_item_id)');
        DB::statement('ALTER TABLE recipe_components ADD CONSTRAINT recipe_components_quantity_positive CHECK (quantity > 0)');

        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique(); // SR-YYYYMMDD-NNNN
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->string('invoice_no', 60)->nullable();
            $table->timestampTz('received_at');
            $table->date('business_date');
            $table->foreignId('received_by')->constrained('users');
            $table->decimal('total_cost', 12, 2);
            $table->string('notes', 500)->nullable();
            $table->timestampsTz();
            $table->index('business_date');
            $table->index('supplier_id');
        });

        Schema::create('stock_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained('stock_receipts');
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('inventory_unit_id')->nullable()->constrained('inventory_units'); // null = base unit
            $table->string('unit_name', 30); // snapshots: later unit edits never change history
            $table->decimal('unit_factor', 14, 3);
            $table->decimal('quantity', 14, 3); // in that unit
            $table->decimal('base_quantity', 14, 3);
            $table->decimal('line_cost', 12, 2);
            $table->decimal('base_unit_cost', 14, 6);
            $table->timestampsTz();
            // PostgreSQL does not index foreign keys by itself.
            $table->index('stock_receipt_id');
            $table->index('inventory_item_id');
            $table->index('inventory_unit_id');
        });
        DB::statement('ALTER TABLE stock_receipt_items ADD CONSTRAINT stock_receipt_items_positive CHECK (quantity > 0 AND base_quantity > 0 AND unit_factor > 0 AND line_cost >= 0)');

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->string('type', 20); // receipt | receipt_void | sale | void_return | adjustment
            $table->decimal('quantity', 14, 3); // signed, base units
            $table->decimal('unit_cost', 14, 6);
            $table->decimal('total_cost', 12, 2);
            $table->decimal('balance_after', 14, 3);
            $table->string('reason', 30)->nullable();
            $table->string('note', 300)->nullable();
            $table->nullableMorphs('source');
            $table->date('business_date');
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();
            $table->index(['inventory_item_id', 'created_at']);
            $table->index('business_date');
        });
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('receipt', 'receipt_void', 'sale', 'void_return', 'adjustment'))");
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_nonzero CHECK (quantity <> 0)');

        // History is never edited (CLAUDE.md "Inventory"); reject_append_only_change() comes from the activity_logs migration.
        foreach (['stock_receipts', 'stock_receipt_items', 'inventory_movements'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION reject_append_only_change()");
        }
    }

    public function down(): void
    {
        foreach (['inventory_movements', 'stock_receipt_items', 'stock_receipts', 'recipe_components', 'inventory_units', 'inventory_items', 'suppliers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

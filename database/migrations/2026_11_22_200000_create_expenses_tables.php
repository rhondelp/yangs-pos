<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories');
            $table->string('description', 200);
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestampsTz();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_reason', 300)->nullable();
            // PostgreSQL does not index foreign keys by itself.
            $table->index('expense_category_id');
            $table->index('expense_date');
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_void_complete CHECK ((voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL) OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL))');

        // Financial history: never deleted, never edited. The one allowed change is voiding a live expense.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION expenses_void_once() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Expenses are never deleted; void them instead';
                END IF;
                IF OLD.voided_at IS NOT NULL
                   OR (to_jsonb(NEW) - 'voided_at' - 'voided_by' - 'void_reason' - 'updated_at')
                      IS DISTINCT FROM (to_jsonb(OLD) - 'voided_at' - 'voided_by' - 'void_reason' - 'updated_at') THEN
                    RAISE EXCEPTION 'Expenses cannot be edited; void and record again';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        DB::unprepared('CREATE TRIGGER expenses_void_once BEFORE UPDATE OR DELETE ON expenses FOR EACH ROW EXECUTE FUNCTION expenses_void_once()');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        DB::unprepared('DROP FUNCTION IF EXISTS expenses_void_once()');
        Schema::dropIfExists('expense_categories');
    }
};

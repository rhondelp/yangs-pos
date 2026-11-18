<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared by every append-only table (payments, inventory movements, ...).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_append_only_change() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Table % is append-only', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('user_name')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('action', 100);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });

        DB::unprepared('CREATE TRIGGER activity_logs_append_only BEFORE UPDATE OR DELETE ON activity_logs FOR EACH ROW EXECUTE FUNCTION reject_append_only_change()');
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_append_only_change()');
    }
};

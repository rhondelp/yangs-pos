<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist'); // for the schedule overlap constraint

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('position', 60);
            $table->string('employment_status', 20)->default('active');
            $table->date('date_hired');
            $table->date('date_separated')->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users'); // optional login
            $table->timestampsTz();
            $table->softDeletesTz();
        });
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_status_check CHECK (employment_status IN ('active', 'on_leave', 'resigned', 'terminated'))");
        DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_separation_check CHECK (date_separated IS NULL OR date_separated >= date_hired)');

        // Pay history: an edit is a new row pointing at the one it replaces (payroll record, never updated).
        Schema::create('employee_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('pay_type', 10);
            $table->decimal('rate', 12, 2);
            $table->string('custom_basis', 80)->nullable();
            $table->date('effective_from');
            $table->foreignId('replaces_id')->nullable()->unique()->constrained('employee_rates');
            $table->foreignId('created_by')->constrained('users');
            $table->timestampTz('created_at')->nullable();
            $table->index(['employee_id', 'effective_from']);
        });
        DB::statement("ALTER TABLE employee_rates ADD CONSTRAINT employee_rates_check CHECK (rate > 0 AND pay_type IN ('daily', 'weekly', 'monthly', 'custom') AND (pay_type <> 'custom' OR custom_basis IS NOT NULL))");
        DB::unprepared('CREATE TRIGGER employee_rates_append_only BEFORE UPDATE OR DELETE ON employee_rates FOR EACH ROW EXECUTE FUNCTION reject_append_only_change()');

        // Night bonuses, paid in cash that night. An edit voids the old row and records a new one.
        Schema::create('employee_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('business_date');
            $table->decimal('amount', 12, 2);
            $table->string('reason', 200);
            $table->foreignId('given_by')->constrained('users');
            $table->foreignId('replaces_id')->nullable()->unique()->constrained('employee_bonuses');
            $table->timestampsTz();
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_reason', 200)->nullable();
            $table->index('employee_id');
            $table->index('business_date');
        });
        DB::statement('ALTER TABLE employee_bonuses ADD CONSTRAINT employee_bonuses_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE employee_bonuses ADD CONSTRAINT employee_bonuses_void_complete CHECK ((voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL) OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL))');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION employee_bonuses_void_once() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Bonuses are never deleted; void them instead';
                END IF;
                IF OLD.voided_at IS NOT NULL
                   OR (to_jsonb(NEW) - 'voided_at' - 'voided_by' - 'void_reason' - 'updated_at')
                      IS DISTINCT FROM (to_jsonb(OLD) - 'voided_at' - 'voided_by' - 'void_reason' - 'updated_at') THEN
                    RAISE EXCEPTION 'Bonuses cannot be edited in place; edit records a new one';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        DB::unprepared('CREATE TRIGGER employee_bonuses_void_once BEFORE UPDATE OR DELETE ON employee_bonuses FOR EACH ROW EXECUTE FUNCTION employee_bonuses_void_once()');

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->time('start_time');
            $table->time('end_time'); // earlier than or equal to start: ends the next day
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE shifts ADD CONSTRAINT shifts_times_differ CHECK (start_time <> end_time)');

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('shift_id')->nullable()->constrained('shifts'); // null = custom
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->date('duty_date'); // business date of starts_at
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampsTz();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancel_reason', 200)->nullable();
            $table->index('employee_id');
            $table->index('shift_id');
            $table->index('duty_date');
        });
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT schedules_length_check CHECK (ends_at > starts_at AND ends_at - starts_at <= interval '16 hours')");
        // One person, one place: live shifts of an employee never overlap, across midnight too. Back to back is fine.
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT schedules_no_overlap EXCLUDE USING gist (employee_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (cancelled_at IS NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('employee_bonuses');
        DB::unprepared('DROP FUNCTION IF EXISTS employee_bonuses_void_once()');
        Schema::dropIfExists('employee_rates');
        Schema::dropIfExists('employees');
    }
};

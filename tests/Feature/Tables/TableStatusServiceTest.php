<?php

namespace Tests\Feature\Tables;

use App\Enums\TableStatus as S;
use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\User;
use App\Services\Tables\DiningTableService;
use App\Services\Tables\TableStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TableStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private TableStatusService $status;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->status = app(TableStatusService::class);
        $this->table = app(DiningTableService::class)->create(['label' => 'T1', 'capacity' => 4]);
    }

    private function assertRejected(S $to, bool $system = false, string $message = ''): void
    {
        $before = $this->table->fresh()->status;
        try {
            $this->status->change($this->table, $to, $system);
            $this->fail("moved {$before->value} to {$to->value}");
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, $e->errors()['status'][0]);
        }
        $this->assertSame($before, $this->table->fresh()->status);
    }

    public function test_people_can_switch_between_available_reserved_and_cleaning(): void
    {
        foreach ([S::Reserved, S::Cleaning, S::Available, S::Cleaning, S::Reserved, S::Available] as $to) {
            $this->status->change($this->table, $to);
            $this->assertSame($to, $this->table->fresh()->status);
        }

        $logs = ActivityLog::where('action', 'table.status_changed')->orderBy('id')->get();
        $this->assertCount(6, $logs);
        $this->assertSame(['status' => 'available'], $logs[0]->old_values);
        $this->assertSame(['status' => 'reserved'], $logs[0]->new_values);
    }

    public function test_people_cannot_mark_a_table_occupied_or_awaiting_payment(): void
    {
        $this->assertRejected(S::Occupied, message: 'Open a tab');
        $this->assertRejected(S::AwaitingPayment, message: 'Open a tab');
    }

    public function test_people_cannot_free_a_table_that_has_a_tab(): void
    {
        $this->status->change($this->table, S::Occupied, system: true);

        $this->assertRejected(S::Available, message: 'has an open tab');
        $this->assertRejected(S::Cleaning, message: 'has an open tab');
        $this->assertRejected(S::Reserved, message: 'has an open tab');
    }

    public function test_the_tab_lifecycle_moves_through_system_transitions(): void
    {
        $this->status->change($this->table, S::Reserved);
        foreach ([S::Occupied, S::AwaitingPayment, S::Occupied, S::AwaitingPayment, S::Cleaning] as $to) {
            $this->status->change($this->table, $to, system: true);
        }
        $this->status->change($this->table, S::Available);

        $this->assertSame(S::Available, $this->table->fresh()->status);
    }

    public function test_impossible_system_jumps_are_rejected(): void
    {
        $this->assertRejected(S::AwaitingPayment, system: true, message: 'cannot');
        $this->status->change($this->table, S::Cleaning);
        $this->assertRejected(S::Occupied, system: true, message: 'cannot');
    }

    public function test_the_same_status_is_a_no_op_without_a_log(): void
    {
        $this->status->change($this->table, S::Available);

        $this->assertSame(0, ActivityLog::where('action', 'table.status_changed')->count());
    }

    public function test_an_inactive_table_refuses_status_changes(): void
    {
        app(DiningTableService::class)->setActive($this->table, false);

        $this->assertRejected(S::Reserved, message: 'not in use');
    }

    public function test_a_table_with_a_tab_cannot_be_deactivated(): void
    {
        $this->status->change($this->table, S::Occupied, system: true);

        $this->expectException(ValidationException::class);
        app(DiningTableService::class)->setActive($this->table, false);
    }

    public function test_table_edits_are_logged(): void
    {
        $tables = app(DiningTableService::class);
        $tables->update($this->table, ['label' => 'Bar 1', 'capacity' => 2]);
        $tables->setActive($this->table->fresh(), false);
        $tables->setActive($this->table->fresh(), true);

        $this->assertSame(['table.created', 'table.updated', 'table.deactivated', 'table.activated'],
            ActivityLog::orderBy('id')->pluck('action')->all());
        $this->assertSame(['label' => 'T1', 'capacity' => 4], ActivityLog::where('action', 'table.updated')->sole()->old_values);
    }
}

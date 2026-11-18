<?php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_records_actor_subject_and_values(): void
    {
        $actor = User::factory()->create(['username' => 'boss', 'name' => 'Boss']);
        $subject = User::factory()->create();
        $this->actingAs($actor);

        app(ActivityLogger::class)->log('user.updated', $subject, ['name' => 'Old'], ['name' => 'New']);

        $log = ActivityLog::sole();
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame('Boss', $log->user_name);
        $this->assertSame('user.updated', $log->action);
        $this->assertSame($subject->getMorphClass(), $log->subject_type);
        $this->assertSame($subject->id, $log->subject_id);
        $this->assertSame(['name' => 'Old'], $log->old_values);
        $this->assertSame(['name' => 'New'], $log->new_values);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertNotNull($log->created_at);
    }

    public function test_activity_logs_cannot_be_updated_or_deleted(): void
    {
        app(ActivityLogger::class)->log('test.entry');

        foreach ([
            fn () => DB::table('activity_logs')->update(['action' => 'tampered']),
            fn () => DB::table('activity_logs')->delete(),
        ] as $attempt) {
            try {
                DB::transaction($attempt); // savepoint, so the outer test transaction survives the error
                $this->fail('activity_logs accepted a change');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }

        $this->assertSame('test.entry', ActivityLog::sole()->action);
    }

    public function test_web_login_logout_and_failed_login_are_logged(): void
    {
        $user = User::factory()->create(['username' => 'cashier1']);

        $this->post('/login', ['username' => 'cashier1', 'password' => 'wrong-password']);
        $this->post('/login', ['username' => 'cashier1', 'password' => 'password']);
        $this->post('/logout');

        $logs = ActivityLog::orderBy('id')->get();
        $this->assertSame(['auth.login_failed', 'auth.login', 'auth.logout'], $logs->pluck('action')->all());
        $this->assertSame(['username' => 'cashier1'], $logs[0]->new_values);
        $this->assertNull($logs[0]->user_id);
        $this->assertSame($user->id, $logs[1]->user_id);
        $this->assertSame($user->id, $logs[2]->user_id);
        $this->assertStringNotContainsString('password', $logs->toJson());
    }
}

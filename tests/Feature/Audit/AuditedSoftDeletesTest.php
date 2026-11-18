<?php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\Concerns\HasAuditedSoftDeletes;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class AuditedSoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // DDL is transactional in PostgreSQL, so RefreshDatabase rolls this table back too.
        Schema::create('supply_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
        $this->actingAs(User::factory()->create());
    }

    public function test_delete_archives_the_record_and_logs_who_did_it(): void
    {
        $item = SupplyItem::create(['name' => 'Napkins']);

        $item->delete();

        $this->assertSoftDeleted($item);
        $this->assertSame(0, SupplyItem::count());
        $this->assertSame(1, SupplyItem::withTrashed()->count());

        $log = ActivityLog::where('action', 'supply_item.archived')->sole();
        $this->assertSame(auth()->id(), $log->user_id);
        $this->assertSame($item->id, $log->subject_id);
    }

    public function test_restore_brings_it_back_and_logs(): void
    {
        $item = SupplyItem::create(['name' => 'Straws']);
        $item->delete();

        $item->restore();

        $this->assertNotSoftDeleted($item);
        $this->assertSame(1, ActivityLog::where('action', 'supply_item.restored')->count());
    }

    public function test_force_delete_is_refused(): void
    {
        $item = SupplyItem::create(['name' => 'Ice']);

        try {
            $item->forceDelete();
            $this->fail('forceDelete removed a business record');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Hard deletes are not allowed', $e->getMessage());
        }

        $this->assertDatabaseHas('supply_items', ['id' => $item->id, 'deleted_at' => null]);
        $this->assertSame(0, ActivityLog::where('action', 'supply_item.archived')->count());
    }
}

class SupplyItem extends Model
{
    use HasAuditedSoftDeletes;

    protected $fillable = ['name'];
}

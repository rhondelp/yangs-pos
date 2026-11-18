<?php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create(['name' => 'Olive Owner'])->assignRole('Owner');
    }

    private function logAt(string $manilaTime, string $action, ?User $actor = null, array $old = [], array $new = []): ActivityLog
    {
        $this->travelTo(CarbonImmutable::parse($manilaTime, 'Asia/Manila'));
        $log = app(ActivityLogger::class)->log($action, null, $old, $new, $actor ?? $this->owner);
        $this->travelBack();

        return $log;
    }

    public function test_owner_sees_a_paginated_newest_first_list_that_keeps_filters(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->logAt(sprintf('2026-11-18 10:%02d', $i), sprintf('bulk.%02d', $i));
        }

        $page1 = $this->actingAs($this->owner)->get("/activity-logs?user_id={$this->owner->id}")
            ->assertOk()
            ->assertSee("user_id={$this->owner->id}&amp;page=2", false);
        $this->assertSame(array_map(fn ($i) => sprintf('bulk.%02d', $i), range(30, 6)), $this->rows($page1));

        $page2 = $this->actingAs($this->owner)->get("/activity-logs?user_id={$this->owner->id}&page=2")->assertOk();
        $this->assertSame(['bulk.05', 'bulk.04', 'bulk.03', 'bulk.02', 'bulk.01'], $this->rows($page2));
    }

    /** Actions of the rows in the listed page (the filter dropdown lists every action, so HTML text is not enough). */
    private function rows($response): array
    {
        return $response->viewData('logs')->getCollection()->pluck('action')->all();
    }

    public function test_filters_by_user_action_and_inclusive_manila_date_range(): void
    {
        $cashier = User::factory()->create(['name' => 'Carla Cashier']);
        $this->logAt('2026-11-17 23:59', 'day.before');
        $this->logAt('2026-11-18 00:00', 'day.start');
        $this->logAt('2026-11-18 23:59', 'day.end');
        $this->logAt('2026-11-19 00:00', 'day.after');
        $this->logAt('2026-11-18 12:00', 'payment.recorded', $cashier);

        $this->actingAs($this->owner);

        $this->assertEqualsCanonicalizing(
            ['day.start', 'day.end', 'payment.recorded'],
            $this->rows($this->get('/activity-logs?from=2026-11-18&to=2026-11-18')->assertOk()),
        );
        $this->assertSame(['payment.recorded'], $this->rows($this->get("/activity-logs?user_id={$cashier->id}")));
        $this->assertSame(['day.end'], $this->rows($this->get('/activity-logs?action=day.end')));
    }

    public function test_detail_shows_old_and_new_values_escaped(): void
    {
        $log = $this->logAt('2026-11-18 09:00', 'product.price_changed', null,
            ['price' => '120.00', 'name' => 'Red Horse'],
            ['price' => '130.00', 'name' => '<script>alert(1)</script>'],
        );

        $this->actingAs($this->owner)->get("/activity-logs/{$log->id}")
            ->assertOk()
            ->assertSeeInOrder(['product.price_changed', 'Olive Owner', 'price', '120.00', '130.00'])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_api_lists_filtered_logs(): void
    {
        $this->logAt('2026-11-18 09:00', 'user.updated', null, ['name' => 'A'], ['name' => 'B']);
        $this->logAt('2026-11-18 09:05', 'auth.login');

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/activity-logs?action=user.updated&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'user.updated')
            ->assertJsonPath('data.0.user_name', 'Olive Owner')
            ->assertJsonPath('data.0.old_values', ['name' => 'A'])
            ->assertJsonPath('data.0.new_values', ['name' => 'B'])
            ->assertJsonPath('data.0.created_at', '2026-11-18T09:00:00+08:00')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v1/activity-logs?from=2026-11-20&to=2026-11-18&user_id=999999&per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to', 'user_id', 'per_page']);

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/activity-logs?from=yesterday-ish')
            ->assertJsonValidationErrors('from');

        $this->actingAs($this->owner)->from('/activity-logs')->get('/activity-logs?to=2026-11-18&from=2026-11-20')
            ->assertRedirect('/activity-logs')
            ->assertSessionHasErrors('to');
    }

    public function test_cashier_cannot_view_logs_on_web_or_api(): void
    {
        $cashier = User::factory()->create()->assignRole('Cashier');
        $log = $this->logAt('2026-11-18 09:00', 'auth.login');

        $this->actingAs($cashier)->get('/activity-logs')->assertForbidden();
        $this->actingAs($cashier)->get("/activity-logs/{$log->id}")->assertForbidden();
        $this->actingAs($cashier, 'sanctum')->getJson('/api/v1/activity-logs')->assertForbidden();
    }
}

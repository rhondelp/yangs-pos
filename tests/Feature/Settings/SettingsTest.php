<?php

namespace Tests\Feature\Settings;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Settings\SettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create()->assignRole('Owner');
    }

    public function test_defaults_apply_until_the_owner_saves_values(): void
    {
        $settings = app(SettingService::class);

        $this->assertSame('YANGS', $settings->get('business_name'));
        $this->assertFalse($settings->get('clean_table_after_payment'));
        $this->assertSame('Thank you! Come again.', $settings->get('receipt_footer'));
    }

    public function test_saving_settings_persists_changed_values_and_logs_them(): void
    {
        $this->actingAs($this->owner);
        app(SettingService::class)->set(['business_name' => 'YANGS Bar', 'clean_table_after_payment' => true, 'receipt_footer' => 'Thank you! Come again.']);

        $fresh = $this->app->make(SettingService::class);
        $this->assertSame('YANGS Bar', $fresh->get('business_name'));
        $this->assertTrue($fresh->get('clean_table_after_payment'));

        $log = ActivityLog::where('action', 'settings.updated')->sole();
        $this->assertSame(['business_name' => 'YANGS', 'clean_table_after_payment' => false], $log->old_values);
        $this->assertSame(['business_name' => 'YANGS Bar', 'clean_table_after_payment' => true], $log->new_values);
    }

    public function test_the_seeder_adds_defaults_without_overwriting_owner_edits(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->actingAs($this->owner);
        app(SettingService::class)->set(['business_name' => 'YANGS Bar']);

        $this->seed(SettingsSeeder::class);

        $this->assertSame('YANGS Bar', app(SettingService::class)->get('business_name'));
        $this->assertSame(5, \DB::table('settings')->count());
    }

    public function test_owner_edits_settings_on_the_settings_page(): void
    {
        $this->actingAs($this->owner)->get('/settings')->assertOk()->assertSee('Business name');

        $this->put('/settings', [
            'business_name' => 'YANGS Bar & Grill', 'business_address' => 'Poblacion, Makati', 'business_tin' => '123-456-789-000',
            'receipt_footer' => 'Salamat po!', 'clean_table_after_payment' => '1',
        ])->assertRedirect('/settings')->assertSessionHas('status');

        $this->assertSame('Poblacion, Makati', app(SettingService::class)->get('business_address'));
        $this->assertTrue(app(SettingService::class)->get('clean_table_after_payment'));

        $this->put('/settings', ['business_name' => '', 'receipt_footer' => str_repeat('x', 301)])
            ->assertSessionHasErrors(['business_name', 'receipt_footer']);
    }

    public function test_cashier_cannot_open_settings(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Cashier'));

        $this->get('/settings')->assertForbidden();
        $this->put('/settings', ['business_name' => 'Hacked'])->assertForbidden();
    }
}

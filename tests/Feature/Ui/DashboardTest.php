<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_owner_sees_empty_states_instead_of_numbers(): void
    {
        $owner = User::factory()->create()->assignRole('Owner');

        $response = $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard.owner')
            ->assertSee("Today's sales")
            ->assertSee('Low-stock products')
            ->assertSee('Upcoming shifts')
            ->assertDontSee('₱');

        foreach ($response->viewData('stats') as $stat) {
            $this->assertNull($stat['value'], "{$stat['label']} must not show a number before real records exist");
        }
    }

    public function test_owner_dashboard_shows_real_recent_activity(): void
    {
        $owner = User::factory()->create(['name' => 'Olive Owner'])->assignRole('Owner');
        app(ActivityLogger::class)->log('user.created', $owner, actor: $owner);

        $this->actingAs($owner)->get('/dashboard')
            ->assertSee('user.created')
            ->assertSee('Olive Owner');
    }

    public function test_cashier_gets_the_pos_layout_dashboard(): void
    {
        $cashier = User::factory()->create(['name' => 'Carla Cashier'])->assignRole('Cashier');

        $this->actingAs($cashier)->get('/dashboard')->assertRedirect(route('floor'));

        $this->get(route('floor'))
            ->assertOk()
            ->assertViewIs('floor.index')
            ->assertSee('data-layout="pos"', false)
            ->assertSee('Carla Cashier')
            ->assertSee('No tables yet')
            ->assertDontSee('href="'.route('users.index').'"', false);
    }

    public function test_a_role_without_a_dashboard_gets_a_clear_message(): void
    {
        $bartender = User::factory()->create()->assignRole('Bartender');

        $this->actingAs($bartender)->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard.none')
            ->assertSee('Your role has no dashboard yet');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}

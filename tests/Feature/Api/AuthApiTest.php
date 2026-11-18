<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_returns_a_token_and_the_user(): void
    {
        $user = User::factory()->create(['username' => 'cashier1'])->assignRole('Cashier');

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'Cashier1',
            'password' => 'password',
            'device_name' => 'Tablet 1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.username', 'cashier1')
            ->assertJsonPath('data.user.role', 'Cashier')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'username', 'email', 'is_active', 'role', 'permissions']]]);
        $this->assertSame('Tablet 1', $user->tokens()->sole()->name);
        $this->assertSame('auth.login', ActivityLog::latest('id')->first()->action);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertSame('auth.login_failed', ActivityLog::sole()->action);
    }

    public function test_login_rejects_a_deactivated_user(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'nope']);
        }

        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password'])
            ->assertTooManyRequests();
    }

    public function test_me_returns_role_and_permissions(): void
    {
        $user = User::factory()->create()->assignRole('Cashier');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'Cashier')
            ->assertJsonPath('data.permissions', [
                'cash-drawer.manage-own', 'dashboard.cashier', 'orders.view-own', 'payments.record',
                'pos.create-order', 'pos.open-session', 'receipts.view', 'tables.view',
            ]);
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->get('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_deletes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('tablet')->plainTextToken;
        $user->createToken('phone');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(['phone'], $user->tokens()->pluck('name')->all());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_deactivated_user_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('tablet')->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}

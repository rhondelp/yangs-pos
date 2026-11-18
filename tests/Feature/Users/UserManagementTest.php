<?php

namespace Tests\Feature\Users;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner = User::factory()->create(['username' => 'owner'])->assignRole('Owner');
    }

    private function validUser(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Cruz',
            'username' => 'Ana',
            'email' => '',
            'password' => 'cashier-pass-1',
            'password_confirmation' => 'cashier-pass-1',
            'role' => 'Cashier',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_owner_can_list_users_on_web_and_api(): void
    {
        User::factory()->create(['username' => 'bartender1'])->assignRole('Bartender');

        $this->actingAs($this->owner)->get('/users')->assertOk()->assertSee('bartender1')->assertSee('Bartender');

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.role', 'Bartender');
    }

    public function test_owner_can_create_a_user_with_a_role(): void
    {
        $this->actingAs($this->owner)->post('/users', $this->validUser())->assertRedirect('/users');

        $user = User::where('username', 'ana')->sole();
        $this->assertTrue($user->hasRole('Cashier'));
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email);
        $this->assertTrue(Hash::check('cashier-pass-1', $user->password));

        $log = ActivityLog::where('action', 'user.created')->sole();
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame('Cashier', $log->new_values['role']);
        $this->assertStringNotContainsString('cashier-pass-1', json_encode($log->new_values));
        $this->assertArrayNotHasKey('password', $log->new_values);
    }

    public function test_owner_can_create_a_user_via_api(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/users', $this->validUser(['is_active' => true]))
            ->assertCreated()
            ->assertJsonPath('data.username', 'ana')
            ->assertJsonPath('data.role', 'Cashier');
    }

    public function test_user_input_is_validated(): void
    {
        User::factory()->create(['username' => 'ana']);

        $this->actingAs($this->owner)
            ->post('/users', $this->validUser(['username' => 'ANA', 'password' => 'short', 'password_confirmation' => 'other', 'role' => 'Wizard', 'name' => '']))
            ->assertSessionHasErrors(['username', 'password', 'role', 'name']);

        $this->actingAs($this->owner)
            ->post('/users', $this->validUser(['username' => 'has space']))
            ->assertSessionHasErrors('username');

        $this->assertSame(2, User::count());
    }

    public function test_owner_can_edit_a_user_and_reset_their_password(): void
    {
        $user = User::factory()->create(['username' => 'ana', 'name' => 'Ana Cruz', 'email' => null])->assignRole('Cashier');

        $this->actingAs($this->owner)->get("/users/{$user->id}/edit")->assertOk()->assertSee('ana');
        $this->actingAs($this->owner)->put("/users/{$user->id}", $this->validUser([
            'name' => 'Ana Reyes', 'role' => 'Supervisor', 'password' => 'new-pass-123', 'password_confirmation' => 'new-pass-123',
        ]))->assertRedirect('/users');

        $user->refresh();
        $this->assertSame('Ana Reyes', $user->name);
        $this->assertTrue($user->hasRole('Supervisor'));
        $this->assertFalse($user->hasRole('Cashier'));
        $this->assertTrue(Hash::check('new-pass-123', $user->password));

        $log = ActivityLog::where('action', 'user.updated')->sole();
        $this->assertSame(['name' => 'Ana Cruz', 'role' => 'Cashier'], $log->old_values);
        $this->assertSame(['name' => 'Ana Reyes', 'role' => 'Supervisor', 'password_changed' => true], $log->new_values);
        $this->assertStringNotContainsString('new-pass-123', json_encode([$log->old_values, $log->new_values]));
    }

    public function test_api_update_without_an_email_keeps_the_existing_email(): void
    {
        $user = User::factory()->create(['username' => 'ana', 'email' => 'ana@example.com'])->assignRole('Cashier');
        $payload = $this->validUser();
        unset($payload['email'], $payload['password'], $payload['password_confirmation']);

        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/users/{$user->id}", $payload)->assertOk();

        $this->assertSame('ana@example.com', $user->fresh()->email);
    }

    public function test_blank_password_on_edit_keeps_the_old_one(): void
    {
        $user = User::factory()->create(['username' => 'ana'])->assignRole('Cashier');

        $this->actingAs($this->owner)->put("/users/{$user->id}", $this->validUser(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/users');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_deactivating_a_user_revokes_their_tokens_and_sessions(): void
    {
        $user = User::factory()->create(['username' => 'ana'])->assignRole('Cashier');
        $user->createToken('tablet');
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->owner)->put("/users/{$user->id}", $this->validUser(['is_active' => '0']))->assertRedirect('/users');

        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(['is_active' => false], array_intersect_key(ActivityLog::where('action', 'user.deactivated')->sole()->new_values, ['is_active' => 1]));
    }

    public function test_owner_cannot_deactivate_themselves(): void
    {
        User::factory()->create()->assignRole('Owner');

        $this->actingAs($this->owner)
            ->put("/users/{$this->owner->id}", $this->validUser(['username' => 'owner', 'role' => 'Owner', 'is_active' => '0']))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($this->owner->fresh()->is_active);
    }

    public function test_last_active_owner_cannot_be_demoted_or_deactivated(): void
    {
        $inactiveOwner = User::factory()->create(['is_active' => false])->assignRole('Owner');
        $secondOwner = User::factory()->create(['username' => 'boss2'])->assignRole('Owner');

        // Two active owners: one may demote the other.
        $this->actingAs($this->owner)
            ->put("/users/{$secondOwner->id}", $this->validUser(['username' => 'boss2', 'role' => 'Cashier']))
            ->assertSessionHasNoErrors();

        // Now $this->owner is the last active owner and cannot demote themselves.
        $this->actingAs($this->owner)
            ->put("/users/{$this->owner->id}", $this->validUser(['username' => 'owner', 'role' => 'Cashier']))
            ->assertSessionHasErrors('role');

        $this->assertTrue($this->owner->fresh()->hasRole('Owner'));
        $this->assertFalse($inactiveOwner->fresh()->is_active);
    }

    public function test_cashier_is_forbidden_from_user_management_on_web_and_api(): void
    {
        $cashier = User::factory()->create()->assignRole('Cashier');
        $target = User::factory()->create();

        $this->actingAs($cashier);
        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        $this->post('/users', $this->validUser())->assertForbidden();
        $this->get("/users/{$target->id}/edit")->assertForbidden();
        $this->put("/users/{$target->id}", $this->validUser())->assertForbidden();

        $this->actingAs($cashier, 'sanctum');
        $this->getJson('/api/v1/users')->assertForbidden();
        $this->postJson('/api/v1/users', $this->validUser())->assertForbidden();
        $this->getJson("/api/v1/users/{$target->id}")->assertForbidden();
        $this->putJson("/api/v1/users/{$target->id}", $this->validUser())->assertForbidden();

        $this->assertSame(3, User::count());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_a_non_owner_user_manager_cannot_touch_owners_or_grant_the_owner_role(): void
    {
        Role::findByName('Supervisor')->givePermissionTo('users.manage');
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)->get("/users/{$this->owner->id}/edit")->assertForbidden();
        $this->actingAs($supervisor)
            ->put("/users/{$this->owner->id}", $this->validUser(['username' => 'owner', 'role' => 'Owner', 'password' => 'hijack-pass-1', 'password_confirmation' => 'hijack-pass-1']))
            ->assertForbidden();
        $this->actingAs($supervisor)->post('/users', $this->validUser(['role' => 'Owner']))->assertSessionHasErrors('role');
        $this->actingAs($supervisor)->put("/users/{$supervisor->id}", $this->validUser(['role' => 'Owner']))->assertSessionHasErrors('role');

        $this->assertTrue(Hash::check('password', $this->owner->fresh()->password));
        $this->assertFalse($supervisor->fresh()->hasRole('Owner'));

        // Ordinary staff accounts are still theirs to manage.
        $this->actingAs($supervisor)->post('/users', $this->validUser())->assertRedirect('/users');
    }
}

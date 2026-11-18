<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_user_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login')->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}

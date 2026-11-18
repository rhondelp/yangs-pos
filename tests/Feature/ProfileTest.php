<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_users_cannot_edit_their_own_details_or_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'X'])->assertMethodNotAllowed();
        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertMethodNotAllowed();
        $this->assertNotNull($user->fresh());
    }
}

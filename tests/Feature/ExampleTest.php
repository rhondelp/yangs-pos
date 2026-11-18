<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_sends_visitors_to_the_app(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->followingRedirects()->get('/')->assertOk()->assertSee('name="username"', false);
    }
}

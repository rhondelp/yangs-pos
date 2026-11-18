<?php

namespace Tests\Feature\Rbac;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class RouteGuardTest extends TestCase
{
    // Routes any signed-in user may reach. Everything else behind auth must name a permission.
    private const OPEN_TO_ANY_USER = [
        'dashboard',
        'profile',
        'password',
        'logout',
        'api/v1/auth/logout',
        'api/v1/auth/me',
    ];

    public function test_every_authenticated_route_requires_a_permission(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $authenticated = collect($middleware)->contains(fn ($m) => $m === 'auth' || Str::startsWith($m, 'auth:'));
            $guarded = collect($middleware)->contains(fn ($m) => Str::startsWith($m, 'can:'));

            if ($authenticated && ! $guarded && ! in_array($route->uri(), self::OPEN_TO_ANY_USER, true)) {
                $unguarded[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $unguarded, 'Add a can:<permission> middleware to these routes');
    }
}

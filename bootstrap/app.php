<?php

use App\Http\Middleware\LogoutDeactivatedUsers;
use App\Http\Middleware\RejectDeactivatedApiUsers;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [LogoutDeactivatedUsers::class]);

        // The POS screen calls /api/v1 with the browser session (Sanctum SPA mode, CSRF-protected).
        $middleware->statefulApi();
        $middleware->api(append: [RejectDeactivatedApiUsers::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients always get JSON errors, even without an Accept header.
        // Supervisor and approval PINs never go into the session's old input.
        $exceptions->dontFlash(['approver_pin', 'approval_pin', 'approval_pin_confirmation']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();

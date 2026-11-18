<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lazy loading, silently discarded attributes and missing attributes throw outside production.
        Model::shouldBeStrict(! $this->app->isProduction());

        Paginator::defaultView('components.ui.pagination');

        // API login. Web login has the same limit inside App\Http\Requests\Auth\LoginRequest.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('username')).'|'.$request->ip()));

        // Tokens of deactivated users stop working immediately.
        Sanctum::authenticateAccessTokensUsing(fn ($token, bool $isValid) => $isValid && $token->tokenable?->is_active === true);
    }
}

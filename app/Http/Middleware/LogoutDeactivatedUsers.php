<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Appended to the web group: a user deactivated mid-session is signed out on their next request.
// API tokens of deactivated users are rejected in AppServiceProvider (Sanctum callback).
class LogoutDeactivatedUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->user()?->is_active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => __('This account has been deactivated.')]);
        }

        return $next($request);
    }
}

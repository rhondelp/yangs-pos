<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Appended to the api group. Tokens of deactivated users are already rejected by the Sanctum callback in
// AppServiceProvider; this also stops a deactivated user's browser session (the POS screen calls the API with it).
class RejectDeactivatedApiUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('sanctum')->user()?->is_active === false) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}

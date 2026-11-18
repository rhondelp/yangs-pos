<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private ActivityLogger $logger) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $username = mb_strtolower(trim($request->string('username')));
        $user = User::where('username', $username)->first();

        // Deactivated users fail exactly like a wrong password.
        if (! $user || ! $user->is_active || ! Hash::check($request->string('password'), $user->password)) {
            $this->logger->log('auth.login_failed', $user, new: ['username' => $username, 'via' => 'api']);

            throw ValidationException::withMessages(['username' => trans('auth.failed')]);
        }

        $token = $user->createToken($request->input('device_name') ?: 'api')->plainTextToken;
        $this->logger->log('auth.login', $user, new: ['via' => 'api'], actor: $user);

        return response()->json(['data' => [
            'token' => $token,
            'user' => new UserResource($user),
        ]]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();
        $this->logger->log('auth.logout', $request->user(), new: ['via' => 'api']);

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}

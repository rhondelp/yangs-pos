<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SaveUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    private const WITH = ['roles.permissions', 'permissions'];

    public function __construct(private UserService $users) {}

    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(User::with(self::WITH)->orderBy('id')->get());
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(self::WITH));
    }

    public function update(SaveUserRequest $request, User $user): UserResource
    {
        $user = $this->users->update($user, $request->validated(), $request->user());

        return new UserResource($user->load(self::WITH));
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SaveUserRequest;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('roles')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('users.create', [
            'user' => new User(['is_active' => true]),
            'roles' => SaveUserRequest::assignableRoles($request->user()),
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());

        return redirect()->route('users.index')->with('status', "Account {$user->username} created.");
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => SaveUserRequest::assignableRoles($request->user()),
        ]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $user = $this->users->update($user, $request->validated(), $request->user());

        return redirect()->route('users.index')->with('status', "Account {$user->username} saved.");
    }
}

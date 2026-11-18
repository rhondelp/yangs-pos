<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateRolePermissionsRequest;
use App\Services\Users\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::with('permissions')->get()
                ->sortBy(fn (Role $role) => [$role->name !== RoleName::Owner->value, $role->name])
                ->values(),
            'groups' => collect(Permission::cases())->groupBy(fn (Permission $p) => $p->group()),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, RolePermissionService $service): RedirectResponse
    {
        $service->syncMatrix($request->validated('permissions', []));

        return redirect()->route('roles.index')->with('status', 'Role permissions saved.');
    }
}

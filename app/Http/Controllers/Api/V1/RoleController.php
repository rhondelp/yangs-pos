<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateRolePermissionsRequest;
use App\Http\Resources\V1\RoleResource;
use App\Services\Users\RolePermissionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::with('permissions')->orderBy('id')->get());
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role, RolePermissionService $service): RoleResource
    {
        return new RoleResource($service->sync($role->load('permissions'), $request->validated('permissions')));
    }
}

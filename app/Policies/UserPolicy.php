<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    // Only an Owner may change an Owner's account (name, password, role, status).
    public function update(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersManage->value)
            && (! $target->hasRole(RoleName::Owner->value) || $actor->hasRole(RoleName::Owner->value));
    }
}

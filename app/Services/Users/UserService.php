<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const AUDITED_FIELDS = ['name', 'username', 'email', 'is_active'];

    public function __construct(private ActivityLogger $logger) {}

    /** @param array{name: string, username: string, email?: ?string, password: string, role: string, is_active?: bool} $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $user->syncRoles([$data['role']]);

            $this->logger->log('user.created', $user, new: [
                ...$user->only(self::AUDITED_FIELDS),
                'role' => $data['role'],
            ]);

            return $user;
        });
    }

    /**
     * @param array{name: string, username: string, email?: ?string, password?: ?string, role: string, is_active?: bool} $data
     *
     * @throws ValidationException when the change would deactivate the actor or leave no active Owner
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $user = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $oldRole = $user->roles->first()?->name;
            $newRole = $data['role'];
            $newActive = $data['is_active'] ?? $user->is_active;

            if ($user->is($actor) && ! $newActive) {
                throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
            }

            $this->ensureAnotherActiveOwnerRemains($user, $oldRole, $newRole, $newActive);

            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                // A blank form field arrives as null and clears it; an API call that omits email keeps it.
                'email' => array_key_exists('email', $data) ? $data['email'] : $user->email,
                'is_active' => $newActive,
            ]);
            $new = $user->getDirty();
            $old = array_intersect_key($user->getOriginal(), $new);

            if ($oldRole !== $newRole) {
                $user->syncRoles([$newRole]);
                $old['role'] = $oldRole;
                $new['role'] = $newRole;
            }

            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $new['password_changed'] = true;
            }

            $user->save();

            if (array_key_exists('is_active', $new) && ! $newActive) {
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
            }

            if ($new) {
                $action = match ($new['is_active'] ?? null) {
                    true => 'user.activated',
                    false => 'user.deactivated',
                    null => 'user.updated',
                };
                $this->logger->log($action, $user, $old, $new);
            }

            return $user;
        });
    }

    private function ensureAnotherActiveOwnerRemains(User $user, ?string $oldRole, string $newRole, bool $newActive): void
    {
        $owner = RoleName::Owner->value;
        $losesOwner = $oldRole === $owner && $user->is_active && ($newRole !== $owner || ! $newActive);

        if (! $losesOwner) {
            return;
        }

        // Lock the other owners so two simultaneous demotions cannot both pass.
        $otherActiveOwners = User::role($owner)->where('is_active', true)->whereKeyNot($user->getKey())->lockForUpdate()->pluck('id');

        if ($otherActiveOwners->isEmpty()) {
            throw ValidationException::withMessages([
                $newRole !== $owner ? 'role' : 'is_active' => 'At least one active Owner account is required.',
            ]);
        }
    }
}

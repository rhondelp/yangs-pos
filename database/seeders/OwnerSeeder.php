<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Creates the first owner account from OWNER_* env values. Never overwrites an existing account.
class OwnerSeeder extends Seeder
{
    public function run(ActivityLogger $logger): void
    {
        $config = config('auth.owner');
        $username = mb_strtolower(trim((string) $config['username']));

        if (User::where('username', $username)->exists()) {
            return;
        }

        if (mb_strlen((string) $config['password']) < 8) {
            throw new RuntimeException('Set OWNER_PASSWORD (at least 8 characters) in .env before seeding the owner account.');
        }

        DB::transaction(function () use ($config, $username, $logger) {
            $owner = User::create([
                'name' => $config['name'],
                'username' => $username,
                'email' => $config['email'] ?: null,
                'password' => $config['password'],
                'is_active' => true,
            ]);
            $owner->assignRole(RoleName::Owner->value);

            $logger->log('user.created', $owner, new: ['username' => $username, 'role' => RoleName::Owner->value, 'source' => 'seeder']);
        });
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Production-safe seeds only. Sample data, when it exists, goes in a local-only seeder.
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            OwnerSeeder::class,
        ]);
    }
}

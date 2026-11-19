<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Production-safe seeds first. Sample data runs only in the local environment.
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CategorySeeder::class,
            SettingsSeeder::class,
            PaymentMethodSeeder::class,
            OwnerSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(SampleTableSeeder::class);
        }
    }
}

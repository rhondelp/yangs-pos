<?php

namespace Database\Seeders;

use App\Services\Settings\SettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Production-safe: stores defaults for keys that were never saved; never overwrites the owner's values.
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingService::DEFAULTS as $key => $value) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}

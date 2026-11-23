<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

// Production-safe: adds missing templates only and never changes one the owner edited. The bar opens at 20:00.
class ShiftSeeder extends Seeder
{
    public const DEFAULTS = [['Morning', '06:00', '14:00'], ['Afternoon', '14:00', '20:00'], ['Evening', '20:00', '04:00']];

    public function run(): void
    {
        foreach (self::DEFAULTS as [$name, $start, $end]) {
            Shift::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists() || Shift::create(['name' => $name, 'start_time' => $start, 'end_time' => $end]);
        }
    }
}

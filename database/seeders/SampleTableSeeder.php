<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use Illuminate\Database\Seeder;

// Local/dev sample data only (CLAUDE.md). DatabaseSeeder calls this in the local environment.
class SampleTableSeeder extends Seeder
{
    public function run(): void
    {
        $capacities = [4, 4, 4, 4, 6, 6, 2, 2, 8, 4];

        foreach ($capacities as $i => $capacity) {
            DiningTable::firstOrCreate(['label' => 'T'.($i + 1)], ['capacity' => $capacity, 'sort_order' => ($i + 1) * 10]);
        }
    }
}

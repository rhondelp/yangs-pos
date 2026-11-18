<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

// Spec §5 categories. Production-safe: adds missing ones, never changes or revives ones the owner edited or archived.
class CategorySeeder extends Seeder
{
    public const NAMES = ['Beer', 'Liquor', 'Cocktails', 'Wine', 'Soft Drinks', 'Water', 'Food and Snacks', 'Other Products'];

    public function run(): void
    {
        foreach (self::NAMES as $i => $name) {
            Category::withTrashed()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()
                || Category::create(['name' => $name, 'sort_order' => ($i + 1) * 10]);
        }
    }
}

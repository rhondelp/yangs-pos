<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

// Production-safe: adds missing categories only. No "Product purchases" (stock-in) and no "Salaries" (payroll).
class ExpenseCategorySeeder extends Seeder
{
    public const NAMES = ['Electricity', 'Water', 'Rent', 'Employee-related', 'Maintenance', 'Transportation', 'Other'];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            ExpenseCategory::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists() || ExpenseCategory::create(['name' => $name]);
        }
    }
}

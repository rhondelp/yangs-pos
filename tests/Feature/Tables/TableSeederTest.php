<?php

namespace Tests\Feature\Tables;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_tables_are_ten_available_tables_and_reseeding_adds_none(): void
    {
        $this->seed(SampleTableSeeder::class);
        $this->seed(SampleTableSeeder::class);

        $this->assertSame(10, DiningTable::count());
        $this->assertSame(['T1', 'T2', 'T3'], DiningTable::orderBy('sort_order')->limit(3)->pluck('label')->all());
        $this->assertSame(10, DiningTable::where('status', TableStatus::Available)->where('is_active', true)->count());
    }

    public function test_the_main_seeder_adds_sample_tables_only_in_local(): void
    {
        config(['auth.owner.password' => 'owner-pass-123']);
        $this->seed(DatabaseSeeder::class); // testing environment

        $this->assertSame(0, DiningTable::count());

        $this->app['env'] = 'local';
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(10, DiningTable::count());
    }
}

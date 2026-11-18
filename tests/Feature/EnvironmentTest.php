<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvironmentTest extends TestCase
{
    // Guards against tests silently running on SQLite or against the dev database.
    public function test_app_runs_on_postgres_test_database_in_manila_time(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('yangs_pos_test', DB::connection()->getDatabaseName());
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', now()->timezoneName);
        $this->assertTrue(extension_loaded('bcmath'));
    }

    // A dev `config:cache` must never point tests (and RefreshDatabase) at the dev database.
    public function test_tests_ignore_the_dev_config_cache(): void
    {
        $this->assertNotSame($this->app->bootstrapPath('cache/config.php'), $this->app->getCachedConfigPath());
    }
}

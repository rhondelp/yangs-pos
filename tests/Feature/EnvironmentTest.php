<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvironmentTest extends TestCase
{
    // Guards against tests silently running on SQLite or against the dev database.
    public function test_app_runs_on_postgres_test_database_in_manila_time(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('yangs_test', DB::connection()->getDatabaseName());
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('en', app()->getLocale());
        $this->assertSame('PHP', config('app.currency'));
        $this->assertSame('Asia/Manila', now()->timezoneName);
        $this->assertTrue(extension_loaded('bcmath'));
        // Laravel writes local times without an offset; the DB session must read them as Manila time.
        $this->assertSame('Asia/Manila', DB::selectOne("select current_setting('TimeZone') as tz")->tz);
    }

    public function test_eloquent_is_strict_outside_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertTrue(Model::preventsSilentlyDiscardingAttributes());
        $this->assertTrue(Model::preventsAccessingMissingAttributes());
    }

    // A dev `config:cache` must never point tests (and RefreshDatabase) at the dev database.
    public function test_tests_ignore_the_dev_config_cache(): void
    {
        $this->assertNotSame($this->app->bootstrapPath('cache/config.php'), $this->app->getCachedConfigPath());
    }
}

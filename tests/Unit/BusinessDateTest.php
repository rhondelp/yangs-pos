<?php

namespace Tests\Unit;

use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessDateTest extends TestCase
{
    public function test_dates_before_the_cutoff_belong_to_the_previous_business_day(): void
    {
        $manila = fn (string $at) => CarbonImmutable::parse($at, 'Asia/Manila');

        $this->assertSame('2026-11-18', BusinessDate::for($manila('2026-11-18 20:00')));
        $this->assertSame('2026-11-18', BusinessDate::for($manila('2026-11-19 01:30')));
        $this->assertSame('2026-11-18', BusinessDate::for($manila('2026-11-19 05:59')));
        $this->assertSame('2026-11-19', BusinessDate::for($manila('2026-11-19 06:00')));
    }

    public function test_utc_input_is_converted_to_manila_first(): void
    {
        // 2026-11-18 22:30 UTC is 2026-11-19 06:30 in Manila.
        $this->assertSame('2026-11-19', BusinessDate::for(CarbonImmutable::parse('2026-11-18 22:30', 'UTC')));
        // 2026-11-18 21:30 UTC is 2026-11-19 05:30 in Manila.
        $this->assertSame('2026-11-18', BusinessDate::for(CarbonImmutable::parse('2026-11-18 21:30', 'UTC')));
    }

    public function test_the_cutoff_config_is_respected(): void
    {
        config(['pos.business_day_cutoff' => '04:00']);

        $this->assertSame('2026-11-18', BusinessDate::for(CarbonImmutable::parse('2026-11-19 03:59', 'Asia/Manila')));
        $this->assertSame('2026-11-19', BusinessDate::for(CarbonImmutable::parse('2026-11-19 04:00', 'Asia/Manila')));
    }
}

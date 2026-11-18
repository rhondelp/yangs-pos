<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_equals_ignores_formatting_and_handles_null(): void
    {
        $this->assertTrue(Money::equals('120', '120.00'));
        $this->assertTrue(Money::equals('120.5', '120.50'));
        $this->assertFalse(Money::equals('120.00', '120.01'));
        $this->assertTrue(Money::equals(null, null));
        $this->assertFalse(Money::equals(null, '0.00'));
    }

    public function test_sub_keeps_two_decimals(): void
    {
        $this->assertSame('75.00', Money::sub('120.00', '45.00'));
        $this->assertSame('-5.50', Money::sub('10', '15.5'));
    }

    public function test_add_and_mul_keep_two_decimals(): void
    {
        $this->assertSame('100.50', Money::add('85', '15.5'));
        $this->assertSame('255.00', Money::mul('85.00', 3));
        $this->assertSame('0.00', Money::mul('0.10', 0));
    }

    public function test_prorate_splits_half_up(): void
    {
        $this->assertSame('3.33', Money::prorate('10.00', 1, 3));
        $this->assertSame('6.67', Money::prorate('10.00', 2, 3));
        $this->assertSame('0.01', Money::prorate('0.01', 1, 2)); // 0.005 rounds up
        $this->assertSame('10.00', Money::prorate('10.00', 3, 3));
    }

    public function test_percent_of_rounds_half_up(): void
    {
        $this->assertSame('62.50', Money::percentOf('75.00', '120.00'));
        $this->assertSame('66.67', Money::percentOf('2.00', '3.00'));
        $this->assertSame('33.33', Money::percentOf('1.00', '3.00'));
        $this->assertSame('-50.00', Money::percentOf('-5.00', '10.00'));
        $this->assertSame('0.01', Money::percentOf('0.005', '50.00')); // 0.01 exactly at the half
    }

    public function test_format_shows_peso_and_dash_for_null(): void
    {
        $this->assertSame('₱1,250.00', Money::format('1250'));
        $this->assertSame('₱0.50', Money::format('0.5'));
        $this->assertSame('-₱5.50', Money::format('-5.5'));
        $this->assertSame('—', Money::format(null));
    }
}

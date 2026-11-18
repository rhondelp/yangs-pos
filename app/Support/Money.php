<?php

namespace App\Support;

/**
 * Money math on decimal strings with bcmath (CLAUDE.md: never float).
 * Amounts are 2-decimal strings; rounding is half-up (away from zero).
 */
final class Money
{
    private const WORK_SCALE = 10;

    public static function equals(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return bccomp($a, $b, 2) === 0;
    }

    public static function add(string $a, string $b): string
    {
        return self::round(bcadd($a, $b, self::WORK_SCALE));
    }

    public static function sub(string $a, string $b): string
    {
        return self::round(bcsub($a, $b, self::WORK_SCALE));
    }

    public static function mul(string $amount, int $factor): string
    {
        return self::round(bcmul($amount, (string) $factor, self::WORK_SCALE));
    }

    /** $amount × $part / $whole, half-up, e.g. the share of a discount that moves with cancelled units. */
    public static function prorate(string $amount, int $part, int $whole): string
    {
        return self::round(bcdiv(bcmul($amount, (string) $part, self::WORK_SCALE), (string) $whole, self::WORK_SCALE));
    }

    /** $part as a percentage of $whole, e.g. percentOf('75', '120') = '62.50'. $whole must not be zero. */
    public static function percentOf(string $part, string $whole): string
    {
        return self::round(bcdiv(bcmul($part, '100', self::WORK_SCALE), $whole, self::WORK_SCALE));
    }

    public static function round(string $value, int $scale = 2): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return str_starts_with($value, '-')
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);
    }

    /** Display only: "₱1,250.00", "-₱5.50", "—" for unknown. */
    public static function format(?string $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        $rounded = self::round($amount);
        $negative = str_starts_with($rounded, '-');
        [$whole, $cents] = explode('.', ltrim($rounded, '-'));

        return ($negative ? '-' : '').'₱'.number_format((int) $whole).'.'.$cents;
    }
}

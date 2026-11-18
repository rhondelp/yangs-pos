<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class BusinessDate
{
    /** The business day (Y-m-d) a moment belongs to: Manila time minus the cutoff. */
    public static function for(DateTimeInterface $at): string
    {
        [$hours, $minutes] = array_map('intval', explode(':', config('pos.business_day_cutoff')));

        return CarbonImmutable::instance($at)
            ->setTimezone('Asia/Manila')
            ->subHours($hours)
            ->subMinutes($minutes)
            ->toDateString();
    }
}

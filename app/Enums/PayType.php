<?php

namespace App\Enums;

enum PayType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Custom = 'custom';

    /** "a day", "a week", "a month"; custom pay shows its own basis instead. */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'a day',
            self::Weekly => 'a week',
            self::Monthly => 'a month',
            self::Custom => 'custom',
        };
    }
}

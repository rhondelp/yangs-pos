<?php

namespace App\Support;

/** Stock quantities are decimal(14,3) strings; people read "48 bottle" or "2.5 case", not "48.000". */
class Quantity
{
    public static function format(?string $quantity): string
    {
        if ($quantity === null) {
            return '—';
        }
        [$whole, $fraction] = array_pad(explode('.', $quantity), 2, '');
        $fraction = rtrim($fraction, '0');
        $sign = str_starts_with($whole, '-') ? '-' : '';

        return $sign.number_format((float) ltrim($whole, '-')).($fraction === '' ? '' : '.'.$fraction);
    }
}

<?php

namespace App\Support;

class Qty
{
    public static function round(float|int|string|null $value): float
    {
        return round((float) $value, 3);
    }

    /** 12.500 -> "12.5", 100.000 -> "100", thousands separated. */
    public static function fmt(float|int|string|null $value): string
    {
        $v = self::round($value);
        $s = number_format($v, 3, '.', ',');

        return rtrim(rtrim($s, '0'), '.');
    }

    public static function money(float|int|string|null $value, ?string $currency = null): string
    {
        return ($currency ? $currency.' ' : '').number_format((float) $value, 2);
    }
}

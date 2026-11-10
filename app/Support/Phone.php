<?php

namespace App\Support;

final class Phone
{
    /** 0812-3456-789 / +62 812 3456 789 / 812... -> 628123456789 */
    public static function normalize(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }
        return $digits;
    }
}

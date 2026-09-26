<?php

namespace App\Support;

/** Readable Tanzanian phone numbers for screens: "+255 700 123 456" or "0755 123 456". Stored values stay as digits. */
class Phone
{
    public static function display(?string $number): ?string
    {
        if ($number === null || $number === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $number);
        if (strlen($digits) === 12 && str_starts_with($digits, '255')) {
            return '+255 '.substr($digits, 3, 3).' '.substr($digits, 6, 3).' '.substr($digits, 9);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7);
        }

        return $number;
    }

    /** Digits for tel: and wa.me links: local numbers (0…) become 255…. */
    public static function international(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);

        return $digits === '' ? null : (str_starts_with($digits, '0') ? '255'.substr($digits, 1) : $digits);
    }
}

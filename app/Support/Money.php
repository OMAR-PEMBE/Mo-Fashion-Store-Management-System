<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Money
{
    /**
     * Screen display only: "TZS 175,000", or "TZS 10,000.25" when cents are present.
     * Exports and stored values keep their exact decimal strings.
     */
    public static function format(BigDecimal|string|int|null $value, bool $currency = true): string
    {
        $amount = BigDecimal::of((string) ($value ?? '0'))->toScale(2);
        [$whole, $cents] = explode('.', (string) $amount->abs());
        $text = number_format((int) $whole).($cents === '00' ? '' : '.'.$cents);
        $text = ($currency ? config('business.currency').' ' : '').$text;

        return $amount->isNegative() ? '-'.$text : $text;
    }

    /** Database SUM() results can come back as floats; this returns an exact two-decimal string. */
    public static function round(float|int|string|null $value): string
    {
        return (string) BigDecimal::of(is_float($value) ? sprintf('%.4F', $value) : (string) ($value ?? '0'))->toScale(2, RoundingMode::HalfUp);
    }
}

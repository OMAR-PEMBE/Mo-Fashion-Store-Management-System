<?php

namespace App\Support;

use Brick\Math\BigDecimal;

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
}

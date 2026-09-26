<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyFormatTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'whole shillings drop cents' => ['175000.00', 'TZS 175,000'],
            'real cents are kept' => ['10000.25', 'TZS 10,000.25'],
            'single cent digit is padded' => ['0.5', 'TZS 0.50'],
            'losses keep their sign' => ['-15000.00', '-TZS 15,000'],
            'zero' => ['0.00', 'TZS 0'],
            'missing value' => [null, 'TZS 0'],
            'integer input' => [45000, 'TZS 45,000'],
            'largest stored amount' => ['9999999999999.99', 'TZS 9,999,999,999,999.99'],
        ];
    }

    #[DataProvider('cases')]
    public function test_amounts_are_readable_without_losing_precision(string|int|null $value, string $expected): void
    {
        $this->assertSame($expected, Money::format($value));
    }

    public function test_currency_can_be_omitted_for_table_columns_labelled_in_tzs(): void
    {
        $this->assertSame('1,234,567.89', Money::format('1234567.89', currency: false));
    }
}

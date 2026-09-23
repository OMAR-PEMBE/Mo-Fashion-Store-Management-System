<?php

namespace Tests\Unit;

use App\Services\SaleService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SaleArithmeticTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'fractional currency' => [[['quantity' => 3, 'unit_price' => '0.10', 'unit_cost' => '0.03', 'discount_amount' => '0.01']], '0.30', '0.29', '0.09', '0.20'],
            'full discount retains cost' => [[['quantity' => 2, 'unit_price' => '100.00', 'unit_cost' => '40.00', 'discount_amount' => '200.00']], '200.00', '0.00', '80.00', '-80.00'],
            'loss making sale' => [[['quantity' => 1, 'unit_price' => '30.00', 'unit_cost' => '40.00']], '30.00', '30.00', '40.00', '-10.00'],
            'large exact decimal' => [[['quantity' => 1, 'unit_price' => '9999999999999.99', 'unit_cost' => '9999999999999.98']], '9999999999999.99', '9999999999999.99', '9999999999999.98', '0.01'],
            'multiple independent lines' => [[['quantity' => 3, 'unit_price' => '10.25', 'unit_cost' => '4.10', 'discount_amount' => '0.75'], ['quantity' => 2, 'unit_price' => '20.50', 'unit_cost' => '8.20', 'discount_amount' => '1.00']], '71.75', '70.00', '28.70', '41.30'],
        ];
    }

    #[DataProvider('cases')]
    public function test_totals_match_independent_decimal_examples(array $items, string $subtotal, string $total, string $cogs, string $profit): void
    {
        $result = (new SaleService)->calculateTotals($items);
        $this->assertSame($subtotal, $result['subtotal']);
        $this->assertSame($total, $result['total_amount']);
        $this->assertSame($cogs, $result['total_cogs']);
        $this->assertSame($profit, $result['gross_profit']);
    }
}

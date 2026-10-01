<?php

namespace Tests\Unit;

use App\Services\LeadProjectPdfService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LeadProjectPdfServiceTest extends TestCase
{
    #[DataProvider('vatIncludedAmounts')]
    public function test_it_splits_vat_included_amounts_without_losing_cents(
        float $gross,
        float $expectedNet,
        float $expectedVat,
    ): void {
        $breakdown = (new LeadProjectPdfService)->splitVatIncluded($gross);

        $this->assertSame($gross, $breakdown['gross']);
        $this->assertSame($expectedNet, $breakdown['net']);
        $this->assertSame($expectedVat, $breakdown['vat']);
        $this->assertSame($breakdown['gross'], round($breakdown['net'] + $breakdown['vat'], 2));
    }

    public static function vatIncludedAmounts(): array
    {
        return [
            'exact euro' => [122.00, 100.00, 22.00],
            'proposal total' => [512.40, 420.00, 92.40],
            'rounding down net' => [9.50, 7.79, 1.71],
            'rounding up net' => [16.55, 13.57, 2.98],
            'one cent' => [0.01, 0.01, 0.00],
        ];
    }

    #[DataProvider('vatIncludedUnitPrices')]
    public function test_it_rounds_split_unit_prices_to_two_decimals(float $gross, float $expectedNet): void
    {
        $this->assertSame($expectedNet, (new LeadProjectPdfService)->netUnitPrice($gross));
    }

    public static function vatIncludedUnitPrices(): array
    {
        return [
            't-shirt price' => [9.50, 7.79],
            'hoodie price' => [16.55, 13.57],
            'already four decimals' => [12.3456, 10.12],
        ];
    }

    #[DataProvider('moneyRoundingCases')]
    public function test_it_rounds_money_through_the_third_decimal(float $value, float $expected): void
    {
        $this->assertSame($expected, (new LeadProjectPdfService)->roundMoney($value));
    }

    public static function moneyRoundingCases(): array
    {
        return [
            'third decimal below five' => [1.034, 1.03],
            'third decimal equal to five' => [1.035, 1.04],
            'fourth decimal lifts the third' => [1.0347, 1.04],
        ];
    }
}

<?php

namespace Tests\Unit;

use App\Models\LeadSalesSheet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LeadSalesSheetVatTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_it_adds_vat_to_the_final_net_order_total(float $net, float $vat, float $gross): void
    {
        $sheet = new LeadSalesSheet(['revenue_total' => $net]);
        $totals = $sheet->vatTotals();

        $this->assertEquals($net, $totals['net']);
        $this->assertEquals($vat, $totals['vat']);
        $this->assertEquals($gross, $totals['gross']);
        $this->assertSame(22, $totals['rate']);
        $this->assertEquals($gross, round($totals['net'] + $totals['vat'], 2));
    }

    public static function amounts(): array
    {
        return [
            'empty' => [0, 0, 0],
            'whole euros' => [100, 22, 122],
            'Excel BS010 300 pieces' => [945, 207.90, 1152.90],
            'rounded VAT' => [512.40, 112.73, 625.13],
            'half cent rounds up' => [0.25, 0.06, 0.31],
            'one cent' => [0.01, 0, 0.01],
        ];
    }
}

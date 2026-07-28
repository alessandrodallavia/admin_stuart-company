<?php

namespace App\Services;

use App\Models\LeadSalesSheet;

class LeadSalesSheetService
{
    public function recalculate(LeadSalesSheet $sheet): void
    {
        $sheet->load('items.prints');

        foreach ($sheet->items as $item) {
            $printCost = (float) $item->prints->sum('unit_cost');
            $printPrice = (float) $item->prints->sum('unit_price');
            $quantity = (float) $item->quantity;
            $automaticFinalPrice = (float) $item->product_unit_price + $printPrice;
            $finalUnitPrice = $item->final_price_overridden
                ? (float) $item->final_unit_price
                : $automaticFinalPrice;
            $cost = $quantity * ((float) $item->product_unit_cost + $printCost);
            $revenue = $quantity * $finalUnitPrice;
            $item->forceFill([
                'final_unit_price' => $finalUnitPrice,
                'cost_total' => $cost,
                'revenue_total' => $revenue,
                'margin_total' => $revenue - $cost,
            ])->save();
        }

        $sheet->load('items');
        $productRevenue = (float) $sheet->items->sum('revenue_total');
        $productCost = (float) $sheet->items->sum('cost_total');
        $hasProducts = $sheet->items->isNotEmpty();
        $shippingFee = $hasProducts ? (float) $sheet->shipping_fee : 0;
        $shippingCharge = $hasProducts && $productRevenue <= (float) $sheet->free_shipping_threshold ? $shippingFee : 0;
        $shippingCost = $hasProducts ? $shippingFee : 0;
        $revenue = $productRevenue + $shippingCharge;
        $cost = $productCost + $shippingCost;
        $margin = $revenue - $cost;
        $sheet->forceFill([
            'product_revenue_total' => $productRevenue,
            'shipping_charge' => $shippingCharge,
            'shipping_cost' => $shippingCost,
            'revenue_total' => $revenue,
            'cost_total' => $cost,
            'margin_total' => $margin,
            'margin_percentage' => $revenue > 0 ? ($margin / $revenue) * 100 : 0,
        ])->save();
        $sheet->lead()->update([
            'margin_amount' => $margin,
            'quantity' => $sheet->items->sum('quantity'),
            'product' => $sheet->items->map(fn ($item) => $item->configuration_name ?: $item->product_name)->join(', '),
        ]);
    }

    public function tier($tiers, float $quantity)
    {
        return $tiers->first(fn ($tier) => $quantity >= (float) $tier->min_quantity && ($tier->max_quantity === null || $quantity <= (float) $tier->max_quantity));
    }
}

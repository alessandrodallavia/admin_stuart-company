<?php

namespace App\Services;

use App\Models\LeadSalesSheet;

class LeadSalesSheetService
{
    public function recalculate(LeadSalesSheet $sheet): void
    {
        $sheet->load(['items.crmProduct.priceTiers', 'items.prints.printType.priceTiers']);

        foreach ($sheet->items as $item) {
            $groupItems = $sheet->items->filter(fn ($candidate) => ($candidate->pricing_group_uuid ?: 'item-'.$candidate->id) === ($item->pricing_group_uuid ?: 'item-'.$item->id));
            $pricingQuantity = (float) $groupItems->sum('quantity');
            $productTier = $item->crmProduct ? $this->tier($item->crmProduct->priceTiers, $pricingQuantity) : null;

            if ($productTier) {
                $item->forceFill([
                    'product_unit_cost' => $productTier->unit_cost ?? $item->crmProduct->unit_cost,
                    'product_unit_price' => $productTier->unit_price,
                ]);
            }

            foreach ($item->prints as $print) {
                $printQuantity = (float) $groupItems
                    ->filter(fn ($groupItem) => $groupItem->prints->contains(fn ($candidate) => $candidate->crm_print_type_id === $print->crm_print_type_id))
                    ->sum('quantity');
                $printTier = $print->printType ? $this->tier($print->printType->priceTiers, $printQuantity) : null;

                if ($printTier) {
                    $print->forceFill(['unit_cost' => $printTier->unit_cost, 'unit_price' => $printTier->unit_price])->save();
                }
            }

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
        $discountAmount = match ($sheet->discount_type) {
            'percentage' => min($productRevenue, $productRevenue * ((float) $sheet->discount_value / 100)),
            'fixed' => min($productRevenue, (float) $sheet->discount_value),
            default => 0,
        };
        $adjustedProductRevenue = max(0, $productRevenue - $discountAmount + (float) $sheet->rounding_adjustment);
        $hasProducts = $sheet->items->isNotEmpty();
        $shippingFee = $hasProducts ? (float) $sheet->shipping_fee : 0;
        $shippingCharge = $hasProducts && $adjustedProductRevenue <= (float) $sheet->free_shipping_threshold ? $shippingFee : 0;
        $shippingCost = $hasProducts ? $shippingFee : 0;
        $revenue = $adjustedProductRevenue + $shippingCharge;
        $cost = $productCost + $shippingCost;
        $margin = $revenue - $cost;
        $sheet->forceFill([
            'product_revenue_total' => $productRevenue,
            'discount_amount' => $discountAmount,
            'shipping_charge' => $shippingCharge,
            'shipping_cost' => $shippingCost,
            'revenue_total' => $revenue,
            'cost_total' => $cost,
            'margin_total' => $margin,
            'margin_percentage' => $revenue > 0 ? ($margin / $revenue) * 100 : 0,
        ])->save();
        $lead = $sheet->lead;
        $lead->update([
            'margin_amount' => $lead->salesSheets()->sum('margin_total'),
            'quantity' => $lead->salesSheets()->with('items')->get()->sum(fn ($order) => $order->items->sum('quantity')),
            'product' => $lead->salesSheets()->with('items')->get()->flatMap->items->map(fn ($item) => $item->configuration_name ?: $item->product_name)->unique()->join(', '),
        ]);
    }

    public function tier($tiers, float $quantity)
    {
        return $tiers->first(fn ($tier) => $quantity >= (float) $tier->min_quantity && ($tier->max_quantity === null || $quantity <= (float) $tier->max_quantity));
    }
}

<?php

namespace App\Services;

use App\Models\LeadSalesSheet;
use App\Models\{CrmPrintType, LeadSalesItem};
use Illuminate\Validation\ValidationException;

class LeadSalesSheetService
{
    public function recalculate(LeadSalesSheet $sheet): void
    {
        $sheet->load(['items.crmProduct.priceTiers', 'items.prints.printType.priceTiers']);

        foreach ($sheet->items as $item) {
            $groupItems = $sheet->items->filter(fn ($candidate) => ($candidate->pricing_group_uuid ?: 'item-'.$candidate->id) === ($item->pricing_group_uuid ?: 'item-'.$item->id));
            $pricingQuantity = (float) $groupItems->sum('quantity');
            $productTier = $item->crmProduct ? $this->tier($item->crmProduct->priceTiers, $pricingQuantity) : null;

            // Gli ordini storici conservano i valori già copiati al momento
            // della vendita; solo i nuovi ordini netti seguono il catalogo.
            $syncCatalogValues = ! (bool) ($sheet->prices_include_vat ?? true);

            if ($productTier && $syncCatalogValues) {
                $item->forceFill([
                    'product_unit_cost' => $productTier->unit_cost ?? $item->crmProduct->unit_cost,
                    'product_unit_price' => $productTier->unit_price,
                ]);
            }

            foreach ($item->prints as $print) {
                if ($syncCatalogValues && $print->printType?->technique === 'setup') {
                    if ($print->printType->fixed_price !== null) $print->forceFill(['unit_price' => $print->printType->fixed_price])->save();
                    continue;
                }
                $printQuantity = (float) $groupItems
                    ->filter(fn ($groupItem) => $groupItem->prints->contains(fn ($candidate) => $candidate->crm_print_type_id === $print->crm_print_type_id))
                    ->sum('quantity');
                $printTier = $print->printType ? $this->tier($print->printType->priceTiers, $printQuantity) : null;

                if ($printTier && $syncCatalogValues) {
                    $print->forceFill(['unit_cost' => $printTier->unit_cost, 'unit_price' => $printTier->unit_price])->save();
                }
            }

            $unitPrints = $syncCatalogValues ? $item->prints->reject(fn ($print) => $print->printType?->technique === 'setup') : $item->prints;
            $setupItems = $syncCatalogValues ? $this->setups($item) : [];
            $setupRevenue = array_sum(array_column($setupItems, 'total_price'));
            $setupCost = array_sum(array_column($setupItems, 'total_cost'));
            $printCost = (float) $unitPrints->sum('unit_cost');
            $printPrice = (float) $unitPrints->sum('unit_price');
            $quantity = (float) $item->quantity;
            $automaticFinalPrice = (float) $item->product_unit_price + $printPrice;
            $finalUnitPrice = $item->final_price_overridden
                ? (float) $item->final_unit_price
                : $automaticFinalPrice;
            $cost = $quantity * ((float) $item->product_unit_cost + $printCost) + $setupCost;
            $revenue = $quantity * $finalUnitPrice + $setupRevenue;
            $item->forceFill([
                'final_unit_price' => $finalUnitPrice,
                'setup_revenue_total' => $setupRevenue, 'setup_cost_total' => $setupCost, 'setup_items' => $setupItems,
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
        $minimumOrderAmount = $hasProducts && $sheet->minimum_order_amount !== null ? (float) $sheet->minimum_order_amount : 0;
        $billableProductRevenue = max($adjustedProductRevenue, $minimumOrderAmount);
        $minimumOrderAdjustment = $billableProductRevenue - $adjustedProductRevenue;
        $shippingFee = $hasProducts ? (float) $sheet->shipping_fee : 0;
        $shippingCharge = $hasProducts && $billableProductRevenue <= (float) $sheet->free_shipping_threshold ? $shippingFee : 0;
        $shippingCost = $hasProducts ? $shippingFee : 0;
        $revenue = $billableProductRevenue + $shippingCharge;
        $cost = $productCost + $shippingCost;
        $margin = $revenue - $cost;
        $sheet->forceFill([
            'product_revenue_total' => $productRevenue,
            'discount_amount' => $discountAmount,
            'minimum_order_adjustment' => $minimumOrderAdjustment,
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

    public function attachPrint(LeadSalesSheet $sheet, LeadSalesItem $item, CrmPrintType $type, string $errorKey = 'print_type_id'): void
    {
        $type->loadMissing('priceTiers');
        $tier = $this->tier($type->priceTiers, (float) $item->quantity);
        if (!$type->is_active) throw ValidationException::withMessages([$errorKey => 'La lavorazione non è attiva.']);
        if (!(bool) ($sheet->prices_include_vat ?? true) && $type->technique === 'setup') {
            if ($type->fixed_price === null) throw ValidationException::withMessages([$errorKey => 'Configura il prezzo unico dell’impianto nel catalogo.']);
            $price = $type->fixed_price;
        } else {
            if (!$tier) throw ValidationException::withMessages([$errorKey => 'Nessuna fascia prezzo disponibile per questa quantità.']);
            $price = $tier->unit_price;
        }
        if (!(bool) ($sheet->prices_include_vat ?? true) && ($type->technique === 'embroidery' || $type->setup_print_type_id)) {
            $setup = CrmPrintType::find($type->setup_print_type_id);
            if (!$setup || !$setup->is_active || $setup->technique !== 'setup' || $setup->fixed_price === null) throw ValidationException::withMessages([$errorKey => 'Configura l’impianto richiesto da questa lavorazione nel catalogo.']);
        }
        $item->prints()->create(['crm_print_type_id' => $type->id, 'print_code' => $type->code, 'print_name' => $type->name, 'unit_cost' => $tier?->unit_cost ?? 0, 'unit_price' => $price]);
    }

    private function setups(LeadSalesItem $item): array
    {
        $explicit = []; $required = [];
        foreach ($item->prints as $print) {
            $type = $print->printType;
            if ($type?->technique === 'setup') $explicit[$type->id][] = $print;
            elseif ($type?->setup_print_type_id) $required[$type->setup_print_type_id] = ($required[$type->setup_print_type_id] ?? 0) + 1;
        }
        $result = [];
        foreach (array_unique([...array_keys($explicit), ...array_keys($required)]) as $id) {
            $type = CrmPrintType::with('priceTiers')->find($id);
            $snapshot = collect($item->setup_items ?? [])->firstWhere('print_type_id', (int) $id);
            $price = $type?->fixed_price ?? $snapshot['unit_price'] ?? null;
            if ($price === null) throw ValidationException::withMessages(['setup' => 'Il prezzo unico di un impianto non è configurato.']);
            $count = max(count($explicit[$id] ?? []), $required[$id] ?? 0);
            $cost = $explicit[$id][0]->unit_cost ?? $type?->priceTiers->first()?->unit_cost ?? $snapshot['unit_cost'] ?? 0;
            $result[] = ['print_type_id' => (int) $id, 'code' => $type?->code ?? $snapshot['code'], 'name' => $type?->name ?? $snapshot['name'], 'quantity' => $count, 'unit_price' => (float) $price, 'unit_cost' => (float) $cost, 'total_price' => round($count * (float) $price,2), 'total_cost' => round($count * (float) $cost,2)];
        }
        return $result;
    }

    public function tier($tiers, float $quantity)
    {
        $matchingTier = $tiers->first(fn ($tier) => $quantity >= (float) $tier->min_quantity && ($tier->max_quantity === null || $quantity <= (float) $tier->max_quantity));

        if ($matchingTier) {
            return $matchingTier;
        }

        // Nell'ordine amministrativo la quantità è libera: sotto il minimo
        // commerciale proponiamo il prezzo della prima fascia disponibile.
        $lowestTier = $tiers->sortBy(fn ($tier) => (float) $tier->min_quantity)->first();

        return $lowestTier && $quantity < (float) $lowestTier->min_quantity
            ? $lowestTier
            : null;
    }
}

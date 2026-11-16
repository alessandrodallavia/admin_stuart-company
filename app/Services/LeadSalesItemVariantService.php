<?php

namespace App\Services;

use App\Models\LeadOrderCheckout;
use App\Models\LeadSalesItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadSalesItemVariantService
{
    public function save(LeadSalesItem $item, array $input, string $errorKey = 'variants'): void
    {
        $item->loadMissing(['crmProduct.colors.sizes', 'crmProduct.sizes']);
        if (! $item->crmProduct) {
            throw ValidationException::withMessages([$errorKey => 'Il prodotto non è collegato al catalogo CRM.']);
        }

        $rows = [];
        foreach ($item->crmProduct->colors as $color) {
            $allowedSizes = $color->sizes_restricted ? $color->sizes : $item->crmProduct->sizes;
            foreach ($allowedSizes as $size) {
                $quantity = round((float) data_get($input, $color->id.'.'.$size->id, 0), 2, PHP_ROUND_HALF_UP);
                if ($quantity <= 0) {
                    continue;
                }
                $rows[] = [
                    'crm_product_color_id' => $color->id,
                    'crm_product_size_id' => $size->id,
                    'color_name' => $color->name,
                    'size_name' => $size->name,
                    'quantity' => $quantity,
                ];
            }
        }

        $total = round((float) collect($rows)->sum('quantity'), 2, PHP_ROUND_HALF_UP);
        if (abs($total - (float) $item->quantity) > 0.001) {
            throw ValidationException::withMessages([
                $errorKey => 'La somma delle taglie e dei colori deve essere '.number_format((float) $item->quantity, 0, ',', '.').' pezzi. Attualmente è '.$this->formatQuantity($total).'.',
            ]);
        }

        DB::transaction(function () use ($item, $rows) {
            $item->variants()->delete();
            foreach ($rows as $row) {
                $item->variants()->create($row);
            }

            $details = collect($rows)->groupBy('color_name')->map(function ($variants, $color) {
                $sizes = $variants->map(fn (array $variant) => $variant['size_name'].'/'.$this->formatQuantity((float) $variant['quantity']))->join(', ');

                return $color.' — '.$sizes;
            })->values()->all();
            $item->forceFill(['colors' => $details])->save();
            $this->synchronizeCheckoutSnapshots($item->fresh('variants'));
        });
    }

    private function synchronizeCheckoutSnapshots(LeadSalesItem $item): void
    {
        $fingerprint = (new OrderCheckoutFingerprint)->forSheet($item->lead_sales_sheet_id);
        $variants = $item->variants->map(fn ($variant) => [
            'color_id' => $variant->crm_product_color_id,
            'color' => $variant->color_name,
            'size_id' => $variant->crm_product_size_id,
            'size' => $variant->size_name,
            'quantity' => (float) $variant->quantity,
        ])->values()->all();
        $details = collect($item->colors)->filter()->join('; ');

        LeadOrderCheckout::query()
            ->where('lead_sales_sheet_id', $item->lead_sales_sheet_id)
            ->whereNotIn('status', ['invalidated', 'expired', 'revoked'])
            ->get()
            ->each(function (LeadOrderCheckout $checkout) use ($item, $variants, $details, $fingerprint) {
                $items = collect($checkout->items)->map(function (array $snapshot) use ($item, $variants, $details) {
                    $matchesId = (int) ($snapshot['sales_item_id'] ?? 0) === $item->id;
                    $matchesLegacySnapshot = empty($snapshot['sales_item_id'])
                        && ($snapshot['code'] ?? null) === $item->product_code
                        && ($snapshot['name'] ?? null) === ($item->configuration_name ?: $item->product_name);
                    if (! $matchesId && ! $matchesLegacySnapshot) {
                        return $snapshot;
                    }

                    return [...$snapshot, 'sales_item_id' => $item->id, 'details' => $details, 'variants' => $variants];
                })->all();
                $checkout->forceFill(['items' => $items, 'order_snapshot_hash' => $fingerprint])->save();
            });
    }

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, fmod($quantity, 1.0) === 0.0 ? 0 : 2, ',', '.');
    }
}

<?php

namespace App\Services;

use App\Models\LeadOrderCheckout;
use App\Models\LeadSalesSheet;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeadOrderCheckoutService
{
    private const VAT_RATE = 22.0;

    public function createForOrder(LeadSalesSheet $sheet, string $paymentType, ?float $depositAmount = null, ?string $expiresAt = null): LeadOrderCheckout
    {
        $this->invalidateStaleForOrder($sheet);
        $sheet->loadMissing(['items.prints', 'items.variants', 'proposals', 'checkouts']);
        $sheet->unsetRelation('checkouts')->load('checkouts');
        $proposal = $sheet->proposals->sortByDesc('id')->first();

        if ($sheet->items->isEmpty()) {
            throw ValidationException::withMessages([
                'checkout' => 'Aggiungi almeno un prodotto prima di generare il checkout.',
            ]);
        }

        $pricesIncludeVat = (bool) ($sheet->prices_include_vat ?? true);
        $orderTotal = $pricesIncludeVat
            ? $this->splitVatIncluded((float) $sheet->revenue_total)
            : $this->addVat((float) $sheet->revenue_total);
        $usableCheckouts = $sheet->checkouts->whereNotIn('status', ['invalidated', 'expired', 'revoked']);
        $existingTypes = $usableCheckouts->pluck('payment_type');
        $orderStatus = $usableCheckouts->pluck('order_status')->first(fn ($status) => $status && $status !== 'new') ?: 'new';

        if ($paymentType === 'full' && $usableCheckouts->isNotEmpty()) {
            throw ValidationException::withMessages(['checkout' => 'Esistono già checkout per questo ordine.']);
        }

        if ($paymentType !== 'full' && $existingTypes->contains('full')) {
            throw ValidationException::withMessages(['checkout' => 'Esiste già un checkout per il pagamento completo.']);
        }

        if ($paymentType === 'deposit') {
            $paymentGross = $this->roundMoney((float) $depositAmount);
            if ($paymentGross <= 0 || $paymentGross >= $orderTotal['gross']) {
                throw ValidationException::withMessages(['deposit_amount' => 'L’acconto deve essere maggiore di zero e inferiore al totale ordine.']);
            }
            $paymentLabel = 'Acconto';
        } elseif ($paymentType === 'balance') {
            if ($existingTypes->contains('balance')) {
                throw ValidationException::withMessages(['checkout' => 'Il checkout del saldo esiste già.']);
            }
            $deposits = (float) $usableCheckouts->where('payment_type', 'deposit')->sum('total_amount');
            if ($deposits <= 0) {
                throw ValidationException::withMessages(['checkout' => 'Crea prima almeno un checkout di acconto.']);
            }
            $paymentGross = $this->roundMoney($orderTotal['gross'] - $deposits);
            if ($paymentGross <= 0) {
                throw ValidationException::withMessages(['checkout' => 'Gli acconti coprono già l’intero totale ordine.']);
            }
            $paymentLabel = 'Saldo';
        } else {
            $paymentGross = $orderTotal['gross'];
            $paymentLabel = 'Pagamento completo';
        }

        $paymentTotal = $this->splitVatIncluded($paymentGross);
        return LeadOrderCheckout::create([
            'lead_id' => $sheet->lead_id,
            'lead_quote_pdf_id' => $proposal?->id,
            'lead_sales_sheet_id' => $sheet->id,
            'token' => $this->uniqueToken(),
            'proposal_number' => $proposal?->proposal_number ?: ($sheet->order_number ?: 'ORD-'.$sheet->id),
            'order_number' => $sheet->order_number,
            'status' => 'draft',
            'order_status' => $orderStatus,
            'order_snapshot_hash' => (new OrderCheckoutFingerprint)->forSheet($sheet->id),
            'expires_at' => $expiresAt ? Carbon::parse($expiresAt)->endOfDay() : now()->addDays(7),
            'payment_type' => $paymentType,
            'payment_label' => $paymentLabel,
            'currency' => 'EUR',
            'vat_rate' => self::VAT_RATE,
            'product_subtotal' => $this->netValue((float) $sheet->product_revenue_total, $pricesIncludeVat),
            'discount_amount' => $this->netValue((float) $sheet->discount_amount, $pricesIncludeVat),
            'rounding_adjustment' => $this->netValue((float) $sheet->rounding_adjustment, $pricesIncludeVat),
            'shipping_amount' => $this->netValue((float) $sheet->shipping_charge, $pricesIncludeVat),
            'order_taxable_amount' => $orderTotal['net'],
            'order_vat_amount' => $orderTotal['vat'],
            'order_total_amount' => $orderTotal['gross'],
            'taxable_amount' => $paymentTotal['net'],
            'vat_amount' => $paymentTotal['vat'],
            'total_amount' => $paymentTotal['gross'],
            'items' => $sheet->items->map(function ($item) use ($pricesIncludeVat) {
                $unit = $this->netValue((float) $item->final_unit_price, $pricesIncludeVat);
                $line = $this->netValue((float) $item->revenue_total, $pricesIncludeVat);
                $details = collect($item->colors ?: [])->filter()->join(', ');

                return [
                    'sales_item_id' => $item->id,
                    'code' => $item->product_code,
                    'name' => $item->configuration_name ?: $item->product_name,
                    'details' => $details,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => $unit,
                    'line_total' => $line,
                    'variants' => $item->variants->map(fn ($variant) => [
                        'color_id' => $variant->crm_product_color_id,
                        'color' => $variant->color_name,
                        'size_id' => $variant->crm_product_size_id,
                        'size' => $variant->size_name,
                        'quantity' => (float) $variant->quantity,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ]);
    }

    public function publicUrl(LeadOrderCheckout $checkout): string
    {
        return rtrim((string) config('services.public_site.url'), '/').'/checkout/'.$checkout->token;
    }

    public function invalidateStaleForOrder(LeadSalesSheet $sheet): void
    {
        $fingerprint = (new OrderCheckoutFingerprint)->forSheet($sheet->id);

        $sheet->checkouts()
            ->whereIn('status', ['draft', 'details_completed', 'bank_transfer_pending'])
            ->get()
            ->each(function (LeadOrderCheckout $checkout) use ($fingerprint) {
                if ($checkout->expires_at?->isPast()) {
                    $checkout->forceFill(['status' => 'expired'])->save();

                    return;
                }

                if (! $checkout->order_snapshot_hash || ! hash_equals($checkout->order_snapshot_hash, $fingerprint)) {
                    $checkout->forceFill(['status' => 'invalidated', 'invalidated_at' => now()])->save();
                }
            });
    }

    private function splitVatIncluded(float $gross): array
    {
        $gross = $this->roundMoney($gross);
        $net = $this->roundMoney($gross / (1 + self::VAT_RATE / 100));

        return ['gross' => $gross, 'net' => $net, 'vat' => $this->roundMoney($gross - $net)];
    }

    private function addVat(float $net): array
    {
        $net = $this->roundMoney($net);
        $gross = $this->roundMoney($net * (1 + self::VAT_RATE / 100));

        return ['gross' => $gross, 'net' => $net, 'vat' => $this->roundMoney($gross - $net)];
    }

    private function roundMoney(float $value): float
    {
        return round(round($value, 3, PHP_ROUND_HALF_UP), 2, PHP_ROUND_HALF_UP);
    }

    private function netValue(float $value, bool $includesVat): float
    {
        return $includesVat ? $this->splitVatIncluded($value)['net'] : $this->roundMoney($value);
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (LeadOrderCheckout::where('token', $token)->exists());

        return $token;
    }

}

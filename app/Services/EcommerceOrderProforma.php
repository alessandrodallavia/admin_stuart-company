<?php
namespace App\Services;
use App\Models\{EcommerceOrder, AdminDocument};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class EcommerceOrderProforma {
    public function create(EcommerceOrder $order): AdminDocument {
        return DB::transaction(function () use ($order) {
            $order = EcommerceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') throw ValidationException::withMessages(['proforma' => 'Ordine annullato.']);
            if ($order->proforma) return $order->proforma;
            if ($order->payment_method !== 'bank' || $order->status === 'paid') throw ValidationException::withMessages(['proforma' => 'Questo ordine non richiede una proforma per bonifico.']);
            $document = app(AdminDocumentService::class)->create($this->data($order));
            $order->update(['admin_document_id' => $document->id]);
            EcommerceOrderDocuments::linkExisting($order, $document);
            return $document;
        });
    }
    public function data(EcommerceOrder $order): array {
            $c = $order->customer;
            $items = collect($order->items)->map(function ($item) {
                $variants = collect($item['rows'])->map(fn ($row) => $row['color'].' · '.$row['size'].' · '.$row['quantity'].' pezzi')->join("\n");
                return ['item_code' => $item['code'], 'description' => $item['name'].' · '.$item['quantity']." pezzi\n".$variants."\n".implode("\n", $item['print_labels']), 'quantity' => 1, 'unit_price' => $item['net'], 'vat_rate' => 22];
            })->values()->all();
            if ($order->shipping_gross > 0) $items[] = ['item_code' => null, 'description' => 'SPESE DI TRASPORTO', 'quantity' => 1, 'unit_price' => round((float) $order->shipping_gross / 1.22, 2), 'vat_rate' => 22];
            $net = round(array_sum(array_column($items, 'unit_price')), 2);
            return [
                'type' => 'proforma', 'document_date' => now()->toDateString(), 'status' => 'draft', 'currency' => 'EUR', 'prices_include_vat' => false,
                'customer_name' => $c['billing_name'], 'customer_email' => $order->email, 'customer_phone' => $c['phone'],
                'customer_tax_code' => $c['billing_tax_code'] ?? $c['billing_company_tax_code'] ?? null, 'customer_vat_number' => $c['billing_vat'] ?? null,
                'customer_recipient_code' => $c['billing_recipient_code'] ?? null, 'customer_pec' => $c['billing_pec'] ?? null,
                'customer_address' => $c['billing_address'], 'customer_city' => $c['billing_city'], 'customer_province' => $c['billing_province'], 'customer_postal_code' => $c['billing_postal_code'], 'customer_country' => 'IT',
                'shipping_name' => $c['shipping_name'], 'shipping_phone' => $c['phone'], 'shipping_address' => $c['shipping_address'], 'shipping_city' => $c['shipping_city'], 'shipping_province' => $c['shipping_province'], 'shipping_postal_code' => $c['shipping_postal_code'], 'shipping_country' => 'IT',
                'notes' => 'Ordine ecommerce '.$order->reference, 'payment_conditions' => 'TP02',
                'rounding_adjustment' => round((float) $order->gross - $net - round($net * .22, 2), 2),
                'items' => $items, 'payments' => [['due_date' => now()->toDateString(), 'payment_method_code' => 'MP05', 'amount' => $order->gross]],
            ];
    }
    public function send(EcommerceOrder $order, AdminDocument $document, string $pdf, string $filename): void {
        DB::transaction(function () use ($order, $document, $pdf, $filename) {
        $order = EcommerceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
        if ($order->proforma_sent_at) return;
        if ($order->status === 'cancelled' || $document->type !== 'proforma' || $document->currency !== 'EUR' || $order->payment_method !== 'bank' || $order->admin_document_id !== $document->id || round((float) $document->total, 2) !== round((float) $order->gross, 2)) throw ValidationException::withMessages(['proforma' => 'La proforma non corrisponde all’ordine.']);
        $body = 'Gentile Cliente, in allegato la proforma relativa all’ordine '.$order->reference.'. Per il bonifico utilizza importo e coordinate indicati nel documento.';
        app(BrevoEmailService::class)->sendTransactionalEmail(['name' => $order->customer_name, 'email' => $order->email], 'Proforma ordine '.$order->reference, '<p>'.e($body).'</p>', $body, ['ecommerce-order', 'proforma'], [['name' => $filename, 'content' => $pdf]]);
        $order->update(['proforma_sent_at' => now(), 'status' => $order->status === 'paid' ? 'paid' : 'bank_transfer_pending']);
        });
    }
}

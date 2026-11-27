<?php

namespace App\Services;

use App\Models\EcommerceOrder;
use App\Support\OrderItemVariantFormatter;

class EcommerceOrderEmailParams
{
    public function build(EcommerceOrder $order): array
    {
        $customer = $order->customer ?? [];
        $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
        return [
            'logo_url' => rtrim((string) config('services.public_site.url'), '/').'/assets/logos/logo-stuart-light.png',
            'card_top_url' => rtrim((string) config('services.public_site.url'), '/').'/assets/images/email/card-top.png',
            'card_bottom_url' => rtrim((string) config('services.public_site.url'), '/').'/assets/images/email/card-bottom.png',
            'received_icon_url' => rtrim((string) config('services.public_site.url'), '/').'/assets/images/email/order-received.png',
            'shipped_icon_url' => rtrim((string) config('services.public_site.url'), '/').'/assets/images/email/'.((($order->fulfillment_method ?? 'shipping') === 'shipping' && ($order->order_status === 'shipped' || in_array($order->fulfillment_status, ['shipped', 'delivered'], true))) ? 'order-received.png' : 'order-pending.png'),
            'customer_name' => $order->customer_name,
            'order_number' => $order->reference,
            'order_date' => $order->created_at->timezone('Europe/Rome')->format('d/m/Y'),
            'items_amount' => $money(collect($order->items)->sum('gross')),
            'shipping_amount' => (float) $order->shipping_gross > 0 ? $money($order->shipping_gross) : 'Gratuita',
            'total_amount' => $money($order->gross),
            'payment_method_label' => $order->payment_method === 'bank' ? 'Bonifico bancario' : 'Pagamento online',
            'payment_received' => $order->status === 'paid',
            'shipping_name' => $customer['shipping_name'] ?? $order->customer_name,
            'shipping_address' => $customer['shipping_address'] ?? '',
            'shipping_postal_code' => $customer['shipping_postal_code'] ?? '',
            'shipping_city' => $customer['shipping_city'] ?? '',
            'shipping_province' => $customer['shipping_province'] ?? '',
            'billing_name' => $customer['billing_name'] ?? $order->customer_name,
            'billing_address' => $customer['billing_address'] ?? '',
            'billing_postal_code' => $customer['billing_postal_code'] ?? '',
            'billing_city' => $customer['billing_city'] ?? '',
            'billing_province' => $customer['billing_province'] ?? '',
            'billing_tax_code' => $customer['billing_tax_code'] ?? $customer['billing_company_tax_code'] ?? '',
            'billing_vat' => $customer['billing_vat'] ?? '',
            'items' => collect($order->items)->map(function ($item, $key) use ($order, $money) {
                return [
                    'name' => $item['name'],
                    'variants' => OrderItemVariantFormatter::proformaDetails(['variants' => $item['rows'] ?? []]),
                    'quantity' => (int) $item['quantity'],
                    'total_amount' => $money($item['gross']),
                    'image_url' => app(EcommerceOrderEmailImage::class)->url($order, (string) $key, $item),
                    'image_alt' => $item['name'].' · '.collect($item['rows'] ?? [])->pluck('color')->unique()->join(', '),
                ];
            })->values()->all(),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\EcommerceOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EcommerceOrderConfirmation
{
    public function __construct(private BrevoEmailService $brevo) {}

    public function send(int $orderId): void
    {
        $lock = Cache::lock('ecommerce-order-confirmation:'.$orderId, 120);
        if (!$lock->get()) throw new RuntimeException('Conferma ordine già in elaborazione.');
        try {
            $order = EcommerceOrder::find($orderId);
            if (!$order || $order->confirmation_sent_at || $order->status === 'cancelled') return;
            if ($order->status !== 'paid' && $order->payment_method !== 'bank') return;
            $params = app(EcommerceOrderEmailParams::class)->build($order);
            $this->brevo->sendTemplateEmail(
                ['name' => $order->customer_name, 'email' => $order->email],
                (int) config('services.brevo.ecommerce_order_received_template_id', 12),
                $params,
                ['ecommerce-order']
            );
            $order->update(['confirmation_sent_at' => now()]);
        } catch (Throwable $exception) {
            Log::error('Conferma ordine ecommerce non inviata', ['order_id' => $orderId, 'exception' => $exception]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }
}

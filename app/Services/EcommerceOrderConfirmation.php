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
            $html = view('emails.ecommerce-order-received', compact('order'))->render();
            $text = html_entity_decode(trim(strip_tags(preg_replace('~<br\s*/?>|</p>~i', "\n", $html))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->brevo->sendTransactionalEmail(['name' => $order->customer_name, 'email' => $order->email], 'Ordine nr. '.$order->reference.' - Conferma ordine', $html, $text, ['ecommerce-order']);
            $order->update(['confirmation_sent_at' => now()]);
        } catch (Throwable $exception) {
            Log::error('Conferma ordine ecommerce non inviata', ['order_id' => $orderId, 'exception' => $exception]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }
}

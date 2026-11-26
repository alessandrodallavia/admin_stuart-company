<?php

namespace App\Services;

use App\Models\EcommerceOrderEvent;
use Illuminate\Support\Facades\Cache;

class EcommerceOrderUpdateNotification
{
    public function __construct(private BrevoEmailService $brevo, private CheckoutOrderCustomerNotificationService $links) {}

    public function send(EcommerceOrderEvent $event): void
    {
   $lock = Cache::lock('ecommerce-update-'.$event->id, 120);
   if (!$lock->get()) return;
   try {
    $event->refresh(); $order = $event->order;
    if (!in_array($event->notification_status, ['pending', 'failed'], true)) return;
    if (!$order || $order->status === 'cancelled') { $event->update(['notification_status' => 'skipped']); return; }
    $carrier = $event->details['carrier'] ?? null;
    $trackingCode = $event->details['tracking'] ?? null;
    $message = \App\Support\OrderCustomerMessage::forEvent($event->kind, $order->reference, $carrier, $trackingCode, $this->links->trackingUrl($carrier, $trackingCode));
    if (!$message) return;
    $this->brevo->sendTransactionalEmail(['name' => $order->customer_name, 'email' => $order->email], $message['subject'], \App\Support\OrderCustomerMessage::html($message['body']), $message['body'], ['ecommerce-order-update']);
    $event->update(['notification_status' => 'sent', 'notification_error' => null, 'notified_at' => now()]);
   } catch (\Throwable $error) { report($error); $event->update(['notification_status' => 'failed', 'notification_error' => 'Invio non riuscito: sarà ritentato.']); }
   finally { $lock->release(); }
    }
}

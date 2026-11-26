<?php
namespace App\Console\Commands;
use App\Models\EcommerceOrderEvent;
use App\Services\{BrevoEmailService, CheckoutOrderCustomerNotificationService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
class SendEcommerceOrderUpdates extends Command {
 protected $signature = 'email:send-ecommerce-updates';
 protected $description = 'Invia gli aggiornamenti operativi degli ordini ecommerce';
 public function handle(BrevoEmailService $brevo, CheckoutOrderCustomerNotificationService $links): int {
  EcommerceOrderEvent::with('order')->whereIn('notification_status',['pending','failed'])->oldest('id')->limit(100)->get()->each(function ($event) use ($brevo,$links) {
   $lock = Cache::lock('ecommerce-update-'.$event->id, 120);
   if (!$lock->get()) return;
   try {
    $event->refresh(); $order = $event->order;
    if ($event->notification_status === 'sent') return;
    if (!$order || $order->status === 'cancelled') { $event->update(['notification_status' => 'skipped']); return; }
    $text = match($event->kind) { 'in_production' => 'Il tuo ordine è in lavorazione.', 'shipped' => 'Il tuo ordine è stato spedito.', 'tracking_updated' => 'I dati di spedizione del tuo ordine sono stati aggiornati.', 'delivered' => 'Il tuo ordine risulta consegnato.', 'collected' => 'Il ritiro del tuo ordine è stato registrato.', default => null };
    if (!$text) return;
    $tracking = $links->trackingUrl($event->details['carrier'] ?? null, $event->details['tracking'] ?? null);
    $body = 'Ordine '.$order->reference."\n\n".$text;
    if (in_array($event->kind,['shipped','tracking_updated'],true)) $body .= "\n\n".trim(($event->details['carrier'] ?? '').' '.($event->details['tracking'] ?? '')).($tracking ? "\nSegui la spedizione: ".$tracking : '');
    $brevo->sendTransactionalEmail(['name' => $order->customer_name, 'email' => $order->email], 'Aggiornamento ordine '.$order->reference, '<p>'.nl2br(e($body)).'</p>', $body, ['ecommerce-order-update']);
    $event->update(['notification_status' => 'sent', 'notification_error' => null, 'notified_at' => now()]);
   } catch (\Throwable $error) { report($error); $event->update(['notification_status' => 'failed', 'notification_error' => 'Invio non riuscito: sarà ritentato.']); }
   finally { $lock->release(); }
  });
  return self::SUCCESS;
 }
}

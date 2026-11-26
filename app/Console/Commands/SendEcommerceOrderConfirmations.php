<?php
namespace App\Console\Commands;
use App\Models\EcommerceOrder;
use App\Services\BrevoEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log};
class SendEcommerceOrderConfirmations extends Command {
    protected $signature = 'email:send-ecommerce-confirmations';
    protected $description = 'Invia le conferme degli ordini ecommerce tramite il servizio Brevo esistente';
    public function handle(BrevoEmailService $brevo): int {
        EcommerceOrder::where('status', '!=', 'cancelled')->whereNull('confirmation_sent_at')->orderBy('id')->limit(100)->get()->each(function ($order) use ($brevo) {
            $lock = Cache::lock('ecommerce-order-confirmation:'.$order->id, 120);
            if (! $lock->get()) return;
            try {
                $order->refresh();
                if ($order->confirmation_sent_at || $order->status === 'cancelled') return;
                $html = view('emails.ecommerce-order-received', compact('order'))->render();
                $brevo->sendTransactionalEmail(['name' => $order->customer_name, 'email' => $order->email], 'Stuart · ordine ricevuto '.$order->reference, $html, trim(strip_tags($html)), ['ecommerce-order']);
                $order->update(['confirmation_sent_at' => now()]);
            } catch (\Throwable $exception) {
                Log::error('Conferma ordine ecommerce non inviata', ['order_id' => $order->id, 'exception' => $exception]);
            } finally { $lock->release(); }
        });
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\EcommerceOrder;
use App\Services\EcommerceOrderConfirmation;
use Illuminate\Console\Command;

class SendEcommerceOrderConfirmations extends Command
{
    protected $signature = 'email:send-ecommerce-confirmations';
    protected $description = 'Recupera le conferme ecommerce non ancora inviate';

    public function handle(EcommerceOrderConfirmation $notifications): int
    {
        EcommerceOrder::where('status', '!=', 'cancelled')->whereNull('confirmation_sent_at')
            ->where(fn ($query) => $query->where('payment_method', 'bank')->orWhere('status', 'paid'))
            ->oldest('id')->limit(100)->get()->each(function ($order) use ($notifications) {
                try { $notifications->send($order->id); }
                catch (\Throwable) { /* Logged by the service; the next attempt may retry. */ }
            });
        return self::SUCCESS;
    }
}

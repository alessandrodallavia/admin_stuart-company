<?php

namespace App\Console\Commands;

use App\Models\EcommerceOrderEvent;
use App\Services\EcommerceOrderUpdateNotification;
use Illuminate\Console\Command;

class SendEcommerceOrderUpdates extends Command
{
    protected $signature = 'email:send-ecommerce-updates';
    protected $description = 'Invia gli aggiornamenti operativi degli ordini ecommerce';

    public function handle(EcommerceOrderUpdateNotification $notifications): int
    {
        EcommerceOrderEvent::with('order')->whereIn('notification_status', ['pending', 'failed'])
            ->oldest('id')->limit(100)->get()->each(fn ($event) => $notifications->send($event));

        return self::SUCCESS;
    }
}

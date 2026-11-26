<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// Shared payload: public dispatches it; only the admin worker handles the admin queue.
class SendEcommerceOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;

    public function __construct(public int $orderId)
    {
        $this->onConnection('database');
        $this->onQueue('admin');
    }

    public function backoff(): array
    {
        return [15, 60, 180, 300];
    }

    public function handle(): void
    {
        app(\App\Services\EcommerceOrderConfirmation::class)->send($this->orderId);
    }
}

<?php

namespace App\Notifications\Channels;

use App\Services\BrevoEmailService;
use Illuminate\Notifications\Notification;

class BrevoAdminEmailChannel
{
    public function __construct(private BrevoEmailService $brevo) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toBrevoAdminEmail') || ! $notifiable->email) {
            return;
        }

        $message = $notification->toBrevoAdminEmail($notifiable);

        if (! $message) {
            return;
        }

        $this->brevo->sendAdminHtmlNotification(
            [
                'name' => $notifiable->name ?: $notifiable->email,
                'email' => $notifiable->email,
            ],
            $message['subject'],
            $message['html'],
            $message['tags'] ?? ['admin-notification'],
        );
    }
}

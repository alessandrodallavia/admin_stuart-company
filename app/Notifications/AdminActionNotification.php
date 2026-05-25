<?php

namespace App\Notifications;

use App\Notifications\Channels\BrevoAdminEmailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminActionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $kind,
        private string $title,
        private string $body,
        private string $url,
        private string $actionLabel = 'Apri',
        private bool $sendEmail = true,
        private array $meta = [],
    ) {
        $this->onQueue('admin');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->sendEmail) {
            $channels[] = BrevoAdminEmailChannel::class;
        }

        return $channels;
    }

    public function viaQueues(): array
    {
        return [
            'database' => 'admin',
            BrevoAdminEmailChannel::class => 'admin',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'action_label' => $this->actionLabel,
            'meta' => $this->meta,
        ];
    }

    public function toBrevoAdminEmail(object $notifiable): array
    {
        return [
            'subject' => $this->title,
            'html' => view('emails.admin-notification', [
                'title' => $this->title,
                'body' => $this->body,
                'actionUrl' => $this->url,
                'actionLabel' => $this->actionLabel,
            ])->render(),
            'tags' => ['admin-notification', $this->kind],
        ];
    }
}

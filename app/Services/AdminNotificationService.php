<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Notifications\AdminActionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AdminNotificationService
{
    public function notifyNewLead(Lead $lead): void
    {
        $name = $this->leadName($lead);

        $this->notifyPermission(
            'leads.view',
            new AdminActionNotification(
                kind: 'new-lead',
                title: "Nuovo lead: {$name}",
                body: $this->leadSummary($lead),
                url: route('admin.leads.index', ['lead' => $lead]),
                actionLabel: 'Apri lead',
                sendEmail: false,
                meta: ['lead_id' => $lead->id],
            ),
        );
    }

    public function notifyPaymentCompleted(Lead $lead): void
    {
        $name = $this->leadName($lead);
        $amount = $lead->payment_amount ? number_format((float) $lead->payment_amount, 2, ',', '.').' EUR' : 'importo non indicato';

        $this->notifyPermission(
            'leads.view',
            new AdminActionNotification(
                kind: 'payment-completed',
                title: "Pagamento completato: {$name}",
                body: "Pagamento ricevuto per {$name}. Importo: {$amount}.",
                url: route('admin.leads.index', ['lead' => $lead]),
                actionLabel: 'Apri lead',
                sendEmail: false,
                meta: ['lead_id' => $lead->id, 'amount' => $lead->payment_amount],
            ),
        );
    }

    public function notifyWhatsappMessage(WhatsappConversation $conversation, WhatsappMessage $message): void
    {
        $label = $conversation->lead ? $this->leadName($conversation->lead) : $conversation->contact_phone;
        $body = $message->body
            ? Str::limit($message->body, 180)
            : 'Ha inviato un nuovo contenuto WhatsApp.';

        $this->notifyPermission(
            'whatsapp.view',
            new AdminActionNotification(
                kind: 'whatsapp-message',
                title: "Nuovo messaggio WhatsApp da {$label}",
                body: $body,
                url: route('admin.conversations.show', ['conversation' => $conversation]),
                actionLabel: 'Apri conversazione',
                sendEmail: false,
                meta: [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ],
            ),
        );
    }

    private function notifyPermission(string $permission, AdminActionNotification $notification): void
    {
        $recipients = $this->recipients($permission);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }

    private function recipients(string $permission): Collection
    {
        return AdminUser::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get()
            ->filter(fn (AdminUser $user) => $user->hasAdminPermission($permission))
            ->values();
    }

    private function leadName(Lead $lead): string
    {
        return $lead->name ?: $lead->club ?: $lead->phone ?: "Lead #{$lead->id}";
    }

    private function leadSummary(Lead $lead): string
    {
        return collect([
            $lead->club,
            $lead->city,
            $lead->phone,
            $lead->email,
        ])->filter()->implode(' - ') ?: 'Nuova richiesta ricevuta.';
    }
}

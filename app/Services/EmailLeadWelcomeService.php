<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailConversation;
use App\Models\Lead;
use App\Support\MessageTemplates;

class EmailLeadWelcomeService
{
    public function __construct(private EmailMailboxService $mailbox) {}

    public function send(Lead $lead): bool
    {
        if (! $lead->email || $lead->email_welcome_sent_at) {
            return false;
        }

        $account = $this->account();

        if (! $account) {
            $lead->forceFill([
                'email_welcome_status' => 'waiting_account',
                'email_welcome_error' => 'Nessuna casella email attiva configurata.',
            ])->save();

            return false;
        }

        $template = MessageTemplates::current()[0] ?? null;
        $body = $template['message'] ?? 'Ciao, sono Andrea di Stuart. Ho ricevuto la tua richiesta e ti scrivo per approfondire il progetto.';
        $conversation = EmailConversation::firstOrCreate(
            [
                'email_account_id' => $account->id,
                'lead_id' => $lead->id,
            ],
            [
                'assigned_user_id' => $account->admin_user_id,
                'subject' => 'La tua richiesta a Stuart Company',
                'contact_email' => $lead->email,
                'contact_name' => $lead->name,
                'status' => 'open',
                'is_seen' => true,
                'last_message_at' => now(),
            ],
        );

        $message = $this->mailbox->send($account, $conversation, $body);

        $lead->forceFill([
            'email_welcome_sent_at' => $message->status === 'sent' ? now() : null,
            'email_welcome_status' => $message->status,
            'email_welcome_error' => $message->error_message,
        ])->save();

        return $message->status === 'sent';
    }

    private function account(): ?EmailAccount
    {
        return EmailAccount::query()
            ->where('email_accounts.is_active', true)
            ->whereHas('adminUser', fn ($query) => $query->where('is_active', true))
            ->with('adminUser')
            ->join('admin_users', 'admin_users.id', '=', 'email_accounts.admin_user_id')
            ->orderByRaw("CASE WHEN admin_users.role = 'operator' THEN 0 ELSE 1 END")
            ->select('email_accounts.*')
            ->first();
    }
}

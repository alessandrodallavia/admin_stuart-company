<?php

namespace App\Console\Commands;

use App\Models\WhatsappFollowUp;
use Illuminate\Console\Command;

class SendWhatsappFollowUps extends Command
{
    protected $signature = 'whatsapp:sync-follow-ups {--limit=50}';

    protected $description = 'Aggiorna i promemoria follow-up WhatsApp senza inviare messaggi automatici.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $deleted = 0;
        $due = 0;

        WhatsappFollowUp::query()
            ->with(['conversation', 'triggerMessage'])
            ->where('status', 'pending')
            ->whereHas('conversation', fn ($query) => $query
                ->where('status', 'open')
                ->where('follow_up_excluded_permanently', false)
                ->where(fn ($query) => $query
                    ->whereNull('follow_up_excluded_until')
                    ->orWhere('follow_up_excluded_until', '<=', now())
                )
            )
            ->orderBy('due_at')
            ->limit($limit)
            ->get()
            ->each(function (WhatsappFollowUp $followUp) use (&$deleted, &$due) {
                $conversation = $followUp->conversation;

                if (! $conversation || $conversation->isExcludedFromFollowUps()) {
                    return;
                }

                if ($this->shouldCancelBecauseCustomerReplied($followUp)) {
                    $followUp->delete();
                    $deleted++;

                    return;
                }

                if ($followUp->due_at->isPast()) {
                    $due++;
                }
            });

        $this->info("Follow-up dovuti: {$due}. Rimossi per risposta cliente: {$deleted}.");

        return self::SUCCESS;
    }

    private function shouldCancelBecauseCustomerReplied(WhatsappFollowUp $followUp): bool
    {
        if (! $followUp->auto_generated || ! $followUp->trigger_message_id || ! $followUp->conversation) {
            return false;
        }

        $triggerMessage = $followUp->triggerMessage;

        if (! $triggerMessage) {
            return false;
        }

        $triggeredAt = $triggerMessage->sent_at ?? $triggerMessage->created_at;

        return $followUp->conversation->messages()
            ->where('direction', 'inbound')
            ->where(function ($query) use ($triggeredAt) {
                $query->where('received_at', '>', $triggeredAt)
                    ->orWhere(function ($query) use ($triggeredAt) {
                        $query->whereNull('received_at')
                            ->where('created_at', '>', $triggeredAt);
                    });
            })
            ->exists();
    }
}

<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Support\MessageTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SendAutomaticWhatsappReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 5;

    public int $uniqueFor = 300;

    public function __construct(
        public int $leadId,
        public int $conversationId,
        public string $to,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->conversationId;
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("whatsapp-auto-reply:{$this->conversationId}"))
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        $lead = Lead::withoutGlobalScope('training')->find($this->leadId);
        $conversation = WhatsappConversation::withoutGlobalScope('training')->find($this->conversationId);

        if (! $lead || ! $conversation) {
            return;
        }

        // A manual handoff or another completed reply during the delay must win.
        if ($lead->status !== 'confirmed' || $conversation->mode !== 'auto') {
            return;
        }

        $isLiveMockupRequest = (bool) $lead->live_mockup_used && $lead->cta_origin === 'live_mockup';
        $templateTitle = match (true) {
            $isLiveMockupRequest => 'Risposta iniziale anteprima live',
            (bool) $lead->calculator_used => 'Risposta iniziale calcolatore',
            default => 'Risposta iniziale',
        };
        $body = MessageTemplates::initialReply(
            (bool) $lead->calculator_used,
            $isLiveMockupRequest,
        );

        if (! is_string($body) || trim($body) === '') {
            throw new RuntimeException("Template \"{$templateTitle}\" non configurato per il periodo corrente.");
        }
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->to,
            'type' => 'text',
            'text' => ['body' => $body],
        ];

        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/messages', $payload);

        $this->storeOutgoingMessage($conversation, $body, $payload, $response);

        // Keep the lead eligible for a retry until Meta has accepted the message.
        $response->throw();

        $lead->forceFill(['status' => 'completed'])->save();
        $this->requestHumanHandoff($conversation, 'Lead completato: prepara mockup e proposta.');
    }

    public function failed(?Throwable $exception): void
    {
        $conversation = WhatsappConversation::withoutGlobalScope('training')->find($this->conversationId);

        if ($conversation && $conversation->mode === 'auto') {
            $this->requestHumanHandoff(
                $conversation,
                'Risposta automatica non inviata dopo i tentativi previsti: contattare manualmente il lead.'
            );
        }
    }

    private function storeOutgoingMessage(WhatsappConversation $conversation, string $body, array $payload, Response $response): void
    {
        $message = WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $response->json('messages.0.id'),
            'direction' => 'outbound',
            'source' => 'automation',
            'type' => 'text',
            'status' => $response->successful() ? 'sent' : 'failed',
            'from_phone' => config('services.whatsapp.phone_number_id'),
            'to_phone' => $this->to,
            'body' => $body,
            'payload' => [
                'request' => $payload,
                'response' => $response->json(),
            ],
            'error_code' => $response->json('error.code'),
            'error_message' => $response->json('error.message'),
            'sent_at' => $response->successful() ? now() : null,
            'failed_at' => $response->failed() ? now() : null,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
    }

    private function requestHumanHandoff(WhatsappConversation $conversation, string $reason): void
    {
        $metadata = $conversation->metadata ?? [];
        $metadata['handoff_reason'] = $reason;

        $conversation->forceFill([
            'mode' => 'manual',
            'needs_human' => true,
            'human_requested_at' => now(),
            'manual_started_at' => $conversation->manual_started_at ?? now(),
            'metadata' => $metadata,
        ])->save();
    }
}

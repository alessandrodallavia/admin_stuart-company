<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\AdminNotificationService;
use App\Services\BrevoEmailService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessWhatsappWebhookJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public $data) {}

    public function handle(BrevoEmailService $emailService, AdminNotificationService $adminNotifications)
    {
        $value = $this->data['entry'][0]['changes'][0]['value'] ?? null;

        if (! $value) {
            return;
        }

        if (isset($value['statuses'])) {
            $this->handleStatuses($value['statuses']);
        }

        if (! isset($value['messages'])) {
            return;
        }

        foreach ($value['messages'] as $message) {

            $from = $message['from'] ?? null;
            $type = $message['type'] ?? null;

            if (! $from) {
                continue;
            }

            // 🚫 evita loop su tuoi messaggi (opzionale)
            if (($message['from'] ?? null) === config('services.whatsapp.phone_number_id')) {
                continue;
            }

            $conversation = $this->getConversation($from);
            $lead = $conversation->lead ?: $this->findLead($message, $from);

            if ($lead && ! $conversation->lead_id) {
                $conversation->forceFill(['lead_id' => $lead->id])->save();
                $conversation->setRelation('lead', $lead);
            }

            $storedMessage = $this->storeIncomingMessage($conversation, $message, $from);
            $this->deleteAutomaticFollowUpsAfterCustomerReply($conversation);

            if ($conversation->mode === 'manual') {
                if ($storedMessage->wasRecentlyCreated) {
                    $adminNotifications->notifyWhatsappMessage($conversation->fresh(['lead']), $storedMessage);
                }

                continue;
            }

            if ($type === 'button') {
                $this->handleButton($message, $from, $conversation);
            }

            if ($type === 'text') {
                $this->handleText($message, $from, $emailService, $lead, $conversation);
            }

            $conversation = $conversation->fresh(['lead']);

            if ($storedMessage->wasRecentlyCreated && $conversation->mode === 'manual') {
                $adminNotifications->notifyWhatsappMessage($conversation, $storedMessage);
            }
        }
    }

    private function handleStatuses(array $statuses): void
    {
        foreach ($statuses as $statusPayload) {
            $providerMessageId = $statusPayload['id'] ?? null;

            if (! $providerMessageId) {
                continue;
            }

            $message = WhatsappMessage::where('provider_message_id', $providerMessageId)->first();

            if (! $message) {
                continue;
            }

            $status = $statusPayload['status'] ?? null;
            $statusAt = isset($statusPayload['timestamp'])
                ? Carbon::createFromTimestamp((int) $statusPayload['timestamp'])
                : now();

            $updates = [
                'status' => $status,
                'payload' => $this->mergePayload($message->payload, 'status_webhooks', $statusPayload),
            ];

            if ($status === 'sent') {
                $updates['sent_at'] = $message->sent_at ?? $statusAt;
            }

            if ($status === 'delivered') {
                $updates['sent_at'] = $message->sent_at ?? $statusAt;
                $updates['delivered_at'] = $statusAt;
            }

            if ($status === 'read') {
                $updates['sent_at'] = $message->sent_at ?? $statusAt;
                $updates['delivered_at'] = $message->delivered_at ?? $statusAt;
                $updates['read_at'] = $statusAt;
            }

            if ($status === 'failed') {
                $updates['failed_at'] = $statusAt;
                $updates['error_code'] = $statusPayload['errors'][0]['code'] ?? $message->error_code;
                $updates['error_message'] = $statusPayload['errors'][0]['message'] ?? $message->error_message;
            }

            $message->forceFill($updates)->save();
        }
    }

    private function handleButton($message, $from, WhatsappConversation $conversation)
    {
        $payload = $message['button']['payload'] ?? null;

        if ($payload === 'Vedi il tuo ordine') {
            $this->sendText($from, "Ecco il tuo ordine 👇\nhttps://tuosito.it/ordine/123", $conversation);
        }

        if ($payload === 'Modifica il tuo ordine') {
            $this->sendText($from, 'Come vuoi modificarlo?', $conversation);
        }
    }

    private function handleText($message, $from, $emailService, ?Lead $lead, WhatsappConversation $conversation)
    {
        $text = $message['text']['body'] ?? '';

        if (! $lead) {
            $this->requestHumanHandoff($conversation, 'Lead non trovato: conversazione in modalità manuale.');

            return; // nessun lead → ignora
        }

        // salva telefono se manca
        if ($lead->phone !== $from) {
            $lead->phone = $from;
            $lead->save();
        }

        if (! $conversation->lead_id) {
            $conversation->forceFill(['lead_id' => $lead->id])->save();
        }

        if (! $lead->whatsapp_conversation_id) {
            $lead->forceFill(['whatsapp_conversation_id' => $conversation->id])->save();
        }

        // 🔥 FLOW
        switch ($lead->status) {

            case 'pre':
                $lead->status = 'confirmed';
                $lead->message = $text;
                $lead->save();

                // no break: appena parte la risposta automatica il lead passa a completed.

            case 'confirmed':

                $this->sendText($from, "Ciao 👋 Sono Andrea di Stuart.\nHo visto la tua richiesta per delle t-shirt personalizzate 👕\n\nPer iniziare mandami pure:\n– logo o grafica\n– colore delle t-shirt\n\n👉 Se hai già il logo in buona qualità puoi inviarmelo direttamente qui 👍", $conversation);

                $lead->status = 'completed';
                $lead->save();

                if (! $lead->pipeline_lead_id) {
                    $this->createDeal($lead->fresh(), $emailService);
                }

                $this->requestHumanHandoff($conversation, 'Lead completato: prepara mockup e proposta.');

                break;
        }
    }

    private function sendText($to, $body, ?WhatsappConversation $conversation = null)
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $body,
            ],
        ];

        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/messages', $payload);

        if (! $conversation) {
            $conversation = $this->getConversation($to);
        }

        $this->storeOutgoingMessage($conversation, 'text', $to, $body, $payload, $response);
    }

    private function storeOutgoingMessage(WhatsappConversation $conversation, string $type, string $to, ?string $body, array $payload, $response): void
    {
        $message = WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $response->json('messages.0.id'),
            'direction' => 'outbound',
            'source' => 'automation',
            'type' => $type,
            'status' => $response->successful() ? 'sent' : 'failed',
            'from_phone' => config('services.whatsapp.phone_number_id'),
            'to_phone' => $to,
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

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
        ])->save();
    }

    private function findLead(array $message, string $from): ?Lead
    {
        $text = $message['text']['body'] ?? '';

        preg_match('/ID richiesta:\s*([A-Z0-9]+)/i', $text, $matches);
        $uuid = $matches[1] ?? null;

        if ($uuid) {
            $lead = Lead::where('uuid', $uuid)->first();

            if ($lead) {
                return $lead;
            }
        }

        return Lead::where('phone', $from)->latest()->first();
    }

    private function getConversation(string $contactPhone, ?Lead $lead = null): WhatsappConversation
    {
        $conversation = WhatsappConversation::where('contact_phone', $contactPhone)
            ->where('status', 'open')
            ->latest()
            ->first();

        if ($conversation) {
            if ($lead && ! $conversation->lead_id) {
                $conversation->forceFill(['lead_id' => $lead->id])->save();
            }

            if ($lead && ! $lead->whatsapp_conversation_id) {
                $lead->forceFill(['whatsapp_conversation_id' => $conversation->id])->save();
            }

            return $conversation;
        }

        $conversation = WhatsappConversation::create([
            'lead_id' => $lead?->id,
            'contact_phone' => $contactPhone,
            'business_phone' => config('services.whatsapp.phone_number_id'),
            'mode' => 'auto',
            'status' => 'open',
        ]);

        if ($lead && ! $lead->whatsapp_conversation_id) {
            $lead->forceFill(['whatsapp_conversation_id' => $conversation->id])->save();
        }

        return $conversation;
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

    private function storeIncomingMessage(WhatsappConversation $conversation, array $message, string $from): WhatsappMessage
    {
        $receivedAt = isset($message['timestamp'])
            ? Carbon::createFromTimestamp((int) $message['timestamp'])
            : now();

        $providerMessageId = $message['id'] ?? null;
        $body = $this->extractMessageBody($message);
        $existingMessage = $providerMessageId
            ? WhatsappMessage::where('provider_message_id', $providerMessageId)->first()
            : null;
        $mediaAttributes = $existingMessage?->media_path
            ? $this->existingMediaAttributes($existingMessage)
            : $this->downloadIncomingMedia($message, $conversation);

        $attributes = [
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'source' => 'webhook',
            'type' => $message['type'] ?? 'unknown',
            'status' => 'received',
            'from_phone' => $from,
            'to_phone' => config('services.whatsapp.phone_number_id'),
            'body' => $body,
            ...$mediaAttributes,
            'payload' => $message,
            'received_at' => $receivedAt,
            'admin_read_at' => $conversation->mode === 'manual' ? null : now(),
        ];

        if ($providerMessageId) {
            if ($existingMessage) {
                $attributes['admin_read_at'] = $existingMessage->admin_read_at;
            }

            $storedMessage = WhatsappMessage::updateOrCreate(
                ['provider_message_id' => $providerMessageId],
                $attributes
            );
        } else {
            $storedMessage = WhatsappMessage::create($attributes);
        }

        $conversation->forceFill([
            'last_message_at' => $storedMessage->received_at ?? $storedMessage->created_at,
        ])->save();

        return $storedMessage;
    }

    private function deleteAutomaticFollowUpsAfterCustomerReply(WhatsappConversation $conversation): void
    {
        $conversation->followUps()
            ->where('auto_generated', true)
            ->where('status', 'pending')
            ->delete();
    }

    private function existingMediaAttributes(WhatsappMessage $message): array
    {
        return [
            'media_id' => $message->media_id,
            'media_disk' => $message->media_disk,
            'media_path' => $message->media_path,
            'media_mime_type' => $message->media_mime_type,
            'media_filename' => $message->media_filename,
            'media_size' => $message->media_size,
        ];
    }

    private function downloadIncomingMedia(array $message, WhatsappConversation $conversation): array
    {
        $type = $message['type'] ?? null;

        if (! in_array($type, ['image', 'document', 'audio', 'video', 'sticker'], true)) {
            return [];
        }

        $media = $message[$type] ?? [];
        $mediaId = $media['id'] ?? null;

        if (! $mediaId) {
            return [];
        }

        try {
            $mediaResponse = Http::withToken(config('services.whatsapp.token'))
                ->get("https://graph.facebook.com/v25.0/{$mediaId}");

            if ($mediaResponse->failed()) {
                Log::warning('Impossibile recuperare media WhatsApp', [
                    'media_id' => $mediaId,
                    'response' => $mediaResponse->json(),
                ]);

                return ['media_id' => $mediaId];
            }

            $mediaUrl = $mediaResponse->json('url');
            $mimeType = $mediaResponse->json('mime_type') ?: ($media['mime_type'] ?? null);
            $filename = $this->mediaFilename($media, $type, $mediaId, $mimeType);

            if (! $mediaUrl) {
                return [
                    'media_id' => $mediaId,
                    'media_mime_type' => $mimeType,
                    'media_filename' => $filename,
                    'media_size' => $mediaResponse->json('file_size') ?: null,
                ];
            }

            $fileResponse = Http::withToken(config('services.whatsapp.token'))->get($mediaUrl);

            if ($fileResponse->failed()) {
                Log::warning('Impossibile scaricare file WhatsApp', [
                    'media_id' => $mediaId,
                    'response' => $fileResponse->status(),
                ]);

                return [
                    'media_id' => $mediaId,
                    'media_mime_type' => $mimeType,
                    'media_filename' => $filename,
                    'media_size' => $mediaResponse->json('file_size') ?: null,
                ];
            }

            $path = "whatsapp/{$conversation->id}/{$mediaId}-".Str::slug(pathinfo($filename, PATHINFO_FILENAME));
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $path .= $extension ? ".{$extension}" : '';

            Storage::disk('public')->put($path, $fileResponse->body());

            return [
                'media_id' => $mediaId,
                'media_disk' => 'public',
                'media_path' => $path,
                'media_mime_type' => $mimeType,
                'media_filename' => $filename,
                'media_size' => $mediaResponse->json('file_size') ?: strlen($fileResponse->body()),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Errore download media WhatsApp', [
                'media_id' => $mediaId,
                'message' => $exception->getMessage(),
            ]);

            return ['media_id' => $mediaId];
        }
    }

    private function mediaFilename(array $media, string $type, string $mediaId, ?string $mimeType): string
    {
        if (! empty($media['filename'])) {
            return $media['filename'];
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'video/mp4' => 'mp4',
            default => null,
        };

        return $extension ? "{$type}-{$mediaId}.{$extension}" : "{$type}-{$mediaId}";
    }

    private function mergePayload(?array $payload, string $key, array $item): array
    {
        $payload ??= [];
        $payload[$key] ??= [];
        $payload[$key][] = $item;

        return $payload;
    }

    private function extractMessageBody(array $message): ?string
    {
        return match ($message['type'] ?? null) {
            'text' => $message['text']['body'] ?? null,
            'image' => $message['image']['caption'] ?? null,
            'document' => $message['document']['caption'] ?? $message['document']['filename'] ?? null,
            'audio' => $message['audio']['caption'] ?? null,
            'video' => $message['video']['caption'] ?? null,
            'button' => $message['button']['text'] ?? $message['button']['payload'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? null,
            default => null,
        };
    }

    private function sendList($to, ?WhatsappConversation $conversation = null)
    {
        $body = 'Cosa vuoi modificare?';
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => [
                    'text' => $body,
                ],
                'action' => [
                    'button' => 'Scegli',
                    'sections' => [
                        [
                            'title' => 'Modifica ordine',
                            'rows' => [
                                [
                                    'id' => 'modifica_taglia',
                                    'title' => 'Modifica taglia',
                                ],
                                [
                                    'id' => 'modifica_colore',
                                    'title' => 'Modifica colore',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/messages', $payload);

        if (! $conversation) {
            $conversation = $this->getConversation($to);
        }

        $this->storeOutgoingMessage($conversation, 'interactive', $to, $body, $payload, $response);
    }

    private function createDeal($lead, $emailService)
    {
        $contactId = null;

        if ($lead->email) {
            $contactId = $emailService->syncLeadContact($lead);
        }

        $responseDeal = $emailService->createDealForLead($lead, $contactId);

        if (! empty($responseDeal['id'])) {
            Lead::where('id', $lead->id)->update([
                'pipeline_lead_id' => $responseDeal['id'],
            ]);
        }
    }
}

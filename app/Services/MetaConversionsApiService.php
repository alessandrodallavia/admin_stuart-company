<?php

namespace App\Services;

use App\Models\Lead;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaConversionsApiService
{
    public function trackLead(Lead $lead): bool
    {
        return $this->track($lead, 'Lead', 'lead', now(), [
            'currency' => 'EUR',
            'value' => (float) ($lead->quote_amount ?: 0),
            'content_name' => 'Preventivo inviato',
            'content_category' => 'Preventivo',
        ]);
    }

    public function trackContact(Lead $lead, ?CarbonInterface $eventTime = null): bool
    {
        return $this->track($lead, 'Contact', 'contact', $eventTime, [
            'content_name' => 'Messaggio WhatsApp ricevuto',
            'content_category' => 'Lead',
        ]);
    }

    public function trackInitiateCheckout(Lead $lead): bool
    {
        $value = (float) ($lead->payment_amount ?: $lead->quote_amount ?: 0);

        return $this->track($lead, 'InitiateCheckout', 'initiate_checkout', now(), [
            'currency' => 'EUR',
            'value' => $value,
            'content_name' => 'Link di pagamento inviato',
            'content_category' => 'Pagamento',
        ]);
    }

    public function trackPurchase(Lead $lead): bool
    {
        $value = (float) ($lead->payment_amount ?: $lead->quote_amount ?: 0);
        $itemId = (string) ($lead->quote_number ?: "lead-{$lead->id}");

        return $this->track($lead, 'Purchase', 'purchase', now(), [
            'currency' => 'EUR',
            'value' => $value,
            'content_ids' => [$itemId],
            'content_type' => 'product',
            'contents' => [[
                'id' => $itemId,
                'quantity' => 1,
                'item_price' => $value,
            ]],
        ]);
    }

    private function track(
        Lead $lead,
        string $eventName,
        string $fieldPrefix,
        ?CarbonInterface $eventTime,
        array $customData
    ): bool {
        $sentAtField = "meta_{$fieldPrefix}_sent_at";
        $statusField = "meta_{$fieldPrefix}_status";
        $errorField = "meta_{$fieldPrefix}_error";

        if (! $lead->meta_marketing_consent || $lead->{$sentAtField}) {
            return false;
        }

        try {
            $this->send($lead, $eventName, $eventTime ?? now(), $customData);

            $lead->forceFill([
                $sentAtField => now(),
                $statusField => 'sent',
                $errorField => null,
            ])->save();

            return true;
        } catch (\Throwable $exception) {
            Log::warning("Invio {$eventName} a Meta Conversions API fallito", [
                'lead_id' => $lead->id,
                'lead_uuid' => $lead->uuid,
                'error' => $exception->getMessage(),
            ]);

            $lead->forceFill([
                $statusField => 'failed',
                $errorField => $exception->getMessage(),
            ])->save();

            return false;
        }
    }

    private function send(Lead $lead, string $eventName, CarbonInterface $eventTime, array $customData): void
    {
        $pixelId = config('services.meta.pixel_id');
        $token = config('services.meta.conversions_api_token');

        if (! $pixelId || ! $token) {
            throw new RuntimeException('Credenziali Meta Conversions API mancanti.');
        }

        $userData = array_filter([
            'em' => $lead->email ? [$this->hash($lead->email)] : null,
            'ph' => $lead->phone ? [$this->hash(preg_replace('/\D+/', '', $lead->phone))] : null,
            'external_id' => [$this->hash($lead->uuid ?: (string) $lead->id)],
            'client_ip_address' => $lead->ip,
            'client_user_agent' => $lead->user_agent,
            'fbp' => $lead->meta_fbp,
            'fbc' => $lead->meta_fbc ?: $this->fbcFromLead($lead),
        ], fn ($value) => filled($value));

        $version = config('services.meta.graph_api_version', 'v25.0');

        try {
            $response = Http::asJson()
                ->withToken($token)
                ->timeout(10)
                ->post("https://graph.facebook.com/{$version}/{$pixelId}/events", [
                    'data' => [[
                        'event_name' => $eventName,
                        'event_time' => $eventTime->timestamp,
                        'event_id' => "meta_{$eventName}_".($lead->uuid ?: $lead->id),
                        'event_source_url' => $lead->landing_page ?: config('services.public_site.url'),
                        'action_source' => 'website',
                        'user_data' => $userData,
                        'custom_data' => $customData,
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Meta Conversions API non raggiungibile: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Meta ha risposto con HTTP '.$response->status().': '.$response->body());
        }
    }

    private function hash(string $value): string
    {
        return hash('sha256', mb_strtolower(trim($value)));
    }

    private function fbcFromLead(Lead $lead): ?string
    {
        if (! $lead->fbclid) {
            return null;
        }

        return sprintf('fb.1.%d.%s', $lead->created_at?->timestamp ?? now()->timestamp, $lead->fbclid);
    }
}

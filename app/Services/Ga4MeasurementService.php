<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Ga4MeasurementService
{
    public function sendQuoteSent(Lead $lead): void
    {
        $this->sendLeadEvent($lead, 'quote_sent', [
            'value' => (float) ($lead->quote_amount ?: 0),
            'quote_amount' => (float) ($lead->quote_amount ?: 0),
        ]);
    }

    public function sendPaymentLinkSent(Lead $lead): void
    {
        $this->sendLeadEvent($lead, 'payment_link_sent', [
            'value' => (float) ($lead->payment_amount ?: $lead->quote_amount ?: 0),
            'payment_amount' => (float) ($lead->payment_amount ?: 0),
            'quote_amount' => (float) ($lead->quote_amount ?: 0),
        ]);
    }

    public function sendPurchase(Lead $lead): void
    {
        $value = (float) ($lead->payment_amount ?: $lead->quote_amount ?: 0);

        $this->sendLeadEvent($lead, 'purchase', [
            'transaction_id' => (string) ($lead->quote_number ?: "lead-{$lead->id}"),
            'value' => $value,
            'payment_amount' => $value,
            'quote_amount' => (float) ($lead->quote_amount ?: 0),
            'items' => [
                [
                    'item_id' => (string) ($lead->quote_number ?: "lead-{$lead->id}"),
                    'item_name' => 'Ordine personalizzato',
                    'price' => $value,
                    'quantity' => 1,
                ],
            ],
        ]);
    }

    private function sendLeadEvent(Lead $lead, string $eventName, array $extraParams = []): void
    {
        $measurementId = (string) config('services.ga4.measurement_id');
        $apiSecret = (string) config('services.ga4.api_secret');

        if (! $measurementId || ! $apiSecret) {
            throw new RuntimeException('Credenziali GA4 Measurement Protocol mancanti.');
        }

        $clientId = $lead->ga_client_id ?: $this->fallbackClientId($lead);
        $params = [
            'currency' => 'EUR',
            'lead_id' => (string) $lead->id,
            'lead_uuid' => (string) $lead->uuid,
            'quote_number' => (string) $lead->quote_number,
            'engagement_time_msec' => 1,
        ] + $extraParams;

        if ($lead->ga_session_id) {
            $params['session_id'] = (int) $lead->ga_session_id;
        }

        if ($lead->utm_source) {
            $params['source'] = $lead->utm_source;
        }

        if ($lead->utm_medium) {
            $params['medium'] = $lead->utm_medium;
        }

        if ($lead->utm_campaign) {
            $params['campaign'] = $lead->utm_campaign;
        }

        if (config('services.ga4.debug_mode')) {
            $params['debug_mode'] = true;
        }

        $payload = [
            'client_id' => $clientId,
            'timestamp_micros' => now()->getTimestampMs() * 1000,
            'events' => [
                [
                    'name' => $eventName,
                    'params' => $params,
                ],
            ],
        ];

        try {
            $response = Http::asJson()
                ->withQueryParameters([
                    'measurement_id' => $measurementId,
                    'api_secret' => $apiSecret,
                ])
                ->timeout(10)
                ->post('https://www.google-analytics.com/mp/collect', $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('GA4 non raggiungibile: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            Log::warning("Invio {$eventName} a GA4 fallito", [
                'lead_id' => $lead->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('GA4 ha risposto con HTTP '.$response->status().'.');
        }
    }

    private function fallbackClientId(Lead $lead): string
    {
        return sprintf('%d.%d', $lead->id, $lead->created_at?->timestamp ?? now()->timestamp);
    }
}

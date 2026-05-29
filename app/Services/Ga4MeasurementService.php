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
        $measurementId = (string) config('services.ga4.measurement_id');
        $apiSecret = (string) config('services.ga4.api_secret');

        if (! $measurementId || ! $apiSecret) {
            throw new RuntimeException('Credenziali GA4 Measurement Protocol mancanti.');
        }

        $clientId = $lead->ga_client_id ?: $this->fallbackClientId($lead);
        $params = [
            'currency' => 'EUR',
            'value' => (float) ($lead->quote_amount ?: 0),
            'lead_id' => (string) $lead->id,
            'lead_uuid' => (string) $lead->uuid,
            'quote_number' => (string) $lead->quote_number,
            'quote_amount' => (float) ($lead->quote_amount ?: 0),
            'engagement_time_msec' => 1,
        ];

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
                    'name' => 'quote_sent',
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
            Log::warning('Invio quote_sent a GA4 fallito', [
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

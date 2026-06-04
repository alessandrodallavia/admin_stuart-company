<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\AdminNotificationService;
use App\Services\Ga4MeasurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (! $secret || ! $this->hasValidSignature($payload, $signature, $secret)) {
            Log::warning('Webhook Stripe con firma non valida', [
                'has_secret' => (bool) $secret,
                'has_signature' => $signature !== '',
            ]);

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        if (($event['type'] ?? null) !== 'checkout.session.completed') {
            return response()->json(['message' => 'Event ignored']);
        }

        $session = data_get($event, 'data.object', []);

        if (data_get($session, 'payment_status') && data_get($session, 'payment_status') !== 'paid') {
            return response()->json(['message' => 'Payment not completed']);
        }

        $leadId = data_get($session, 'client_reference_id') ?: data_get($session, 'metadata.lead_id');

        if (! $leadId) {
            Log::warning('Webhook Stripe senza lead collegato', [
                'session_id' => data_get($session, 'id'),
            ]);

            return response()->json(['message' => 'Lead reference missing']);
        }

        $lead = Lead::find($leadId);

        if (! $lead) {
            Log::warning('Webhook Stripe per lead inesistente', [
                'lead_id' => $leadId,
                'session_id' => data_get($session, 'id'),
            ]);

            return response()->json(['message' => 'Lead not found']);
        }

        $amountTotal = data_get($session, 'amount_total');
        $paymentUrl = data_get($session, 'url');
        $customerDetails = data_get($session, 'customer_details', []);
        $wasAlreadyCompleted = $lead->status === 'order_completed';

        $lead->fill([
            'status' => 'order_completed',
            'payment_amount' => $amountTotal ? round(((int) $amountTotal) / 100, 2) : $lead->payment_amount,
            'payment_link' => $paymentUrl ?: $lead->payment_link,
            'billing_name' => data_get($customerDetails, 'name') ?: $lead->billing_name,
            'billing_email' => data_get($customerDetails, 'email') ?: $lead->billing_email,
            'billing_phone' => data_get($customerDetails, 'phone') ?: $lead->billing_phone,
        ])->save();

        if (! $wasAlreadyCompleted || $lead->wasChanged('payment_amount')) {
            app(AdminNotificationService::class)->notifyPaymentCompleted($lead->fresh());
        }

        $this->sendPurchaseEvent($lead->fresh(), app(Ga4MeasurementService::class));
        $this->sendPaymentThankYouMessage($lead->fresh());

        return response()->json(['message' => 'Lead marked as paid']);
    }

    private function sendPaymentThankYouMessage(Lead $lead): void
    {
        if ($lead->whatsapp_payment_thank_you_sent_at) {
            return;
        }

        $conversation = $lead->whatsappConversation
            ?? $lead->linkedWhatsappConversation
            ?? WhatsappConversation::query()
                ->where('lead_id', $lead->id)
                ->latest('last_message_at')
                ->latest()
                ->first();

        if (! $conversation) {
            $lead->forceFill([
                'whatsapp_payment_thank_you_status' => 'failed',
                'whatsapp_payment_thank_you_error' => 'Nessuna conversazione WhatsApp collegata.',
            ])->save();

            return;
        }

        if (! $lead->payment_checkout_token) {
            $lead->forceFill([
                'payment_checkout_token' => \Illuminate\Support\Str::random(48),
            ])->save();
        }

        $body = "Grazie, pagamento ricevuto correttamente. Procediamo con il tuo ordine e ti aggiorneremo appena sarà pronto.\n\nPer completare la fatturazione, clicca sul pulsante qui sotto e inserisci i tuoi dati.";
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $conversation->contact_phone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'cta_url',
                'body' => [
                    'text' => $body,
                ],
                'footer' => [
                    'text' => 'Stuart Company',
                ],
                'action' => [
                    'name' => 'cta_url',
                    'parameters' => [
                        'display_text' => 'Dati fatturazione',
                        'url' => config('services.public_site.url').'/pagamento/dati/'.$lead->payment_checkout_token,
                    ],
                ],
            ],
        ];

        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/messages', $payload);

        $message = WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $response->json('messages.0.id'),
            'direction' => 'outbound',
            'source' => 'automation',
            'type' => 'interactive',
            'status' => $response->successful() ? 'sent' : 'failed',
            'from_phone' => config('services.whatsapp.phone_number_id'),
            'to_phone' => $conversation->contact_phone,
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

        if ($response->failed()) {
            Log::warning('Invio messaggio ringraziamento pagamento WhatsApp fallito', [
                'lead_id' => $lead->id,
                'conversation_id' => $conversation->id,
                'response' => $response->json(),
            ]);
        }

        $lead->forceFill([
            'whatsapp_payment_thank_you_sent_at' => $response->successful() ? now() : null,
            'whatsapp_payment_thank_you_status' => $response->successful() ? 'sent' : 'failed',
            'whatsapp_payment_thank_you_error' => $response->failed() ? ($response->json('error.message') ?: 'errore sconosciuto') : null,
        ])->save();
    }

    private function sendPurchaseEvent(Lead $lead, Ga4MeasurementService $ga4): void
    {
        if ($lead->status !== 'order_completed' || $lead->ga4_purchase_sent_at) {
            return;
        }

        try {
            $ga4->sendPurchase($lead);

            $lead->forceFill([
                'ga4_purchase_sent_at' => now(),
                'ga4_purchase_sent_status' => 'sent',
                'ga4_purchase_sent_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Invio purchase a GA4 fallito', [
                'lead_id' => $lead->id,
                'lead_uuid' => $lead->uuid,
                'error' => $exception->getMessage(),
            ]);

            $lead->forceFill([
                'ga4_purchase_sent_status' => 'failed',
                'ga4_purchase_sent_error' => $exception->getMessage(),
            ])->save();
        }
    }

    private function hasValidSignature(string $payload, string $signature, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1' && $value) {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || $signatures === []) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}

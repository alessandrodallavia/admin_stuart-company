<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $wasAlreadyCompleted = $lead->status === 'order_completed';

        $lead->fill([
            'status' => 'order_completed',
            'payment_amount' => $amountTotal ? round(((int) $amountTotal) / 100, 2) : $lead->payment_amount,
            'payment_link' => $paymentUrl ?: $lead->payment_link,
        ])->save();

        if (! $wasAlreadyCompleted || $lead->wasChanged('payment_amount')) {
            app(AdminNotificationService::class)->notifyPaymentCompleted($lead->fresh());
        }

        return response()->json(['message' => 'Lead marked as paid']);
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

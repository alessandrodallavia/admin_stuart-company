<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class LeadConversionTrackingService
{
    public function __construct(
        private Ga4MeasurementService $ga4,
        private MetaConversionsApiService $meta,
        private GoogleAdsConversionService $googleAds,
    ) {}

    public function trackForCurrentStatus(Lead $lead): void
    {
        match ($lead->status) {
            'quote_sent' => $this->trackQuoteSent($lead),
            'link_sent' => $this->trackPaymentLinkSent($lead),
            'order_completed' => $this->trackPurchase($lead),
            default => null,
        };
    }

    public function trackQuoteSent(Lead $lead): void
    {
        if ($lead->is_training) {
            return;
        }

        $this->trackGa4($lead, 'quote_sent', fn () => $this->ga4->sendQuoteSent($lead));
        $lead = $lead->fresh();
        if (! $lead->meta_lead_sent_at) {
            $this->meta->trackLead($lead);
        }
        $this->trackGoogleAds($lead->fresh(), 'quote_sent', fn () => $this->googleAds->uploadQuoteSent($lead));
    }

    public function trackPaymentLinkSent(Lead $lead): void
    {
        if ($lead->is_training) {
            return;
        }

        $this->trackGa4($lead, 'payment_link_sent', fn () => $this->ga4->sendPaymentLinkSent($lead));
        $lead = $lead->fresh();
        if (! $lead->meta_initiate_checkout_sent_at) {
            $this->meta->trackInitiateCheckout($lead);
        }
        $this->trackGoogleAds($lead->fresh(), 'payment_link_sent', fn () => $this->googleAds->uploadPaymentLinkSent($lead));
    }

    public function trackPurchase(Lead $lead): void
    {
        if ($lead->is_training) {
            return;
        }

        $this->trackGa4($lead, 'purchase', fn () => $this->ga4->sendPurchase($lead));
        $lead = $lead->fresh();
        if (! $lead->meta_purchase_sent_at) {
            $this->meta->trackPurchase($lead);
        }
        $this->trackGoogleAds($lead->fresh(), 'purchase', fn () => $this->googleAds->uploadPurchase($lead));
    }

    private function trackGa4(Lead $lead, string $event, callable $send): void
    {
        $prefix = $event === 'purchase' ? 'ga4_purchase_sent' : "ga4_{$event}";

        if ($lead->{"{$prefix}_at"}) {
            return;
        }

        $this->track($lead, $prefix, $send, 'GA4', "{$prefix}_at");
    }

    private function trackGoogleAds(Lead $lead, string $event, callable $send): void
    {
        if (! $lead->gclid || $lead->{"google_ads_{$event}_at"}) {
            return;
        }

        $this->track($lead, "google_ads_{$event}", $send, 'Google Ads', "google_ads_{$event}_at");
    }

    private function track(Lead $lead, string $prefix, callable $send, string $platform, ?string $sentAtField = null): void
    {
        $sentAtField ??= "{$prefix}_sent_at";

        try {
            $send();

            $lead->forceFill([
                $sentAtField => now(),
                "{$prefix}_status" => 'sent',
                "{$prefix}_error" => null,
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning("Invio {$prefix} a {$platform} fallito", [
                'lead_id' => $lead->id,
                'lead_uuid' => $lead->uuid,
                'error' => $exception->getMessage(),
            ]);

            $lead->forceFill([
                "{$prefix}_status" => 'failed',
                "{$prefix}_error" => $exception->getMessage(),
            ])->save();
        }
    }
}

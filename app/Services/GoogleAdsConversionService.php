<?php

namespace App\Services;

use App\Models\Lead;
use Carbon\CarbonInterface;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V24\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Util\V24\ResourceNames;
use Google\Ads\GoogleAds\V24\Common\Consent;
use Google\Ads\GoogleAds\V24\Enums\ConsentStatusEnum\ConsentStatus;
use Google\Ads\GoogleAds\V24\Services\ClickConversion;
use Google\Ads\GoogleAds\V24\Services\UploadClickConversionsRequest;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleAdsConversionService
{
    public function uploadWhatsappMessageReceived(
        string $gclid,
        string $orderId,
        ?CarbonInterface $conversionTime = null,
        ?float $value = null
    ): array {
        return $this->uploadClickConversion(
            gclid: $gclid,
            conversionActionId: (int) config('services.google_ads.whatsapp_conversion_action_id'),
            orderId: $orderId,
            conversionTime: $conversionTime,
            value: $value ?? (float) config('services.google_ads.whatsapp_conversion_value', 1),
        );
    }

    public function uploadQuoteSent(Lead $lead, ?CarbonInterface $conversionTime = null): array
    {
        return $this->uploadLeadConversion($lead, 'quote_sent', $conversionTime, (float) ($lead->quote_amount ?: 0));
    }

    public function uploadPaymentLinkSent(Lead $lead, ?CarbonInterface $conversionTime = null): array
    {
        return $this->uploadLeadConversion($lead, 'payment_link_sent', $conversionTime, (float) ($lead->payment_amount ?: $lead->quote_amount ?: 0));
    }

    public function uploadPurchase(Lead $lead, ?CarbonInterface $conversionTime = null): array
    {
        return $this->uploadLeadConversion($lead, 'purchase', $conversionTime, (float) ($lead->payment_amount ?: $lead->quote_amount ?: 0));
    }

    private function uploadLeadConversion(Lead $lead, string $event, ?CarbonInterface $conversionTime, float $value): array
    {
        return $this->uploadClickConversion(
            gclid: (string) $lead->gclid,
            conversionActionId: (int) config("services.google_ads.{$event}_conversion_action_id"),
            orderId: "{$event}-".($lead->uuid ?: $lead->id),
            conversionTime: $conversionTime,
            value: $value,
        );
    }

    private function uploadClickConversion(
        string $gclid,
        int $conversionActionId,
        string $orderId,
        ?CarbonInterface $conversionTime = null,
        ?float $value = null
    ): array {
        $this->ensureConfigured($conversionActionId);
        $customerId = $this->normalizedCustomerId(config('services.google_ads.customer_id'));
        $conversionTime ??= now();

        $conversion = new ClickConversion([
            'gclid' => $gclid,
            'conversion_action' => ResourceNames::forConversionAction($customerId, $conversionActionId),
            'conversion_date_time' => $conversionTime->format('Y-m-d H:i:sP'),
            'conversion_value' => $value ?? 0,
            'currency_code' => config('services.google_ads.currency', 'EUR'),
            'order_id' => $orderId,
            'consent' => new Consent([
                'ad_user_data' => ConsentStatus::GRANTED,
            ]),
        ]);

        $response = $this->client()
            ->getConversionUploadServiceClient()
            ->uploadClickConversions(
                UploadClickConversionsRequest::build($customerId, [$conversion], true)
            );

        $partialFailure = $response->getPartialFailureError();

        if ($partialFailure && $partialFailure->getCode() !== 0) {
            $message = $partialFailure->getMessage();

            Log::warning('Google Ads conversione WhatsApp caricata con errore parziale', [
                'order_id' => $orderId,
                'partial_failure' => $message,
            ]);

            throw new RuntimeException($message ?: 'Google Ads partial failure durante upload conversione WhatsApp.');
        }

        return [
            'job_id' => $response->getJobId(),
            'results_count' => iterator_count($response->getResults()->getIterator()),
        ];
    }

    private function client()
    {
        $oauth2 = (new OAuth2TokenBuilder())
            ->withClientId(config('services.google_ads.client_id'))
            ->withClientSecret(config('services.google_ads.client_secret'))
            ->withRefreshToken(config('services.google_ads.refresh_token'))
            ->build();

        $builder = (new GoogleAdsClientBuilder())
            ->withDeveloperToken(config('services.google_ads.developer_token'))
            ->withOAuth2Credential($oauth2);

        if ($loginCustomerId = config('services.google_ads.login_customer_id')) {
            $builder->withLoginCustomerId((int) $this->normalizedCustomerId($loginCustomerId));
        }

        return $builder->build();
    }

    private function ensureConfigured(int $conversionActionId): void
    {
        foreach ([
            'developer_token',
            'client_id',
            'client_secret',
            'refresh_token',
            'customer_id',
        ] as $key) {
            if (! config("services.google_ads.{$key}")) {
                throw new RuntimeException("Config Google Ads mancante: services.google_ads.{$key}");
            }
        }

        if (! $conversionActionId) {
            throw new RuntimeException('Conversion action Google Ads mancante.');
        }
    }

    private function normalizedCustomerId(string|int|null $customerId): string
    {
        return preg_replace('/\D+/', '', (string) $customerId);
    }
}

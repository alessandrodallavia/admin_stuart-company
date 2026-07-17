<?php

namespace App\Services;

use App\Models\Lead;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V24\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V24\Enums\DeviceEnum\Device;
use Google\Ads\GoogleAds\V24\Services\SearchGoogleAdsRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GoogleAdsReportingService
{
    public function performance(Carbon $dateFrom, Carbon $dateTo): array
    {
        if (! $this->isConfigured()) {
            return ['available' => false, 'error' => 'Configurazione Google Ads incompleta.'];
        }

        $cacheKey = "google-ads:performance:{$dateFrom->toDateString()}:{$dateTo->toDateString()}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($dateFrom, $dateTo) {
            $query = sprintf(
                "SELECT metrics.cost_micros, metrics.clicks, metrics.impressions, metrics.ctr, metrics.average_cpc, metrics.search_impression_share, metrics.search_rank_lost_impression_share, metrics.search_budget_lost_impression_share FROM customer WHERE segments.date BETWEEN '%s' AND '%s'",
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            );

            $response = $this->client()->getGoogleAdsServiceClient()->search(
                SearchGoogleAdsRequest::build($this->customerId(), $query)
            );

            $row = collect($response->iterateAllElements())->first();
            $metrics = $row?->getMetrics();

            if (! $metrics) {
                return $this->emptyPerformance();
            }

            return [
                'available' => true,
                'error' => null,
                'spend' => $metrics->getCostMicros() / 1_000_000,
                'clicks' => (int) $metrics->getClicks(),
                'impressions' => (int) $metrics->getImpressions(),
                'ctr' => (float) $metrics->getCtr() * 100,
                'average_cpc' => (float) $metrics->getAverageCpc() / 1_000_000,
                'impression_share' => $this->share($metrics->getSearchImpressionShare()),
                'lost_rank_share' => $this->share($metrics->getSearchRankLostImpressionShare()),
                'lost_budget_share' => $this->share($metrics->getSearchBudgetLostImpressionShare()),
            ];
        });
    }

    public function enrichLeads(Carbon $dateFrom, Carbon $dateTo): int
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Configurazione Google Ads incompleta.');
        }

        $earliestDate = now()->subDays(89)->startOfDay();
        $dateFrom = $dateFrom->greaterThan($earliestDate) ? $dateFrom : $earliestDate;
        $updated = 0;

        Lead::query()
            ->whereNotNull('gclid')
            ->whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->where(fn ($query) => $query
                ->whereNull('ad_group')
                ->orWhereNull('utm_campaign')
                ->orWhereNull('utm_term')
                ->orWhereNull('acquisition_country')
                ->orWhereNull('acquisition_region')
                ->orWhereNull('device'))
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Lead $lead) => $lead->created_at->toDateString())
            ->each(function ($leads, string $date) use (&$updated) {
                $leads->chunk(200)->each(function ($chunk) use ($date, &$updated) {
                    $byGclid = $chunk
                        ->filter(fn (Lead $lead) => preg_match('/^[A-Za-z0-9_-]+$/', (string) $lead->gclid))
                        ->keyBy('gclid');

                    if ($byGclid->isEmpty()) {
                        return;
                    }

                    $quotedGclids = $byGclid->keys()->map(fn (string $gclid) => "'{$gclid}'")->implode(', ');
                    $query = "SELECT click_view.gclid, click_view.keyword_info.text, click_view.location_of_presence.country, click_view.location_of_presence.region, campaign.name, ad_group.name, segments.device FROM click_view WHERE segments.date = '{$date}' AND click_view.gclid IN ({$quotedGclids})";
                    $response = $this->client()->getGoogleAdsServiceClient()->search(
                        SearchGoogleAdsRequest::build($this->customerId(), $query)
                    );
                    $rows = collect($response->iterateAllElements());
                    $geoNames = $this->geoNames(
                        $rows->flatMap(function ($row) {
                            $location = $row->getClickView()?->getLocationOfPresence();

                            return [$location?->getCountry(), $location?->getRegion()];
                        })->filter()->unique()->values()->all()
                    );

                    foreach ($rows as $row) {
                        $lead = $byGclid->get($row->getClickView()?->getGclid());

                        if (! $lead) {
                            continue;
                        }

                        $location = $row->getClickView()?->getLocationOfPresence();
                        $country = $geoNames[$location?->getCountry()] ?? null;
                        $region = $geoNames[$location?->getRegion()] ?? null;

                        $lead->forceFill([
                            'utm_campaign' => $lead->utm_campaign ?: $row->getCampaign()?->getName(),
                            'ad_group' => $lead->ad_group ?: $row->getAdGroup()?->getName(),
                            'utm_term' => $lead->utm_term ?: $row->getClickView()?->getKeywordInfo()?->getText(),
                            'acquisition_country' => $lead->acquisition_country ?: ($country['country_code'] ?? $country['name'] ?? null),
                            'acquisition_region' => $lead->acquisition_region ?: ($region['name'] ?? null),
                            'device' => $lead->device ?: $this->deviceName($row->getSegments()?->getDevice()),
                        ])->save();
                        $updated++;
                    }
                });
            });

        return $updated;
    }

    private function geoNames(array $resourceNames): array
    {
        $locations = [];

        foreach (array_chunk($resourceNames, 200) as $chunk) {
            $quotedNames = collect($chunk)->map(fn (string $name) => "'{$name}'")->implode(', ');
            $query = "SELECT geo_target_constant.resource_name, geo_target_constant.name, geo_target_constant.country_code FROM geo_target_constant WHERE geo_target_constant.resource_name IN ({$quotedNames})";
            $response = $this->client()->getGoogleAdsServiceClient()->search(
                SearchGoogleAdsRequest::build($this->customerId(), $query)
            );

            foreach ($response->iterateAllElements() as $row) {
                $geo = $row->getGeoTargetConstant();
                $locations[$geo->getResourceName()] = [
                    'name' => $geo->getName(),
                    'country_code' => $geo->getCountryCode(),
                ];
            }
        }

        return $locations;
    }

    private function deviceName(?int $device): ?string
    {
        return match ($device) {
            Device::MOBILE => 'Mobile',
            Device::TABLET => 'Tablet',
            Device::DESKTOP => 'Desktop',
            Device::CONNECTED_TV => 'TV connessa',
            Device::OTHER => 'Altro',
            default => null,
        };
    }

    private function emptyPerformance(): array
    {
        return [
            'available' => true,
            'error' => null,
            'spend' => 0,
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'average_cpc' => 0,
            'impression_share' => null,
            'lost_rank_share' => null,
            'lost_budget_share' => null,
        ];
    }

    private function share(float $value): ?float
    {
        return $value < 0 ? null : $value * 100;
    }

    private function isConfigured(): bool
    {
        return collect(['developer_token', 'client_id', 'client_secret', 'refresh_token', 'customer_id'])
            ->every(fn (string $key) => filled(config("services.google_ads.{$key}")));
    }

    private function customerId(): string
    {
        return preg_replace('/\D+/', '', (string) config('services.google_ads.customer_id'));
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
            $builder->withLoginCustomerId((int) preg_replace('/\D+/', '', (string) $loginCustomerId));
        }

        return $builder->build();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\Ga4MeasurementService;
use App\Services\GoogleAdsConversionService;
use App\Services\LeadConversionTrackingService;
use App\Services\MetaConversionsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class LeadConversionTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_quote_event_to_all_platforms_only_once(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        Config::set('services.meta.pixel_id', 'test-pixel');
        Config::set('services.meta.conversions_api_token', 'test-token');

        $lead = $this->lead();

        $this->mock(Ga4MeasurementService::class, function (MockInterface $mock) use ($lead) {
            $mock->shouldReceive('sendQuoteSent')->once()->withArgs(fn (Lead $sentLead) => $sentLead->is($lead));
        });
        $this->mock(GoogleAdsConversionService::class, function (MockInterface $mock) use ($lead) {
            $mock->shouldReceive('uploadQuoteSent')->once()->withArgs(fn (Lead $sentLead) => $sentLead->is($lead));
        });

        $service = app(LeadConversionTrackingService::class);
        $service->trackQuoteSent($lead);
        $service->trackQuoteSent($lead->fresh());

        $lead->refresh();
        $this->assertNotNull($lead->ga4_quote_sent_at);
        $this->assertNotNull($lead->meta_lead_sent_at);
        $this->assertNotNull($lead->google_ads_quote_sent_at);
        Http::assertSentCount(1);
    }

    public function test_it_skips_google_ads_without_gclid_but_tracks_other_platforms(): void
    {
        $lead = $this->lead(['gclid' => null, 'status' => 'order_completed']);

        $this->mock(Ga4MeasurementService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendPurchase')->once();
        });
        $this->mock(MetaConversionsApiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('trackPurchase')->once();
        });
        $this->mock(GoogleAdsConversionService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('uploadPurchase');
        });

        app(LeadConversionTrackingService::class)->trackPurchase($lead);

        $this->assertNull($lead->fresh()->google_ads_purchase_at);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create([
            'uuid' => 'TRACK01',
            'status' => 'quote_sent',
            'name' => 'Cliente Test',
            'email' => 'cliente@example.test',
            'phone' => '393331234567',
            'privacy_consent' => true,
            'meta_marketing_consent' => true,
            'gclid' => 'test-gclid',
            'quote_amount' => 1000,
            'payment_amount' => 800,
            ...$attributes,
        ]);
    }
}

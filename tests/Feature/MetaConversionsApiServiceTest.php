<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\MetaConversionsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaConversionsApiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_each_consenting_event_only_once(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1]),
        ]);
        Config::set('services.meta.pixel_id', '356478581578783');
        Config::set('services.meta.conversions_api_token', 'token');
        Config::set('services.meta.graph_api_version', 'v25.0');

        $lead = $this->lead(['meta_marketing_consent' => true]);
        $service = app(MetaConversionsApiService::class);

        $this->assertTrue($service->trackContact($lead));
        $this->assertFalse($service->trackContact($lead->fresh()));
        $this->assertTrue($service->trackInitiateCheckout($lead->fresh()));
        $this->assertTrue($service->trackPurchase($lead->fresh()));

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->data()['data'][0]['event_name'] === 'Purchase'
            && $request->data()['data'][0]['custom_data']['currency'] === 'EUR'
            && $request->data()['data'][0]['custom_data']['value'] === 120.5);
    }

    public function test_it_does_not_send_without_cookiebot_marketing_consent(): void
    {
        Http::fake();

        $this->assertFalse(app(MetaConversionsApiService::class)->trackPurchase($this->lead()));

        Http::assertNothingSent();
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create([
            'uuid' => 'META01',
            'status' => 'link_sent',
            'name' => 'Cliente Test',
            'email' => 'cliente@example.test',
            'phone' => '393331234567',
            'privacy_consent' => true,
            'payment_amount' => 120.50,
            'quote_number' => 'PROPOSTA-0001',
            'landing_page' => 'https://stuart-company.com/',
            'ip' => '203.0.113.10',
            'user_agent' => 'Test Browser',
            ...$attributes,
        ]);
    }
}

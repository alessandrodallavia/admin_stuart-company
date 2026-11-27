<?php

namespace Tests\Feature;

use App\Models\EcommerceOrder;
use App\Services\{BrevoEmailService, EcommerceOrderEmailImage, EcommerceOrderEmailParams};
use Brevo\Brevo;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommerceOrderEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function order(): EcommerceOrder
    {
        return EcommerceOrder::create(['uuid' => Str::uuid(), 'submission_key' => Str::random(64), 'payment_method' => 'bank', 'status' => 'proforma_pending', 'customer_name' => 'Mario Rossi', 'email' => 'mario@example.test', 'customer' => ['shipping_name' => 'Mario Rossi', 'shipping_address' => 'Via Roma 1', 'shipping_city' => 'Milano', 'shipping_province' => 'MI', 'shipping_postal_code' => '20100', 'billing_name' => 'Mario Rossi', 'billing_address' => 'Via Milano 2', 'billing_city' => 'Roma', 'billing_postal_code' => '00100', 'billing_province' => 'RM', 'billing_tax_code' => 'RSSMRA80A01H501U'], 'items' => ['item-1' => ['name' => 'T-shirt', 'quantity' => 20, 'gross' => 183, 'rows' => [['color' => 'BLACK', 'size' => 'm', 'quantity' => 10], ['color' => 'BLACK', 'size' => 'l', 'quantity' => 10]]]], 'net' => 158.12, 'vat' => 34.78, 'gross' => 192.90, 'shipping_gross' => 9.90]);
    }

    public function test_template_params_match_the_brevo_contract_for_bank_and_paid_orders(): void
    {
        $order = $this->order();
        $params = app(EcommerceOrderEmailParams::class)->build($order);
        $this->assertSame('€ 183,00', $params['items_amount']);
        $this->assertSame('€ 9,90', $params['shipping_amount']);
        $this->assertSame('€ 192,90', $params['total_amount']);
        $this->assertSame('Bonifico bancario', $params['payment_method_label']);
        $this->assertFalse($params['payment_received']);
        $this->assertSame('Mario Rossi', $params['shipping_name']);
        $this->assertSame('Via Milano 2', $params['billing_address']);
        $this->assertSame('Roma', $params['billing_city']);
        $this->assertStringEndsWith('/assets/logos/logo-stuart-light.png', $params['logo_url']);
        foreach (['logo_url', 'received_icon_url', 'shipped_icon_url', 'card_top_url', 'card_bottom_url'] as $key) {
            $this->assertNotEmpty($params[$key]);
            $this->assertTrue(filter_var($params[$key], FILTER_VALIDATE_URL) !== false);
        }
        $this->assertStringEndsWith('/order-pending.png', $params['shipped_icon_url']);
        $this->assertSame('col. Black tg. M/10, L/10', $params['items'][0]['variants']);
        $this->assertSame(20, $params['items'][0]['quantity']);
        $this->assertSame('€ 183,00', $params['items'][0]['total_amount']);
        $order->update(['status' => 'paid', 'payment_method' => 'online', 'shipping_gross' => 0, 'gross' => 183]);
        $params = app(EcommerceOrderEmailParams::class)->build($order);
        $this->assertTrue($params['payment_received']);
        $this->assertSame('Pagamento online', $params['payment_method_label']);
        $this->assertSame('Gratuita', $params['shipping_amount']);
        $order->update(['order_status' => 'shipped']);
        $this->assertStringEndsWith('/order-received.png', app(EcommerceOrderEmailParams::class)->build($order)['shipped_icon_url']);
    }

    public function test_real_sdk_payload_uses_template_12_and_keeps_arrays_and_booleans(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(201, ['Content-Type' => 'application/json'], '{"messageId":"test-template-12"}')]));
        $handler->push(Middleware::history($history));
        $client = new Brevo(apiKey: 'test-only', options: ['client' => new Client(['handler' => $handler])]);
        $service = new class($client) extends BrevoEmailService {
            public function __construct(private Brevo $client) {}
            protected function brevo(): Brevo { return $this->client; }
        };
        $params = app(EcommerceOrderEmailParams::class)->build($this->order());
        $service->sendTemplateEmail(['email' => 'mario@example.test', 'name' => 'Mario Rossi'], 12, $params, ['ecommerce-order']);
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame(12, $body['templateId']);
        $this->assertSame($params, $body['params']);
        $this->assertFalse($body['params']['payment_received']);
        $this->assertEmpty($body['htmlContent'] ?? null);
        $this->assertEmpty($body['subject'] ?? null);
        $this->assertSame(config('admin_notifications.from.email'), $body['sender']['email']);
    }

    public function test_mockup_is_flattened_with_artwork_and_reuses_an_opaque_image_link(): void
    {
        Storage::fake('ecommerce_orders'); Storage::fake('live_mockup_assets');
        config(['services.public_site.url' => 'https://public.example.test']);
        $order = $this->order();
        $png = function ($red, $green, $blue) {
            $image = imagecreatetruecolor(80, 100);
            imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
            ob_start(); imagepng($image); $data = ob_get_clean(); imagedestroy($image); return $data;
        };
        Storage::disk('live_mockup_assets')->put('black.png', $png(0, 255, 0));
        Storage::disk('ecommerce_orders')->put($order->uuid.'/logo.png', $png(255, 0, 0));
        $item = ['previews' => [['mockup' => '/assets/images/landing/live-mockup/black.png', 'position' => 'front', 'overlay' => ['left' => 25, 'top' => 20, 'width' => 50, 'height' => 40]]], 'artworks' => ['front' => ['path' => $order->uuid.'/logo.png']]];
        $service = app(EcommerceOrderEmailImage::class);
        $url = $service->url($order, 'item-1', $item);
        $this->assertMatchesRegularExpression('~^https://public.example.test/email/ordini/immagini/([a-f0-9]{64})\.png$~', $url);
        $this->assertSame($url, $service->url($order, 'item-1', $item));
        $image = imagecreatefromstring(Storage::disk('ecommerce_orders')->get('email-images/'.basename($url)));
        $this->assertSame(400, imagesx($image)); $this->assertSame(500, imagesy($image));
        $this->assertSame(0xff0000, imagecolorat($image, 200, 200));
        $this->assertSame(0x00ff00, imagecolorat($image, 10, 10));
        imagedestroy($image);
    }
}

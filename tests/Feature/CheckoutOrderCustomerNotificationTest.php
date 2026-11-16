<?php

namespace Tests\Feature;

use App\Models\AdminDocument;
use App\Models\Lead;
use App\Models\LeadOrderCheckout;
use App\Models\LeadSalesSheet;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\CheckoutOrderCustomerNotificationService;
use App\Services\BrevoEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutOrderCustomerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_processing_notification_prefers_open_whatsapp_conversation(): void
    {
        [$checkout, $conversation] = $this->checkoutWithConversation(now());
        Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.payment']]])]);

        $result = app(CheckoutOrderCustomerNotificationService::class)
            ->orderProcessing($checkout);

        $this->assertSame(['sent' => true, 'channel' => 'whatsapp'], $result);
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => 'wamid.payment',
            'direction' => 'outbound',
            'source' => 'automation',
            'status' => 'sent',
        ]);
        Http::assertSent(fn ($request) => $request['to'] === '393331234567'
            && str_contains($request['text']['body'], 'ordine nr. 00123 è in lavorazione')
            && str_ends_with($request['text']['body'], 'Cordiali saluti'));
    }

    public function test_expired_whatsapp_conversation_falls_back_to_email_channel(): void
    {
        [$checkout] = $this->checkoutWithConversation(now()->subHours(25));
        Http::fake();

        $checkout->update(['contact_email' => null]);
        $checkout->lead->update(['email' => null]);
        $result = app(CheckoutOrderCustomerNotificationService::class)->orderProcessing($checkout->fresh());

        $this->assertFalse($result['sent']);
        $this->assertNull($result['channel']);
        $this->assertStringContainsString('indirizzo e-mail', $result['error']);
        Http::assertNothingSent();
    }

    public function test_expired_whatsapp_conversation_sends_email_with_brevo(): void
    {
        [$checkout] = $this->checkoutWithConversation(now()->subHours(25));
        $this->mock(BrevoEmailService::class, function ($mock) {
            $mock->shouldReceive('sendTransactionalEmail')->once()
                ->withArgs(fn ($to, $subject) => $to['email'] === 'mario@example.test'
                    && $subject === 'Ordine nr. 00123')
                ->andReturn([]);
        });
        Http::fake();

        $result = app(CheckoutOrderCustomerNotificationService::class)->orderProcessing($checkout);

        $this->assertSame(['sent' => true, 'channel' => 'email'], $result);
        Http::assertNothingSent();
    }

    public function test_proforma_email_uses_the_formatted_order_number(): void
    {
        [$checkout] = $this->checkoutWithConversation(now()->subHours(25));
        $document = AdminDocument::create([
            'type' => 'proforma',
            'number' => 17,
            'year' => 2026,
            'code' => 'PRO-17',
            'document_date' => now()->toDateString(),
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@example.test',
        ]);
        $this->mock(BrevoEmailService::class, function ($mock) {
            $mock->shouldReceive('sendTransactionalEmail')->once()
                ->withArgs(fn ($to, $subject, $html) => $subject === 'Proforma nr. 17'
                    && str_contains($html, "relativa all&#039;ordine nr. 00123"))
                ->andReturn([]);
        });
        Http::fake();

        $result = app(CheckoutOrderCustomerNotificationService::class)
            ->proformaSent($checkout, $document, '%PDF-test', 'Proforma nr. 17.pdf');

        $this->assertSame(['sent' => true, 'channel' => 'email'], $result);
    }

    public function test_shipped_notification_contains_the_carrier_tracking_link(): void
    {
        [$checkout] = $this->checkoutWithConversation(now());
        $checkout->update([
            'fulfillment_carrier' => 'BRT - Bartolini',
            'fulfillment_tracking' => 'ABC 123',
        ]);
        Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.shipped']]])]);

        $result = app(CheckoutOrderCustomerNotificationService::class)->shipped($checkout->fresh());

        $this->assertSame(['sent' => true, 'channel' => 'whatsapp'], $result);
        Http::assertSent(fn ($request) => str_contains(
            $request['text']['body'],
            'https://vas.brt.it/vas/sped_det_show.hsm?referer=sped_numspe_par.htm&Nspediz=ABC%20123'
        ));
    }

    public function test_shipped_notification_contains_the_gls_tracking_link(): void
    {
        [$checkout] = $this->checkoutWithConversation(now());
        $checkout->update([
            'fulfillment_carrier' => 'GLS',
            'fulfillment_tracking' => 'P3661592917',
        ]);
        Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.shipped']]])]);

        app(CheckoutOrderCustomerNotificationService::class)->shipped($checkout->fresh());

        Http::assertSent(fn ($request) => str_contains(
            $request['text']['body'],
            'https://gls-group.com/IT/it/servizi-online/ricerca-spedizioni/?match=P3661592917&type=NAT'
        ));
    }

    private function checkoutWithConversation($incomingAt): array
    {
        $lead = Lead::create([
            'name' => 'Mario Rossi',
            'email' => 'mario@example.test',
            'phone' => '3331234567',
        ]);
        $sheet = LeadSalesSheet::create([
            'lead_id' => $lead->id,
            'order_number' => 'ORD-123',
            'name' => 'Ordine notifiche',
            'status' => 'draft',
        ]);
        $checkout = LeadOrderCheckout::create([
            'lead_id' => $lead->id,
            'lead_sales_sheet_id' => $sheet->id,
            'token' => str_repeat('n', 64),
            'proposal_number' => 'ORD-123',
            'order_number' => 'ORD-123',
            'status' => 'paid',
            'order_status' => 'payment_pending',
            'payment_type' => 'deposit',
            'payment_label' => 'Acconto',
            'currency' => 'EUR',
            'items' => [],
            'total_amount' => 100,
            'contact_first_name' => 'Mario',
            'contact_last_name' => 'Rossi',
            'contact_email' => 'mario@example.test',
        ]);
        $conversation = WhatsappConversation::create([
            'lead_id' => $lead->id,
            'contact_phone' => '393331234567',
            'business_phone' => '390000000000',
            'mode' => 'manual',
            'status' => 'open',
            'last_message_at' => $incomingAt,
        ]);
        WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => 'wamid.incoming.'.$incomingAt->timestamp,
            'direction' => 'inbound',
            'source' => 'customer',
            'type' => 'text',
            'status' => 'received',
            'from_phone' => '393331234567',
            'to_phone' => '390000000000',
            'body' => 'Ciao',
            'received_at' => $incomingAt,
        ]);

        return [$checkout, $conversation];
    }
}

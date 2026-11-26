<?php

namespace Tests\Unit;

use App\Support\OrderCustomerMessage;
use Tests\TestCase;

class OrderCustomerMessageTest extends TestCase
{
    public function test_processing_keeps_the_existing_checkout_wording(): void
    {
        $message = OrderCustomerMessage::forEvent('in_production', 'EC-000007');
        $this->assertSame('Ordine nr. EC-000007', $message['subject']);
        $this->assertSame("Gentile Cliente,\n\nil tuo ordine nr. EC-000007 è in lavorazione.\n\nTi aggiorneremo non appena sarà pronto.\n\nCordiali saluti", $message['body']);
    }

    public function test_shipped_uses_checkout_format_with_optional_tracking_and_clickable_link(): void
    {
        $message = OrderCustomerMessage::forEvent('shipped', 'EC-000007', 'GLS', 'ABC123', 'https://example.test/tracking/ABC123');
        $this->assertSame('Ordine nr. EC-000007 spedito', $message['subject']);
        $this->assertSame("Gentile Cliente,\n\nil tuo ordine nr. EC-000007 è stato spedito.\n\nGLS · tracking ABC123 · Segui la spedizione: https://example.test/tracking/ABC123\n\nCordiali saluti", $message['body']);
        $this->assertStringContainsString('<a href="https://example.test/tracking/ABC123">', OrderCustomerMessage::html($message['body']));
        $withoutTracking = OrderCustomerMessage::forEvent('shipped', '00123');
        $this->assertSame("Gentile Cliente,\n\nil tuo ordine nr. 00123 è stato spedito.\n\nCordiali saluti", $withoutTracking['body']);
    }

    public function test_other_notifications_follow_the_same_style_without_inventing_checkout_events(): void
    {
        foreach (['payment_received', 'tracking_updated', 'delivered', 'collected'] as $kind) {
            $message = OrderCustomerMessage::forEvent($kind, 'EC-000007');
            $this->assertStringContainsString('Ordine nr. EC-000007', $message['subject']);
            $this->assertStringStartsWith("Gentile Cliente,\n\n", $message['body']);
            $this->assertStringContainsString('EC-000007', $message['body']);
            $this->assertStringEndsWith("\n\nCordiali saluti", $message['body']);
        }
        $this->assertNull(OrderCustomerMessage::forEvent('updated', 'EC-000007'));
        $this->assertStringNotContainsString('<script>', OrderCustomerMessage::html('<script>alert(1)</script>'));
    }
}

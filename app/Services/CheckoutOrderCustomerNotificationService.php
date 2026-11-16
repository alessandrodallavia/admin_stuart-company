<?php

namespace App\Services;

use App\Models\AdminDocument;
use App\Models\LeadOrderCheckout;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckoutOrderCustomerNotificationService
{
    public function __construct(private readonly BrevoEmailService $brevo) {}

    public function orderProcessing(LeadOrderCheckout $checkout): array
    {
        $orderNumber = $this->displayOrderNumber($checkout->order_number);

        return $this->deliver($checkout, 'Ordine nr. '.$orderNumber,
            "Gentile Cliente,\n\nil tuo ordine nr. {$orderNumber} è in lavorazione.\n\nTi aggiorneremo non appena sarà pronto.\n\nCordiali saluti");
    }

    public function balanceRequested(LeadOrderCheckout $checkout, string $checkoutUrl): array
    {
        return $this->deliver($checkout, 'Saldo ordine - '.$checkout->order_number,
            "Gentile Cliente,\n\nil tuo ordine nr. {$checkout->order_number} è pronto. Per procedere con il saldo, completa il checkout al seguente link:\n{$checkoutUrl}");
    }

    public function shipped(LeadOrderCheckout $checkout): array
    {
        $orderNumber = $this->displayOrderNumber($checkout->order_number);
        $trackingUrl = $this->trackingUrl($checkout->fulfillment_carrier, $checkout->fulfillment_tracking);
        $tracking = collect([
            $checkout->fulfillment_carrier,
            $checkout->fulfillment_tracking ? 'tracking '.$checkout->fulfillment_tracking : null,
            $trackingUrl ? 'Segui la spedizione: '.$trackingUrl : null,
        ])->filter()->join(' · ');

        return $this->deliver($checkout, 'Ordine nr. '.$orderNumber.' spedito',
            "Gentile Cliente,\n\nil tuo ordine nr. {$orderNumber} è stato spedito.".($tracking ? "\n\n{$tracking}" : '')."\n\nCordiali saluti");
    }

    public function proformaSent(LeadOrderCheckout $checkout, AdminDocument $document, string $pdf, string $filename): array
    {
        $number = $document->number ?: $document->display_code;
        $orderNumber = $this->displayOrderNumber($checkout->order_number);
        $body = "Gentile Cliente,\n\nin allegato può trovare la proforma nr. {$number} relativa all'ordine nr. {$orderNumber}.\n\nCordiali saluti";

        return $this->deliver($checkout, 'Proforma nr. '.$number, $body, [
            'name' => $filename,
            'content' => $pdf,
        ]);
    }

    private function deliver(LeadOrderCheckout $checkout, string $subject, string $body, ?array $attachment = null): array
    {
        $checkout->loadMissing('lead');
        $conversation = WhatsappConversation::query()
            ->with('latestIncomingMessage')
            ->where('lead_id', $checkout->lead_id)
            ->latest('last_message_at')->latest('id')->first();

        if ($conversation && ! $conversation->isWhatsappWindowExpired() && $conversation->contact_phone
            && $this->sendWhatsapp($conversation, $body, $attachment)) {
            return ['sent' => true, 'channel' => 'whatsapp'];
        }

        $email = $checkout->contact_email ?: $checkout->lead?->email;
        if (! $email) {
            return ['sent' => false, 'channel' => null, 'error' => 'Il cliente non ha un indirizzo e-mail.'];
        }

        try {
            $htmlBody = nl2br(e($body));
            $htmlBody = preg_replace('~https://[^\s<]+~', '<a href="$0">$0</a>', $htmlBody);
            $this->brevo->sendTransactionalEmail(
                ['name' => trim($checkout->contact_first_name.' '.$checkout->contact_last_name) ?: $email, 'email' => $email],
                $subject, '<p>'.$htmlBody.'</p>', $body, ['checkout-order'], $attachment ? [$attachment] : []
            );

            return ['sent' => true, 'channel' => 'email'];
        } catch (Throwable $exception) {
            report($exception);

            return ['sent' => false, 'channel' => 'email', 'error' => 'Invio e-mail non riuscito.'];
        }
    }

    private function sendWhatsapp(WhatsappConversation $conversation, string $body, ?array $attachment): bool
    {
        if ($attachment) {
            $upload = Http::withToken(config('services.whatsapp.token'))
                ->attach('file', $attachment['content'], $attachment['name'])
                ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/media', [
                    'messaging_product' => 'whatsapp', 'type' => 'application/pdf',
                ]);
            if ($upload->failed() || ! $upload->json('id')) {
                return false;
            }
            $payload = [
                'messaging_product' => 'whatsapp', 'to' => $conversation->contact_phone, 'type' => 'document',
                'document' => ['id' => $upload->json('id'), 'filename' => $attachment['name'], 'caption' => $body],
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp', 'to' => $conversation->contact_phone,
                'type' => 'text', 'text' => ['body' => $body],
            ];
        }

        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/messages', $payload);

        WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $response->json('messages.0.id'),
            'direction' => 'outbound', 'source' => 'automation', 'type' => $attachment ? 'document' : 'text',
            'status' => $response->successful() ? 'sent' : 'failed',
            'from_phone' => config('services.whatsapp.phone_number_id'), 'to_phone' => $conversation->contact_phone,
            'body' => $body, 'payload' => ['request' => $payload, 'response' => $response->json()],
            'error_code' => $response->json('error.code'), 'error_message' => $response->json('error.message'),
            'sent_at' => $response->successful() ? now() : null, 'failed_at' => $response->failed() ? now() : null,
        ]);

        if ($response->successful()) {
            $conversation->forceFill(['last_message_at' => now()])->save();
        }

        return $response->successful();
    }

    private function displayOrderNumber(string $orderNumber): string
    {
        if (preg_match('/(\d+)$/', $orderNumber, $matches)) {
            return str_pad((string) ((int) $matches[1]), 5, '0', STR_PAD_LEFT);
        }

        return $orderNumber;
    }

    private function trackingUrl(?string $carrier, ?string $tracking): ?string
    {
        $carrier = mb_strtolower(trim((string) $carrier));
        $tracking = trim((string) $tracking);
        if ($carrier === '' || $tracking === '') {
            return null;
        }

        $code = rawurlencode($tracking);

        return match (true) {
            str_contains($carrier, 'brt'), str_contains($carrier, 'bartolini') => 'https://vas.brt.it/vas/sped_det_show.hsm?referer=sped_numspe_par.htm&Nspediz='.$code,
            str_contains($carrier, 'gls') => 'https://gls-group.com/IT/it/servizi-online/ricerca-spedizioni/?match='.$code.'&type=NAT',
            str_contains($carrier, 'dhl') => 'https://www.dhl.com/it-it/home/tracking.html?tracking-id='.$code,
            str_contains($carrier, 'ups') => 'https://www.ups.com/track?loc=it_IT&tracknum='.$code,
            str_contains($carrier, 'fedex') => 'https://www.fedex.com/fedextrack/?trknbr='.$code,
            str_contains($carrier, 'poste'), str_contains($carrier, 'sda') => 'https://www.poste.it/cerca/index.html#/risultati-spedizioni/'.$code,
            default => null,
        };
    }
}

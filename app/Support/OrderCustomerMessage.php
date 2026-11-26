<?php

namespace App\Support;

class OrderCustomerMessage
{
    public static function forEvent(string $kind, string $orderNumber, ?string $carrier = null, ?string $trackingCode = null, ?string $trackingUrl = null): ?array
    {
        $text = match ($kind) {
            'in_production' => "il tuo ordine nr. {$orderNumber} è in lavorazione.\n\nTi aggiorneremo non appena sarà pronto.",
            'shipped' => "il tuo ordine nr. {$orderNumber} è stato spedito.",
            'tracking_updated' => "i dati di spedizione del tuo ordine nr. {$orderNumber} sono stati aggiornati.",
            'payment_received' => "Abbiamo ricevuto il pagamento tramite bonifico bancario. Il pagamento del tuo ordine nr. {$orderNumber} è confermato.",
            'delivered' => "il tuo ordine nr. {$orderNumber} risulta consegnato.",
            'collected' => "il ritiro del tuo ordine nr. {$orderNumber} è stato registrato.",
            default => null,
        };
        if ($text === null) return null;

        if (in_array($kind, ['shipped', 'tracking_updated'], true)) {
            $tracking = collect([
                $carrier,
                $trackingCode ? 'tracking '.$trackingCode : null,
                $trackingUrl ? 'Segui la spedizione: '.$trackingUrl : null,
            ])->filter()->join(' · ');
            if ($tracking !== '') $text .= "\n\n".$tracking;
        }

        $suffix = match ($kind) {
            'shipped' => ' spedito',
            'tracking_updated' => ' - Tracking aggiornato',
            'payment_received' => ' - Pagamento ricevuto',
            'delivered' => ' consegnato',
            'collected' => ' ritirato',
            default => '',
        };

        return [
            'subject' => 'Ordine nr. '.$orderNumber.$suffix,
            'body' => "Gentile Cliente,\n\n{$text}\n\nCordiali saluti",
        ];
    }

    public static function html(string $body): string
    {
        $html = nl2br(e($body));
        return '<p>'.preg_replace('~https://[^\s<]+~', '<a href="$0">$0</a>', $html).'</p>';
    }
}

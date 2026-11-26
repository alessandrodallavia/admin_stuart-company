<?php

namespace App\Support;

class ProformaCustomerMessage
{
    public static function subject(string|int $number): string
    {
        return 'Proforma nr. '.$number;
    }

    public static function body(string|int $number, string $orderNumber): string
    {
        return "Gentile Cliente,\n\nin allegato può trovare la proforma nr. {$number} relativa all'ordine nr. {$orderNumber}.\n\nCordiali saluti";
    }
}

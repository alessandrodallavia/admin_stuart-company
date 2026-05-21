<?php

namespace App\Enums;

enum DocumentType: string
{
    case QUOTE = 'quote';
    case PROFORMA = 'proforma';
    case ORDER = 'order';
    case DELIVERY_NOTE = 'delivery_note';
    case INVOICE = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::QUOTE => 'Preventivo',
            self::PROFORMA => 'Proforma',
            self::ORDER => 'Ordine',
            self::DELIVERY_NOTE => 'DDT',
            self::INVOICE => 'Fattura',
        };
    }
}

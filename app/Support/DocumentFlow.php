<?php

namespace App\Support;

use App\Enums\DocumentType;

class DocumentFlow
{
    public static function allowed(): array
    {
        return [
            DocumentType::QUOTE->value => [
                DocumentType::PROFORMA->value,
                DocumentType::ORDER->value,
                DocumentType::INVOICE->value,
            ],

            DocumentType::PROFORMA->value => [
                DocumentType::ORDER->value,
                DocumentType::INVOICE->value,
            ],

            DocumentType::ORDER->value => [
                DocumentType::DELIVERY_NOTE->value,
                DocumentType::INVOICE->value,
            ],

            DocumentType::DELIVERY_NOTE->value => [
                DocumentType::INVOICE->value,
            ],

            DocumentType::INVOICE->value => [],
        ];
    }
}

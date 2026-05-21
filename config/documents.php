<?php

return [
    'company_address' => [
        env('DOCUMENTS_COMPANY_NAME', 'STUART COMPANY'),
        env('DOCUMENTS_COMPANY_ADDRESS', 'SEDE LEGALE:'),
        'e-mail: '.env('DOCUMENTS_COMPANY_EMAIL', 'info@stuart-company.it'),
        'Codice Fiscale '.env('DOCUMENTS_COMPANY_TAX_CODE', '').' - P. IVA '.env('DOCUMENTS_COMPANY_VAT_NUMBER', ''),
        env('DOCUMENTS_COMPANY_REGISTRATION', ''),
    ],
];

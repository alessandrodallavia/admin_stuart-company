<?php

return [
    'company_address' => [
        'STUART COMPANY SRLS',
        'SEDE LEGALE: VIA SANTA LUCIA, 103 - 35139 PADOVA (PD)',
        'e-mail: info@stuart-company.com',
        'Codice Fiscale 05040450289 - P. IVA 05040450289',
        'Reg. imprese PD 05040450289 - R.E.A. PD 438333',
    ],
    'company' => [
        'name' => 'STUART COMPANY SRLS',
        'vat_number' => '05040450289',
        'tax_code' => '05040450289',
        'country' => 'IT',
        'address' => 'VIA SANTA LUCIA',
        'street_number' => '103',
        'postal_code' => '35139',
        'city' => 'PADOVA',
        'province' => 'PD',
        'email' => 'info@stuart-company.com',
        'regime_fiscale' => env('DOCUMENTS_COMPANY_REGIME_FISCALE', 'RF01'),
    ],
    'bank' => [
        'name' => env('DOCUMENTS_BANK_NAME', 'UNICREDIT SPA'),
        'iban' => env('DOCUMENTS_BANK_IBAN', 'IT36I0200812101000104661292'),
        'bic' => env('DOCUMENTS_BANK_BIC', 'UNCRITM1923'),
    ],
    'invoice_fiscal_types' => [
        'TD01' => 'Fattura',
        'TD02' => 'Fattura di acconto',
        'TD24' => "Fattura differita di cui all'art. 21, comma 4, lett. a",
        'TD04' => 'Nota di credito',
        'TD05' => 'Nota di debito',
        'TD01A' => 'Autofattura',
        'TD16' => 'Integrazione fattura reverse charge interno',
        'TD17' => "Integrazione/autofattura per acquisto servizi all'estero",
        'TD18' => 'Integrazione/autofattura per acquisto di beni intracomunitari',
    ],
];

<?php

return [
    'default_language' => env('WHATSAPP_TEMPLATE_DEFAULT_LANGUAGE', 'it'),

    'templates' => [
        'lead_senza_risposta' => [
            'label' => 'Lead senza risposta',
            'name' => 'lead_senza_risposta',
            'language' => env('WHATSAPP_TEMPLATE_LEAD_SENZA_RISPOSTA_LANGUAGE', env('WHATSAPP_TEMPLATE_DEFAULT_LANGUAGE', 'it')),
            'body' => 'Template WhatsApp: lead_senza_risposta',
            'parameters' => [],
        ],
    ],
];

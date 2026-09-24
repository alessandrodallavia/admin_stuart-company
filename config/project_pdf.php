<?php

$whatsappPhone = preg_replace('/\D+/', '', (string) env('WHATSAPP_PHONE', '393458007031'));

return [
    'andrea_url' => env('PROJECT_PDF_ANDREA_URL', 'https://wa.me/'.$whatsappPhone),
    'configurator_url' => env(
        'PROJECT_PDF_CONFIGURATOR_URL',
        rtrim((string) env('PUBLIC_SITE_URL', 'https://stuart-company.com'), '/').'/#configuratore'
    ),
];

<?php

return [
    'from' => [
        'name' => env('ADMIN_NOTIFICATION_FROM_NAME', env('APP_NAME', 'Stuart Admin')),
        'email' => env('ADMIN_NOTIFICATION_FROM_EMAIL', 'info@bullstar.it'),
    ],

    'whatsapp_email_cooldown_minutes' => (int) env('ADMIN_NOTIFICATION_WHATSAPP_EMAIL_COOLDOWN_MINUTES', 5),
];

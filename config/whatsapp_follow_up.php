<?php

return [
    'auto_enabled' => env('WHATSAPP_FOLLOW_UP_AUTO_ENABLED', true),
    'auto_delay_hours' => (int) env('WHATSAPP_FOLLOW_UP_AUTO_DELAY_HOURS', 24),
    'auto_body' => env(
        'WHATSAPP_FOLLOW_UP_AUTO_BODY',
        "Ricontattare il cliente: non ha ancora risposto all'ultimo messaggio manuale."
    ),
];

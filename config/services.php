<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'pipeline_landing' => env('BREVO_PIPELINE_LANDING'),
        'first_stage' => env('BREVO_FIRST_STAGE'),
        'lead_stages' => [
            'pre' => env('BREVO_STAGE_PRE', env('BREVO_FIRST_STAGE')),
            'confirmed' => env('BREVO_STAGE_CONFIRMED', env('BREVO_FIRST_STAGE')),
            'completed' => env('BREVO_STAGE_COMPLETED', env('BREVO_FIRST_STAGE')),
            'quote_sent' => env('BREVO_STAGE_QUOTE_SENT', env('BREVO_FIRST_STAGE')),
            'link_sent' => env('BREVO_STAGE_LINK_SENT', env('BREVO_FIRST_STAGE')),
            'order_completed' => env('BREVO_STAGE_ORDER_COMPLETED', env('BREVO_FIRST_STAGE')),
        ],
        'webhook_token_inbound' => env('BREVO_WEBHOOK_TOKEN_INBOUND')
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'token' => env('WHATSAPP_TOKEN'),
        'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN'),
        'phone' => env('WHATSAPP_PHONE', '393458007031'),
        'phone_api' => env('WHATSAPP_PHONE_API', '15559497130'),
    ]

];

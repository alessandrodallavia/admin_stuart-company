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
        'webhook_token_inbound' => env('BREVO_WEBHOOK_TOKEN_INBOUND'),
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'token' => env('WHATSAPP_TOKEN'),
        'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN'),
        'phone' => env('WHATSAPP_PHONE', '393458007031'),
        'phone_api' => env('WHATSAPP_PHONE_API', '15559497130'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY', env('STRIPE_PRIVATE_KEY')),
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'eur'),
        'success_url' => env('STRIPE_SUCCESS_URL', rtrim(env('PUBLIC_SITE_URL', 'https://stuart-company.com'), '/') . '/pagamento/completato'),
        'cancel_url' => env('STRIPE_CANCEL_URL', rtrim(env('PUBLIC_SITE_URL', 'https://stuart-company.com'), '/') . '/pagamento/annullato'),
    ],

    'public_site' => [
        'url' => rtrim(env('PUBLIC_SITE_URL', 'https://stuart-company.com'), '/'),
    ],

    'google_ads' => [
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
        'whatsapp_conversion_action_id' => env('GOOGLE_ADS_WHATSAPP_CONVERSION_ACTION_ID'),
        'whatsapp_conversion_value' => (float) env('GOOGLE_ADS_WHATSAPP_CONVERSION_VALUE', 1),
        'currency' => env('GOOGLE_ADS_CURRENCY', 'EUR'),
    ],

    'ga4' => [
        'measurement_id' => env('GA4_MEASUREMENT_ID', 'G-ZMHBX4W5QX'),
        'api_secret' => env('GA4_API_SECRET'),
        'debug_mode' => env('GA4_DEBUG_MODE', false),
    ],

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID', '356478581578783'),
        'conversions_api_token' => env('META_CONVERSIONS_API_TOKEN'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v25.0'),
    ],

    'dropbox' => [
        'client_id' => env('DROPBOX_APP_KEY'),
        'client_secret' => env('DROPBOX_APP_SECRET'),
        'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),
    ],

    'brt' => [
        'username' => env('BRT_USERNAME'),
        'password' => env('BRT_PASSWORD'),
        'departure_depot' => env('BRT_DEPARTURE_DEPOT', '139'),
    ],

    'sda' => [
        'username' => env('SDA_USERNAME'),
        'password' => env('SDA_PASSWORD'),
        'verify_ssl' => env('SDA_VERIFY_SSL', false),
        'sender_name' => env('SDA_SENDER_NAME'),
        'sender_address' => env('SDA_SENDER_ADDRESS'),
        'sender_zip' => env('SDA_SENDER_ZIP'),
        'sender_city' => env('SDA_SENDER_CITY'),
        'sender_province' => env('SDA_SENDER_PROVINCE'),
        'sender_phone' => env('SDA_SENDER_PHONE'),
        'sender_email' => env('SDA_SENDER_EMAIL'),
        'service_code' => env('SDA_SERVICE_CODE', 'S09'),
    ],

];

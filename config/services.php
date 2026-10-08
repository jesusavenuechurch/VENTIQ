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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', '+14155238886'),
    ],

    // Meta WhatsApp Cloud API — replacing Twilio. Use a permanent System
    // User access token (Business Settings), not the 24h quick-start token.
    // (There used to be a second, duplicate 'whatsapp' key further down
    // this file — PHP silently keeps the LAST duplicate array key, so it
    // was overwriting this entire block; removed, nothing referenced it.)
    'whatsapp' => [
        'phone_number_id'     => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token'        => env('WHATSAPP_ACCESS_TOKEN'),
        'app_id'              => env('WHATSAPP_APP_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'api_version'         => env('WHATSAPP_API_VERSION', 'v25.0'),
        // Template names live in config/constants.php (whatsapp_templates).
    ],

    // Khoebo, VENTIQ's accounting system: organizers are its customers and
    // VENTIQ's fees its products. See docs/khoebo-plan.md.
    'khoebo' => [
        'url'   => env('KHOEBO_URL', 'https://dev.khoebo.co.ls/api/v1'),
        'token' => env('KHOEBO_TOKEN'),
        // What VENTIQ sells, made in Khoebo by `php artisan khoebo:products`
        // (their Khoebo ids are kept in khoebo_products). To sell something
        // new, add it here and run the command again; a key never changes.
        // VENTIQ isn't VAT registered: tax 4 is Exempt on Khoebo dev.
        // Events published get their order automatically; the hourly
        // catch-up (khoebo:orders) only looks at events created from here.
        'orders_from' => env('KHOEBO_ORDERS_FROM', '2026-10-08'),
        // Payment terms on orders (Khoebo's id; left off when not set).
        'payment_term_id' => env('KHOEBO_PAYMENT_TERM_ID'),
        'tax_ids' => array_map('intval', array_filter(explode(',', env('KHOEBO_TAX_IDS', '4')))),
        'products' => [
            'fee-person' => [
                'name'       => 'VENTIQ per-person fee',
                'sku'        => 'VQ-PERSON',
                'type'       => 'service',
                'sale_price' => env('VENTIQ_OPERATIONAL_FEE', 7.50),
            ],
            'fee-sales' => [
                'name'       => 'VENTIQ ticket sales fee (4.9%)',
                'sku'        => 'VQ-SALES',
                'type'       => 'service',
                'sale_price' => 0, // set on each order: 4.9% of that event's sales
            ],
        ],
    ],

    'mopay' => [
        'api_key' => env('MOPAY_API_KEY'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

];

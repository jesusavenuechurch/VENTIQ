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
        // The Events books (eventiq company). EVENTS_KHOEBO_* or, as before, KHOEBO_*.
        'url'   => env('EVENTS_KHOEBO_URL', env('KHOEBO_URL', 'https://dev.khoebo.co.ls/api/v1')),
        'token' => env('EVENTS_KHOEBO_TOKEN', env('KHOEBO_TOKEN')),
        // What VENTIQ sells, made in Khoebo by `php artisan khoebo:products`
        // (their Khoebo ids are kept in khoebo_products). To sell something
        // new, add it here and run the command again; a key never changes.
        // VENTIQ isn't VAT registered: tax 4 is Exempt on Khoebo dev.
        // Events published get their order automatically; the hourly
        // catch-up (khoebo:orders) only looks at events created from here.
        'orders_from' => env('KHOEBO_ORDERS_FROM', '2026-10-08'),
        // Where payments are recorded in Khoebo (its journal ids), by how
        // the organizer paid VENTIQ; any not set use the default.
        'journals' => [
            'default'       => env('KHOEBO_JOURNAL_ID'),
            'bank_transfer' => env('KHOEBO_JOURNAL_BANK'),
            'ecocash'       => env('KHOEBO_JOURNAL_ECOCASH'),
            'mpesa'         => env('KHOEBO_JOURNAL_MPESA'),
            'cash'          => env('KHOEBO_JOURNAL_CASH'),
        ],
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

        // The Sessions books: a company of its own in Khoebo (sventiq), with
        // its own address and token. Plans, pay-as-you-go sessions and
        // corporate invoices are invoiced and paid here.
        // `php artisan khoebo:products --books=sessions` makes its products.
        'sessions' => [
            'url'   => env('SESSIONS_KHOEBO_URL'),
            'token' => env('SESSIONS_KHOEBO_TOKEN'),
            // Payment journals in the Sessions company (GET /payment-journals).
            'journals' => [
                'default'       => env('SESSIONS_KHOEBO_JOURNAL_ID', env('SESSIONS_KHOEBO_JOURNAL_BANK')),
                'bank_transfer' => env('SESSIONS_KHOEBO_JOURNAL_BANK'),
                'ecocash'       => env('SESSIONS_KHOEBO_JOURNAL_ECOCASH'),
                'mpesa'         => env('SESSIONS_KHOEBO_JOURNAL_MPESA'),
                'cash'          => env('SESSIONS_KHOEBO_JOURNAL_CASH'),
            ],
            'tax_ids' => array_map('intval', array_filter(explode(',', (string) env('SESSIONS_KHOEBO_TAX_IDS', '')))),
            // Payments completed from this date go to Khoebo (the hourly catch-up looks no further back).
            'from' => env('SESSIONS_KHOEBO_FROM', '2026-10-22'),
            'products' => [
                'plan-team' => [
                    'name'       => 'VENTIQ Sessions: Team plan (one month)',
                    'sku'        => 'VQS-TEAM',
                    'type'       => 'service',
                    'sale_price' => 500,
                ],
                'plan-business' => [
                    'name'       => 'VENTIQ Sessions: Business plan (one month)',
                    'sku'        => 'VQS-BUSINESS',
                    'type'       => 'service',
                    'sale_price' => 1200,
                ],
                'payg' => [
                    'name'       => 'VENTIQ Sessions: pay-as-you-go sessions',
                    'sku'        => 'VQS-PAYG',
                    'type'       => 'service',
                    'sale_price' => 150, // a bundle's price is on its line
                ],
                'session' => [
                    'name'       => 'VENTIQ Sessions: session held',
                    'sku'        => 'VQS-SESSION',
                    'type'       => 'service',
                    'sale_price' => 0, // each corporate organization's agreed rate, on its invoice
                ],
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


    // The phone holding VENTIQ's merchant SIM forwards every SMS from the
    // mobile money senders to /api/mobile-money/sms, sending this token
    // (X-Ventiq-Token header, or ?token=). See docs/mobile-money-sms.md.
    'mobile_money' => [
        'token'   => env('MOBILE_MONEY_SMS_TOKEN'),
        'senders' => [
            'ecocash' => array_filter(explode(',', env('MOBILE_MONEY_ECOCASH_SENDERS', '199'))),
        ],
        // The tablet is expected to forward something at least this often
        // while events are selling; the money page warns after that.
        'quiet_hours' => (int) env('MOBILE_MONEY_QUIET_HOURS', 24),
    ],
];

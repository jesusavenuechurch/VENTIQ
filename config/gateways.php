<?php

return [
    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'paylesotho'),
    'paylesotho' => [
        // Master kill switch — every org shares these same merchant
        // credentials, so a merchant-activation problem on PayLesotho's side
        // is a platform-wide outage, not a per-org one. Flip this off in
        // .env to pull "Pay Online" everywhere at once (ticket checkout and
        // the session-plan self-serve upgrade both fall back to manual/
        // "contact us") without having to hunt down every org's individual
        // payment-method toggle.
        'enabled'  => env('PAYLESOTHO_ENABLED', true),
        'base_url' => env('PAYLESOTHO_BASE_URL', 'https://api.paylesotho.co.ls'),
        'token'    => env('PAYLESOTHO_API_TOKEN'),

        // Each merchant id/number is registered under its own business name
        // with PayLesotho — EcoCash and M-Pesa are separate merchant
        // accounts, not the same "Ventiq" account, so the name sent has to
        // match whichever one the request is for.
        'ecocash' => [
            'merchant_id'   => env('PAYLESOTHO_ECOCASH_MERCHANT_ID'),
            'merchant_name' => env('PAYLESOTHO_ECOCASH_MERCHANT_NAME'),
        ],
        'mpesa' => [
            'merchant_number' => env('PAYLESOTHO_MPESA_MERCHANT_NUMBER'),
            'merchant_name'   => env('PAYLESOTHO_MPESA_MERCHANT_NAME'),
        ],
    ],
];

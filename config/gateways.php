<?php

return [
    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'paylesotho'),
    'paylesotho' => [
        'base_url'      => env('PAYLESOTHO_BASE_URL', 'https://api.paylesotho.co.ls'),
        'token'         => env('PAYLESOTHO_API_TOKEN'),
        'merchant_name' => env('PAYLESOTHO_MERCHANT_NAME', 'Ventiq'),

        'ecocash' => [
            'merchant_id' => env('PAYLESOTHO_ECOCASH_MERCHANT_ID'),
        ],
        'mpesa' => [
            'merchant_number' => env('PAYLESOTHO_MPESA_MERCHANT_NUMBER'),
        ],
    ],
];

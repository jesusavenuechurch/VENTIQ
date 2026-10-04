<?php

return [
    'emails' => [
        'noreply' => env('MAIL_FROM_ADDRESS', 'noreply@ventiq.co.ls'),
        'support' => env('SUPPORT_EMAIL', 'support@ventiq.co.ls'),
        'info' => env('INFO_EMAIL', 'info@ventiq.co.ls'),
    ],

    // Hours an unpaid ticket holds its place before it can expire. Events
    // can override it (events.payment_window_hours). Unset = no deadline,
    // which is today's behaviour, until the default is decided.
    'payment_window_hours' => env('VENTIQ_PAYMENT_WINDOW_HOURS') ? (int) env('VENTIQ_PAYMENT_WINDOW_HOURS') : null,

    'company' => [
        'name' => 'VENTIQ',
        'tagline' => 'Smart Event Access for Lesotho',
        'website' => 'https://ventiq.co.ls',
        'phone' => '+266 6255 2155',
    ],
];
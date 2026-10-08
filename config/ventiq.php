<?php

return [
    'emails' => [
        'noreply' => env('MAIL_FROM_ADDRESS', 'noreply@ventiq.co.ls'),
        'support' => env('SUPPORT_EMAIL', 'support@ventiq.co.ls'),
        'info' => env('INFO_EMAIL', 'info@ventiq.co.ls'),
    ],

    // Hours an unpaid ticket holds its place before it can expire. Events
    // can override it (events.payment_window_hours). 0 = no deadline.
    'payment_window_hours' => (int) env('VENTIQ_PAYMENT_WINDOW_HOURS', 48) ?: null,

    'company' => [
        'name' => 'VENTIQ',
        'tagline' => 'Smart Event Access for Lesotho',
        'website' => 'https://ventiq.co.ls',
        'phone' => '+266 6255 2155',
    ],
];
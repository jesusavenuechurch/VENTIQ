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

    // Corporate Sessions invoices (App\Services\Sessions\CorporateBilling).
    'billing' => [
        // Days to pay a monthly invoice.
        'payment_days' => (int) env('VENTIQ_INVOICE_PAYMENT_DAYS', 14),
        // Printed on the invoice under "How to pay": bank account, mobile money number.
        'payment_instructions' => env('VENTIQ_INVOICE_PAYMENT_INSTRUCTIONS', 'Pay by bank transfer or mobile money, quoting the invoice number. Ask billing@ventiq.co.ls for the account details.'),
        'email' => env('VENTIQ_BILLING_EMAIL', 'billing@ventiq.co.ls'),
    ],

    'company' => [
        'name' => 'VENTIQ',
        'tagline' => 'Smart Event Access for Lesotho',
        'website' => 'https://ventiq.co.ls',
        'phone' => '+266 6255 2155',
    ],
];
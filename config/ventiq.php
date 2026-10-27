<?php

return [
    // Buying tickets for several people at once (one payment, a ticket each).
    // Built, and off until it's ready to go out.
    'group_purchase' => (bool) env('VENTIQ_GROUP_PURCHASE', false),
    'group_purchase_max' => 10,

    'emails' => [
        'noreply' => env('MAIL_FROM_ADDRESS', 'noreply@ventiq.co.ls'),
        'support' => env('SUPPORT_EMAIL', 'support@ventiq.co.ls'),
        'info' => env('INFO_EMAIL', 'info@ventiq.co.ls'),
    ],

    // Hours an unpaid ticket holds its place before it can expire. Events
    // can override it (events.payment_window_hours). 0 = no deadline.
    'payment_window_hours' => (int) env('VENTIQ_PAYMENT_WINDOW_HOURS', 48) ?: null,

    // VENTIQ's bank account: on the payment page, invoices and their emails
    // (App\Support\BankDetails). A transfer quotes its reference.
    'bank' => [
        'bank'           => env('VENTIQ_BANK_NAME', 'First National Bank Lesotho (FNB)'),
        'account_name'   => env('VENTIQ_BANK_ACCOUNT_NAME', 'Stastical Data Analysi'),
        'account_number' => env('VENTIQ_BANK_ACCOUNT_NUMBER', '63212657621'),
        'account_type'   => env('VENTIQ_BANK_ACCOUNT_TYPE', 'Business Cheque Account'),
        'branch'         => env('VENTIQ_BANK_BRANCH', 'Kingsway'),
        'branch_code'    => env('VENTIQ_BANK_BRANCH_CODE', '280261'),
        'swift'          => env('VENTIQ_BANK_SWIFT', 'FIRNLSMX'),
    ],

    // Corporate Sessions invoices (App\Services\Sessions\CorporateBilling).
    'billing' => [
        // Days to pay a monthly invoice.
        'payment_days' => (int) env('VENTIQ_INVOICE_PAYMENT_DAYS', 14),
        // Anything to add under "How to pay", after the bank account.
        'payment_instructions' => env('VENTIQ_INVOICE_PAYMENT_INSTRUCTIONS'),
        'email' => env('VENTIQ_BILLING_EMAIL', 'billing@ventiq.co.ls'),
    ],

    'company' => [
        'name' => 'VENTIQ',
        'tagline' => 'Smart Event Access for Lesotho',
        'website' => 'https://ventiq.co.ls',
        'phone' => '+266 6255 2155',
    ],
];
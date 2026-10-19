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

        // Appended as ?token= to the callback URL we send PayLesotho and
        // checked on every callback. Leave unset until the callback URL
        // PayLesotho holds includes it, or callbacks will be rejected.
        'callback_secret' => env('PAYLESOTHO_CALLBACK_SECRET'),

        // Each merchant id/number is registered under its own business name
        // with PayLesotho — EcoCash and M-Pesa are separate merchant
        // accounts, not the same "Ventiq" account, so the name sent has to
        // match whichever one the request is for.
        // Each driver is switched on separately: PayLesotho being available
        // doesn't mean every method it offers should be shown to attendees.
        // v2 holds the request open until the attendee enters their PIN,
        // then answers 200 (paid) or 415 (declined, low balance, wrong
        // number). v3 only answers 201 "initiated" and never says how it
        // ended, so VENTIQ uses v2 and waits for that answer in the
        // background (see App\Jobs\ChargeMobileMoney).
        'wait_seconds' => (int) env('PAYLESOTHO_WAIT_SECONDS', 120),
        // Pushes one ticket gets before the page offers another way to pay.
        'max_attempts' => (int) env('PAYLESOTHO_MAX_ATTEMPTS', 3),
        // How long the page waits for an answer before asking "Did you enter
        // your PIN?".
        'page_wait_seconds' => (int) env('PAYLESOTHO_PAGE_WAIT_SECONDS', 90),

        'ecocash' => [
            'enabled'       => env('PAYLESOTHO_ECOCASH_ENABLED', true),
            'endpoint'      => env('PAYLESOTHO_ECOCASH_ENDPOINT') ?: '/api/v2/econet/payment',
            // false = the old v3 "initiate only" endpoint.
            'waits_for_pin' => (bool) env('PAYLESOTHO_ECOCASH_WAITS_FOR_PIN', true),
            // Shown to attendees who pay the merchant by hand after the
            // pushes failed. Defaults to the merchant id PayLesotho uses.
            'merchant_code' => env('PAYLESOTHO_ECOCASH_MERCHANT_CODE') ?: env('PAYLESOTHO_ECOCASH_MERCHANT_ID'),
            'merchant_id'   => env('PAYLESOTHO_ECOCASH_MERCHANT_ID'),
            'merchant_name' => env('PAYLESOTHO_ECOCASH_MERCHANT_NAME'),
        ],
        'mpesa' => [
            // Off until VENTIQ has an M-Pesa merchant account with PayLesotho;
            // organizers see it as "coming soon".
            'enabled'         => env('PAYLESOTHO_MPESA_ENABLED', false),
            'merchant_number' => env('PAYLESOTHO_MPESA_MERCHANT_NUMBER'),
            'merchant_name'   => env('PAYLESOTHO_MPESA_MERCHANT_NAME'),
        ],
    ],
];

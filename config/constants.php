<?php

// config/constants.php

return [
    'currency' => [
        'code' => 'LSL',
        'symbol' => 'L',
        'name' => 'Lesotho Loti',
        'decimals' => 2,
    ],

    // VENTIQ's fees, charged on every ticket once it's active, whoever
    // collected the money (excl. VAT):
    //  - service fee: a percentage of the ticket price (nothing on free
    //    and complimentary tickets)
    //  - operational fee: a flat amount per person (a table of 3 pays it
    //    three times; free and complimentary tickets pay it too)
    // Online money has them deducted before payout; everything else is
    // invoiced to the organizer. A super admin can sponsor an event's fees.
    // Packages were VENTIQ's old way of charging (prepaid ticket quotas).
    // Fees per ticket replaced them and packages unlock nothing, so they're
    // not sold unless this is switched back on.
    'packages_for_sale' => (bool) env('VENTIQ_PACKAGES_FOR_SALE', false),

    'fees' => [
        'service_percent'        => (float) env('VENTIQ_SERVICE_FEE_PERCENT', 0.049),
        'operational_per_person' => (float) env('VENTIQ_OPERATIONAL_FEE', 7.50),
        // Fees on events before this date are never invoiced (the fee
        // model started on this branch; older events were sold under
        // packages).
        'invoice_from'           => env('VENTIQ_FEES_INVOICE_FROM', '2026-10-05'),
    ],

    'payment_methods' => [
        'cash' => [
            'label' => 'Cash Payment',
            'icon' => 'fa-money-bill-wave',
            'color' => 'text-green-600',
            'account_label' => null, // Cash doesn't need account
            'requires_account' => false,
        ],
        'ecocash' => [
            'label' => 'EcoCash',
            'icon' => 'fa-mobile-alt',
            'color' => 'text-blue-600',
            'account_label' => 'EcoCash Number',
            'requires_account' => true,
        ],
        'mpesa' => [
            'label' => 'M-Pesa',
            'icon' => 'fa-mobile-alt',
            'color' => 'text-red-600',
            'account_label' => 'M-Pesa Number',
            'requires_account' => true,
        ],
        'bank_transfer' => [
            'label' => 'Bank Transfer',
            'icon' => 'fa-university',
            'color' => 'text-purple-600',
            'account_label' => 'Bank Account Number',
            'requires_account' => true,
        ],
        'card' => [
            'label' => 'Card Payment',
            'icon' => 'fa-credit-card',
            'color' => 'text-orange-600',
            'account_label' => 'Merchant Code',
            'requires_account' => true,
        ],
        'online' => [
            'label' => 'Online Payment',
            'icon' => 'fa-globe',
            'color' => 'text-blue-600',
            'account_label' => null,
            'requires_account' => false,
        ],
        'free' => [
            'label' => 'Free',
            'icon' => 'fa-gift',
            'color' => 'text-gray-600',
            'account_label' => null,
            'requires_account' => false,
        ],
    ],

    'payment_statuses' => [
        'pending' => 'Pending',
        'partial' => 'Partial Payment',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],

    'ticket_statuses' => [
        'pending' => 'Pending',
        'active' => 'Active',
        'checked_in' => 'Checked In',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ],

    'delivery_methods' => [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'in_person' => 'In Person',
    ],

    'event_statuses' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

     'delivery_methods' => [
        'whatsapp' => [
            'label' => 'WhatsApp',
            'icon' => 'fa-brands fa-whatsapp',
            'color' => 'text-green-600',
        ],
        'email' => [
            'label' => 'Email',
            'icon' => 'fa-envelope',
            'color' => 'text-blue-600',
        ],
        'both' => [
            'label' => 'WhatsApp & Email',
            'icon' => 'fa-paper-plane',
            'color' => 'text-purple-600',
        ],
    ],

    'delivery_statuses' => [
        'pending' => [
            'label' => 'Pending Delivery',
            'color' => 'warning',
            'icon' => 'fa-clock',
        ],
        'sent' => [
            'label' => 'Sent',
            'color' => 'info',
            'icon' => 'fa-paper-plane',
        ],
        'delivered' => [
            'label' => 'Delivered',
            'color' => 'success',
            'icon' => 'fa-check-circle',
        ],
        'failed' => [
            'label' => 'Failed',
            'color' => 'danger',
            'icon' => 'fa-exclamation-circle',
        ],
    ],

        'event_types' => [
        'standard' => [
            'label'       => 'Standard Event',
            'description' => 'Conferences, concerts, church events, launches, general registrations.',
            'icon'        => 'heroicon-o-calendar',
            'color'       => 'info',
            'workshop'    => false, // drives whether workshop features activate
        ],
        'workshop' => [
            'label'       => 'Workshop / Training',
            'description' => 'Donor workshops, ministry trainings, HR sessions, funded programs.',
            'icon'        => 'heroicon-o-academic-cap',
            'color'       => 'warning',
            'workshop'    => true,
        ],
        // Future types — uncomment when ready, no migration needed:
        // 'conference' => [
        //     'label'       => 'Conference',
        //     'description' => 'Multi-day conferences with sessions and speakers.',
        //     'icon'        => 'heroicon-o-microphone',
        //     'color'       => 'success',
        //     'workshop'    => false,
        // ],
        // 'hybrid' => [
        //     'label'       => 'Hybrid Event',
        //     'description' => 'In-person and virtual attendance combined.',
        //     'icon'        => 'heroicon-o-globe-alt',
        //     'color'       => 'purple',
        //     'workshop'    => false,
        // ],
    ],

     // ── Workshop Districts ────────────────────────────────────────────────────
    // Lesotho's 10 districts — add or rename without touching the database
    ///TODO: needs work, districts are districts and can be reused, so workshop_distrcit is wrong
    'workshop_districts' => [
        'maseru'        => 'Maseru',
        'berea'         => 'Berea',
        'leribe'        => 'Leribe',
        'butha_buthe'   => 'Butha-Buthe',
        'teyateyaneng'  => 'TY (Teyateyaneng)',
        'mafeteng'      => 'Mafeteng',
        'mohales_hoek'  => 'Mohale\'s Hoek',
        'quthing'       => 'Quthing',
        'qacha_nek'     => 'Qacha\'s Nek',
        'mokhotlong'    => 'Mokhotlong',
        'thaba_tseka'   => 'Thaba-Tseka',
        'other'         => 'Other / Outside Lesotho',
    ],
    
 
    // ── Workshop Signature Statuses ───────────────────────────────────────────
    'signature_statuses' => [
        'pending'  => [
            'label' => 'Awaiting Signature',
            'color' => 'warning',
        ],
        'signed'   => [
            'label' => 'Signed',
            'color' => 'success',
        ],
        'declined' => [
            'label' => 'Declined',
            'color' => 'danger',
        ],
        'skipped'  => [
            'label' => 'Skipped at Gate',
            'color' => 'gray',
        ],
    ],
    'payment' => [
        'surcharge_rate'    => 0.05,   // legacy MoPay ticket checkout only (retired)
        // What the payment gateway (PayLesotho) keeps from each online
        // payment. VENTIQ absorbs it: attendees pay the ticket price and
        // organizers aren't charged it. Used to report VENTIQ's net.
        'gateway_fee_rate'  => (float) env('VENTIQ_GATEWAY_FEE_RATE', 0.01),
    ],

        'categories' => [
        'music' => ['label' => 'Music', 'color' => '#D4537E'],
        'business' => ['label' => 'Business', 'color' => '#378ADD'],
        'sports' => ['label' => 'Sports', 'color' => '#639922'],
        'worship' => ['label' => 'Worship', 'color' => '#7F77DD'],
        'education' => ['label' => 'Education', 'color' => '#BA7517'],
        'markets' => ['label' => 'Markets', 'color' => '#F07F22'],
        'arts' => ['label' => 'Arts', 'color' => '#534AB7'],
        'community' => ['label' => 'Community', 'color' => '#0F6E56'],
    ],
 
    'districts' => [
        'Maseru',
        'Leribe',
        'Berea',
        'Mafeteng',
        "Mohale's Hoek",
        'Quthing',
        'Qacha\'s Nek',
        'Mokhotlong',
        'Thaba-Tseka',
        'Butha-Buthe',
    ],

    /*
     | Every WhatsApp template VENTIQ sends, in one place (docs/whatsapp-templates.md
     | has the wording). Meta must approve a template before it can be sent:
     | set 'approved' => true once it is, and that message starts going out.
     | Until then people with an email address get the same message by email.
     |   name   — the template's name in WhatsApp Manager
     |   button — whether it has a "Visit website" button (https://<domain>/{{1}})
     */
    'whatsapp_templates' => [
        // Attendees
        'ticket_ready'      => ['name' => 'ticket_ready',      'approved' => true,  'button' => true],
        'ticket_registered' => ['name' => 'ticket_registered', 'approved' => true,  'button' => false],
        'payment_failed'    => ['name' => 'payment_failed',    'approved' => true,  'button' => true],
        'payment_reminder'  => ['name' => 'payment_reminder',  'approved' => true,  'button' => true],
        'payment_rejected'  => ['name' => 'payment_rejected',  'approved' => true,  'button' => true],
        'payment_expired'   => ['name' => 'payment_expired',   'approved' => true,  'button' => false],
        // Organizers
        'payment_submitted' => ['name' => 'payment_submitted', 'approved' => true,  'button' => true],
        'payment_unfinished' => ['name' => 'payment_unfinished', 'approved' => false, 'button' => true],
        // VENTIQ Sessions
        'thank_you'         => ['name' => 'thank_you',         'approved' => true,  'button' => false],
    ],
];

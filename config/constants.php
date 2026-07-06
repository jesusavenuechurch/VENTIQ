<?php

// config/constants.php

return [
    'currency' => [
        'code' => 'LSL',
        'symbol' => 'L',
        'name' => 'Lesotho Loti',
        'decimals' => 2,
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
        'surcharge_rate'    => 0.05,   // 5% added to ticket price, paid by attendee
        'gateway_fee_rate'  => 0.025,  // 2.5% MoPay takes from gross_paid
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
];

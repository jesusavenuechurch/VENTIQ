<?php

namespace App\Support;

class PackageDefinition
{
    public static function all(): array
    {
        return [
            'starter' => [
                'name'              => 'Starter',
                'price'             => 250.00,
                'events'            => 1,
                'tickets'           => 50,
                'comp_tickets'      => 2,
                'max_scanners'      => 1,
                'max_users'         => 1,
                'overage_rate'      => 10.00,
                'description'       => 'Small community events, churches, workshops.',
                'features' => [
                    'public_events'       => true,
                    'private_events'      => false,
                    'ticket_tiers'        => false,
                    'bulk_upload'         => false,
                    'complimentary'       => true,
                    'table_tickets'       => false,
                    'installments'        => false,
                    'advanced_reporting'  => false,
                    'export'              => false,
                    'role_permissions'    => false,
                    'whatsapp_delivery'   => true,
                    'email_delivery'      => true,
                    'priority_support'    => false,
                ],
            ],

            'standard' => [
                'name'              => 'Standard',
                'price'             => 600.00,
                'events'            => 1,
                'tickets'           => 150,
                'comp_tickets'      => 10,
                'max_scanners'      => 2,
                'max_users'         => 3,
                'overage_rate'      => 8.00,
                'description'       => 'Growing organisers, seminars, medium paid events.',
                // These are the BASE features — add-ons can unlock the false ones
                'features' => [
                    'public_events'       => true,
                    'private_events'      => false, // available as add-on
                    'ticket_tiers'        => true,
                    'bulk_upload'         => false, // available as add-on
                    'complimentary'       => true,
                    'table_tickets'       => false, // available as add-on
                    'installments'        => false, // available as add-on
                    'advanced_reporting'  => false,
                    'export'              => false,
                    'role_permissions'    => true,
                    'whatsapp_delivery'   => true,
                    'email_delivery'      => true,
                    'priority_support'    => false,
                ],
            ],

            'professional' => [
                'name'              => 'Professional',
                'price'             => 1200.00,
                'events'            => 1,
                'tickets'           => 300,
                'comp_tickets'      => 25,
                'max_scanners'      => 5,
                'max_users'         => 10,
                'overage_rate'      => 6.00,
                'description'       => 'Conferences, medium festivals, professional organisers.',
                'features' => [
                    'public_events'       => true,
                    'private_events'      => true,
                    'ticket_tiers'        => true,
                    'bulk_upload'         => true,
                    'complimentary'       => true,
                    'table_tickets'       => true,
                    'installments'        => true,
                    'advanced_reporting'  => true,
                    'export'              => true,
                    'role_permissions'    => true,
                    'whatsapp_delivery'   => true,
                    'email_delivery'      => true,
                    'priority_support'    => true,
                    'organizational_records'=> true,
                ],
            ],

            'enterprise' => [
                'name'              => 'Enterprise',
                'price'             => 0.00, // custom
                'events'            => 999,
                'tickets'           => 999999,
                'comp_tickets'      => 999,
                'max_scanners'      => 999,
                'max_users'         => 999,
                'overage_rate'      => 0.00,
                'description'       => 'Custom pricing based on operational needs.',
                'features' => [
                    'public_events'       => true,
                    'private_events'      => true,
                    'ticket_tiers'        => true,
                    'bulk_upload'         => true,
                    'complimentary'       => true,
                    'table_tickets'       => true,
                    'installments'        => true,
                    'advanced_reporting'  => true,
                    'export'              => true,
                    'role_permissions'    => true,
                    'whatsapp_delivery'   => true,
                    'email_delivery'      => true,
                    'priority_support'    => true,
                    'organizational_records'=> true,
                ],
            ],

            'free_trial' => [
                'name'              => 'Free Trial',
                'price'             => 0.00,
                'events'            => 1,
                'tickets'           => 150, // mirrors standard
                'comp_tickets'      => 10,
                'max_scanners'      => 2,
                'max_users'         => 3,
                'overage_rate'      => 8.00,
                'description'       => 'Trial access mirroring Standard features.',
                'features' => [
                    'public_events'       => true,
                    'private_events'      => false,
                    'ticket_tiers'        => true,
                    'bulk_upload'         => false,
                    'complimentary'       => true,
                    'table_tickets'       => false,
                    'installments'        => false,
                    'advanced_reporting'  => false,
                    'export'              => false,
                    'role_permissions'    => true,
                    'whatsapp_delivery'   => true,
                    'email_delivery'      => true,
                    'priority_support'    => false,
                ],
            ],
        ];
    }

    public static function get(string $type): array
    {
        return self::all()[$type] ?? self::all()['starter'];
    }

    public static function featuresFor(string $type): array
    {
        return self::get($type)['features'];
    }

    public static function availableAddOns(): array
    {
        // Add-ons only make sense for Standard
        return [
            'private_events' => ['label' => 'Private Event Mode',    'price' => 150.00],
            'bulk_upload'    => ['label' => 'Excel/CSV Bulk Upload',  'price' => 100.00],
            'table_tickets'  => ['label' => 'Table Tickets',          'price' => 100.00],
            'installments'   => ['label' => 'Installment Payments',   'price' => 150.00],
            'organizational_records'=> ['label' => 'Organizational Records',   'price' => 200.00],
        ];
    }
}
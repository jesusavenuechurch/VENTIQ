<?php

namespace App\Services\Khoebo;

use App\Models\Organization;

/**
 * Organizers are VENTIQ's customers in Khoebo: each organization is made a
 * customer once, the first time it's needed, and Khoebo's id is kept on
 * the organization.
 */
class KhoeboCustomers
{
    public function __construct(private KhoeboClient $khoebo) {}

    /** @return int the organization's Khoebo customer id */
    public function ensure(Organization $organization): int
    {
        if ($organization->khoebo_customer_id) {
            return (int) $organization->khoebo_customer_id;
        }

        $customer = $this->khoebo->create('customers', $this->body($organization), "ventiq-customer-org-{$organization->id}");

        $organization->forceFill(['khoebo_customer_id' => $customer['id']])->save();

        return (int) $customer['id'];
    }

    public function body(Organization $organization): array
    {
        // The organization's admin, for contact details it doesn't have.
        $owner = $organization->members()->whereHas('roles', fn ($q) => $q->where('name', 'org_admin'))->oldest('id')->first()
            ?? $organization->members()->oldest('id')->first();

        return array_filter([
            'name'               => $organization->name,
            'is_company'         => true,
            'email'              => $organization->contact_email ?: $organization->email ?: $owner?->email,
            'phone'              => $organization->phone ?: $owner?->phone,
            'website'            => $organization->website,
            'external_reference' => "ventiq-org-{$organization->id}",
            'address'            => ['country_code' => 'LS'],
        ], fn ($value) => filled($value));
    }
}

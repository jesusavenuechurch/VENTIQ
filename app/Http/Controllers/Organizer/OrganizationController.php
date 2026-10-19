<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationDetailsRequest;
use Illuminate\Http\Request;

/**
 * The organization's own details after setup: name, logo, contact details
 * attendees see. Shared by VENTIQ Events and Sessions. The web address
 * (slug) isn't editable here: posters, QR codes and links already shared
 * point at it.
 */
class OrganizationController extends Controller
{
    public function edit(Request $request)
    {
        $organization = $request->attributes->get('organization');

        return \Inertia\Inertia::render('Organizer/Organization', [
            'organization' => [
                'name'         => $organization->name,
                'tagline'      => $organization->tagline,
                'phone'        => $organization->phone ? substr(preg_replace('/\D/', '', $organization->phone), -8) : '',
                'contactEmail' => $organization->contact_email,
                'website'      => $organization->website,
                'description'  => $organization->description,
                'logoUrl'      => $organization->logo_path ? \Illuminate\Support\Facades\Storage::url($organization->logo_path) : null,
            ],
            'canEdit'      => $request->user()->can('edit_organization'),
            // The organization's own events page: super admins only until it's sold.
            'eventsPage'   => $request->user()->isSuperAdmin() ? [
                'url'   => route('public.events', $organization->slug),
                'fixed' => $organization->events()->exists(),
            ] : null,
            'meta'         => ['title' => 'Organization | VENTIQ'],
        ]);
    }

    public function update(OrganizationDetailsRequest $request)
    {
        abort_unless($request->user()->can('edit_organization'), 403);

        $organization = $request->attributes->get('organization');
        $organization->update($request->details());

        return redirect()->route('organizer.organization.edit')->with('status', 'Saved. Your events and tickets now show these details.');
    }
}

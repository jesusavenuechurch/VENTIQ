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
        return view('organizer.organization', [
            'organization' => $request->attributes->get('organization'),
            'canEdit'      => $request->user()->can('edit_organization'),
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

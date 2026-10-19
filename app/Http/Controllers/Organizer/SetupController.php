<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationDetailsRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** The organizer's first stop when sign-up didn't ask about their organization. */
class SetupController extends Controller
{
    public function show(Request $request)
    {
        $organization = $request->attributes->get('organization');

        return Inertia::render('Organizer/Setup', [
            'organization' => [
                'name'         => $organization->name,
                'phone'        => $organization->phone ? substr($organization->phone, -8) : '',
                'contactEmail' => $organization->contact_email,
                'description'  => $organization->description,
            ],
            'meta' => ['title' => 'Set up your organization | VENTIQ'],
        ]);
    }

    public function store(OrganizationDetailsRequest $request)
    {
        $organization = $request->attributes->get('organization');
        $organization->update($request->details());

        return redirect()->route('organizer.home')->with('status', "You're all set, {$organization->name}. Create your first event when you're ready.");
    }
}

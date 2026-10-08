<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationDetailsRequest;
use Illuminate\Http\Request;

/** The organizer's first stop when sign-up didn't ask about their organization. */
class SetupController extends Controller
{
    public function show(Request $request)
    {
        return view('organizer.setup', [
            'organization' => $request->attributes->get('organization'),
        ]);
    }

    public function store(OrganizationDetailsRequest $request)
    {
        $organization = $request->attributes->get('organization');
        $organization->update($request->details());

        return redirect()->route('organizer.home')->with('status', "You're all set, {$organization->name}. Create your first event when you're ready.");
    }
}

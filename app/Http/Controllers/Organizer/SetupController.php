<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The organizer's first stop when sign-up didn't ask about their organization. */
class SetupController extends Controller
{
    public function show(Request $request)
    {
        return view('organizer.setup', [
            'organization' => $request->attributes->get('organization'),
        ]);
    }

    public function store(Request $request)
    {
        $organization = $request->attributes->get('organization');

        $request->merge(['phone' => '+266' . substr(preg_replace('/\D/', '', (string) $request->input('phone')), -8)]);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization->id)],
            'phone'         => ['required', 'regex:/^\+266[0-9]{8}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'logo'          => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ], [
            'name.unique'  => 'Another organization already uses that name on VENTIQ. Add something to tell yours apart, e.g. your town.',
            'phone.regex'  => 'Enter an 8-digit Lesotho number.',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('organization-logos', 'public');
        }
        unset($data['logo']);

        $organization->update($data);

        return redirect()->route('organizer.home')->with('status', "You're all set, {$organization->name}. Create your first event when you're ready.");
    }
}

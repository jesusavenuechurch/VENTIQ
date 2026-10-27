<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lets a super admin open the organizer area for one organization, to
 * help or correct things on its behalf, and get back to the admin after.
 */
class ActAsController extends Controller
{
    public function start(Request $request, Organization $organization)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        session([CurrentOrganization::SESSION_KEY => $organization->id]);
        Log::info("Super admin {$request->user()->id} opened the organizer area for organization {$organization->id}");

        // Creating an event for it (from the admin's Events page).
        if ($request->query('to') === 'create') {
            return redirect()->route('organizer.events.create');
        }

        // Straight to one of its events (from the admin's events and tickets lists).
        $event = $request->integer('event') ? $organization->events()->find($request->integer('event')) : null;
        if ($event) {
            return redirect()->route($request->query('to') === 'edit' ? 'organizer.events.edit' : 'organizer.events.attendees',
                array_filter([$event, 'q' => $request->query('q')]));
        }

        return redirect()->route('organizer.home');
    }

    public function stop(Request $request)
    {
        $request->session()->forget(CurrentOrganization::SESSION_KEY);

        return redirect()->route('ventiq.organizations.index');
    }
}

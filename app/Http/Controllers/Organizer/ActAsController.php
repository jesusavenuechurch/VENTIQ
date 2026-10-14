<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lets a super admin open the organizer area for one organization, to
 * help or correct things on its behalf, and get back to Filament after.
 */
class ActAsController extends Controller
{
    public function start(Request $request, Organization $organization)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        session([CurrentOrganization::SESSION_KEY => $organization->id]);
        Log::info("Super admin {$request->user()->id} opened the organizer area for organization {$organization->id}");

        return redirect()->route('organizer.home');
    }

    public function stop(Request $request)
    {
        $request->session()->forget(CurrentOrganization::SESSION_KEY);

        return redirect()->route('filament.admin.organization.resources.organizations.index');
    }
}

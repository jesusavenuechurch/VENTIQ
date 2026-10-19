<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * The React (Inertia) pages: their root view and the props every page gets.
 * Blade pages are untouched; a route answers with Inertia::render() once
 * its page has moved to resources/js/Pages.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'inertia';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared with every page (see resources/js/types/index.d.ts).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'firstName'    => \Illuminate\Support\Str::before($user->name ?? 'Account', ' '),
                    'email'        => $user->email,
                    'avatar'       => \App\Support\Avatar::url($user->email),
                    'isSuperAdmin' => $user->isSuperAdmin(),
                    'organization' => $user->organization ? ['id' => $user->organization->id, 'name' => $user->organization->name] : null,
                    // The organization whose area they're in (a super admin acting for one too).
                    'currentOrganizationId' => \App\Support\CurrentOrganization::id(),
                ] : null,
            ],
            // For the site layout (resources/js/Layouts/SiteLayout.tsx).
            'site' => [
                'districts' => config('constants.districts'),
            ],
            // The "status" flash every controller already uses.
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error'  => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

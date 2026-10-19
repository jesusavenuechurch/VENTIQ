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
                    'email'        => $user->email,
                    'isSuperAdmin' => $user->isSuperAdmin(),
                    'organization' => $user->organization ? ['id' => $user->organization->id, 'name' => $user->organization->name] : null,
                ] : null,
            ],
            // The "status" flash every controller already uses.
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}

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
            // Super admins: what's waiting for them, as a count on the admin tab.
            'adminWaiting' => fn () => $user?->isSuperAdmin() ? [
                'ventiq.session-plans.index' => \App\Models\PaymentSession::where('gateway', \App\Services\Sessions\BankTransfers::GATEWAY)
                    ->where('payable_type', 'session_package')->where('status', 'pending')->count(),
                'ventiq.money.index' => \App\Models\PaymentSession::where('payable_type', 'ticket')->whereIn('gateway', ['paylesotho', 'bank'])
                    ->where('status', 'pending')->whereNotNull('purchase_meta')->where('created_at', '>', now()->subDays(7))->count(),
            ] : null,
            // The "status" flash every controller already uses.
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error'  => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

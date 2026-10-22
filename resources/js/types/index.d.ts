import type { route as routeFn } from 'ziggy-js';

/** Props every page gets (App\Http\Middleware\HandleInertiaRequests). */
export interface SharedProps {
    auth: {
        user: {
            id: number;
            name: string;
            firstName: string;
            email: string;
            avatar: string;
            isSuperAdmin: boolean;
            organization: { id: number; name: string } | null;
            currentOrganizationId: number | null;
        } | null;
    };
    site: { districts: string[] };
    flash: { status: string | null; error: string | null };
    errors: Record<string, string>;
    /** Link preview, set by the server (resources/views/inertia.blade.php). */
    meta?: { title?: string; description?: string; image?: string };
    [key: string]: unknown;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}

declare global {
    // Ziggy's route(), from the @routes directive.
    const route: typeof routeFn;
}

/** The organizer area's own shared props (App\Http\Middleware\EnsureOrganizerAccess). */
export interface OrganizerProps {
    organization: { id: number; name: string; slug: string };
    actingAs: boolean;
    paymentsToConfirm: number;
    usesEvents: boolean;
    can: Record<
        | 'approve_payment' | 'create_event' | 'edit_event' | 'delete_event' | 'edit_organization'
        | 'view_payment_method' | 'create_payment_method' | 'edit_payment_method' | 'delete_payment_method'
        | 'manage_staff' | 'view_reports',
        boolean
    >;
}

/** The Sessions area's shared props (App\Http\Middleware\EnsureSessionsAccess). */
export interface SessionsArea {
    organization: string;
    can: Record<'desk' | 'checkin' | 'view' | 'run' | 'plan', boolean>;
    needsReview: number;
    overdueActions: number;
    /** An Admin allowed them to sign certificates. */
    canSign: boolean;
    /** On a corporate rate there's no included number: sessions are invoiced monthly. */
    quota: { used: number; included: number | null; remaining: number; corporate: boolean };
}

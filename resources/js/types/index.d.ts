import type { route as routeFn } from 'ziggy-js';

/** Props every page gets (App\Http\Middleware\HandleInertiaRequests). */
export interface SharedProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            isSuperAdmin: boolean;
            organization: { id: number; name: string } | null;
        } | null;
    };
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

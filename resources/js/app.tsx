import '../css/inertia.css';
import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { createRoot } from 'react-dom/client';
import { AdminShell } from '@/Layouts/AdminLayout';
import { OrganizerShell } from '@/Layouts/OrganizerLayout';
import { SessionsShell } from '@/Layouts/SessionsLayout';
import { SettingsShell } from '@/Layouts/SettingsLayout';
import { CookieNotice } from '@/components/site/cookie-notice';

// Pages live in resources/js/Pages; a controller names one with
// Inertia::render('Tickets/Find').
const pages = import.meta.glob('./Pages/**/*.tsx');

type Page = { default: { layout?: unknown } };

/**
 * An area's shell (the site header and the area's tabs) stays mounted
 * while moving between its pages, instead of being drawn again on each:
 * nothing jumps, and the tab row keeps where it was scrolled to.
 */
function shellFor(name: string) {
    if (name.startsWith('Ventiq/')) return AdminShell;
    if (['Organizer/Organization', 'Organizer/Payout', 'Organizer/Team'].includes(name)) return SettingsShell;
    if (['Organizer/Setup'].includes(name)) return null;
    if (name.startsWith('Organizer/')) return OrganizerShell;
    if (name.startsWith('Programmes/')) return SessionsShell;
    // The live console and the pass are pages of their own.
    if (name.startsWith('Sessions/') && !['Sessions/Show', 'Sessions/CheckinPass'].includes(name)) return SessionsShell;
    return null;
}

createInertiaApp({
    title: (title) => (title ? `${title} | VENTIQ` : 'VENTIQ'),
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`No page at resources/js/Pages/${name}.tsx`);
        }
        const module = (await page()) as Page;
        const Shell = shellFor(name);
        if (Shell && !module.default.layout) {
            module.default.layout = (content: React.ReactNode) => <Shell>{content}</Shell>;
        }
        return module.default as never;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <CookieNotice />
                <Toaster />
            </>,
        );
    },
    progress: { color: '#F07F22' },
});

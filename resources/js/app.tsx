import '../css/inertia.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// Pages live in resources/js/Pages; a controller names one with
// Inertia::render('Tickets/Find').
const pages = import.meta.glob('./Pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} | VENTIQ` : 'VENTIQ'),
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`No page at resources/js/Pages/${name}.tsx`);
        }
        return ((await page()) as { default: never }).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#F07F22' },
});

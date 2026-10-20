import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export interface AreaTab {
    route: string;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    /** Route names (with *) this tab is the one for. */
    active: string[];
    badge?: number;
}

const read = (key: string): Record<string, string> => {
    try {
        return JSON.parse(window.sessionStorage.getItem(key) ?? '{}');
    } catch {
        return {};
    }
};

/**
 * Which tab is on, and where each tab leads. With `remember`, a tab works
 * like a phone's tab bar: it goes back to the page you left it on (a form
 * half filled in, a filtered list), and tapping the tab you're on goes to
 * its main page.
 */
export function useAreaTabs(area: string, tabs: AreaTab[], remember = false) {
    const { url } = usePage();
    const key = `tabs:${area}`;
    const isOn = (tab: AreaTab) => tab.active.some((p) => route().current(p));
    const current = tabs.find(isOn);

    useEffect(() => {
        if (!remember || !current) return;
        try {
            window.sessionStorage.setItem(key, JSON.stringify({ ...read(key), [current.route]: url }));
        } catch {
            // Private mode: tabs just go to their main pages.
        }
    }, [remember, key, current, url]);

    const href = (tab: AreaTab) => {
        const home = route(tab.route);
        if (!remember || isOn(tab)) return home;
        return read(key)[tab.route] ?? home;
    };

    return { isOn, href };
}

/** Forget where a tab was left, e.g. once the form it was on is sent. */
export function forgetTab(area: string, tabRoute: string) {
    try {
        const key = `tabs:${area}`;
        const all = read(key);
        delete all[tabRoute];
        window.sessionStorage.setItem(key, JSON.stringify(all));
    } catch {
        // Nothing stored.
    }
}

/** Keeps the tab you're on in view in a row that scrolls sideways. */
export function useActiveInView<T extends HTMLElement>() {
    const ref = useRef<T>(null);
    const { url } = usePage();
    useEffect(() => {
        ref.current?.querySelector('[aria-current="page"]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }, [url]);
    return ref;
}

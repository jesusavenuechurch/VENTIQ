import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowLeftRight, Building2, ShieldUser, UsersRound } from 'lucide-react';
import { useEffect, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { type AreaTab, useAreaTabs } from '@/components/area-tabs';
import { Alert } from '@/components/ui/alert';
import { cn } from '@/lib/utils';
import type { OrganizerProps } from '@/types';

export type SettingsFrom = 'events' | 'sessions';
const KEY = 'settings:from';

/**
 * Where settings were opened from: `?from=` on the link, remembered for
 * the visit; otherwise Events if the organization sells tickets, else
 * Sessions.
 */
export function useSettingsFrom(): SettingsFrom {
    const { organizer } = usePage<{ organizer: OrganizerProps }>().props;
    const fallback: SettingsFrom = organizer.usesEvents ? 'events' : 'sessions';
    const [from, setFrom] = useState<SettingsFrom>(fallback);

    useEffect(() => {
        const asked = new URLSearchParams(window.location.search).get('from');
        try {
            if (asked === 'events' || asked === 'sessions') window.sessionStorage.setItem(KEY, asked);
            const kept = window.sessionStorage.getItem(KEY);
            setFrom(kept === 'events' || kept === 'sessions' ? kept : fallback);
        } catch {
            setFrom(asked === 'events' || asked === 'sessions' ? asked : fallback);
        }
    }, [fallback]);

    return from;
}

const SECTIONS: AreaTab[] = [
    { route: 'organizer.organization.edit', label: 'Organization', icon: Building2, active: ['organizer.organization.*'] },
    { route: 'organizer.payout.edit', label: 'Payouts', icon: ArrowLeftRight, active: ['organizer.payout.*'] },
    { route: 'organizer.team.index', label: 'Team', icon: UsersRound, active: ['organizer.team.*'] },
];

/**
 * Settings belong to the organization, not to Events or Sessions: their
 * own header, their three sections, and a way back to where you were.
 * Kept mounted while moving between the sections (see app.tsx).
 */
export function SettingsShell({ children }: { children: React.ReactNode }) {
    const { organizer } = usePage<{ organizer: OrganizerProps }>().props;
    const from = useSettingsFrom();
    const { isOn } = useAreaTabs('settings', SECTIONS);
    const back = from === 'sessions' ? { href: route('sessions.index'), label: 'Back to Sessions' } : { href: route('organizer.home'), label: 'Back to Events' };

    return (
        <SiteLayout>
            <div className="mx-auto max-w-5xl px-4 py-8">
                {organizer.actingAs && (
                    <div className="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p className="text-[11px] font-bold text-amber-800">
                            <ShieldUser className="mr-1 inline size-4" />
                            Super admin view of <strong>{organizer.organization.name}</strong>. Changes you make here are made on their behalf.
                        </p>
                        <button type="button" onClick={() => router.post(route('organizer.act-as.stop'))} className="rounded-full bg-amber-600 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-amber-700">Back to admin</button>
                    </div>
                )}

                <Link href={back.href} className="inline-flex items-center gap-1 text-[11px] font-bold text-gray-400 hover:text-brand"><ArrowLeft className="size-3" />{back.label}</Link>
                <p className="mt-4 text-[10px] font-black uppercase tracking-[0.3em] text-gray-300">{organizer.organization.name}</p>
                <h1 className="mt-1 text-2xl font-black tracking-tight text-brand">Organization settings</h1>
                <p className="mt-1 text-[13px] font-medium text-gray-500">Shared by Events and Sessions.</p>

                <nav aria-label="Settings" className="mb-6 mt-5 flex w-full gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 sm:w-fit">
                    {SECTIONS.map((s) => {
                        const on = isOn(s);
                        return (
                            <Link key={s.route} href={route(s.route)} prefetch aria-current={on ? 'page' : undefined} className={cn('flex-1 whitespace-nowrap rounded-xl px-4 py-2.5 text-center text-[11px] font-black uppercase tracking-widest transition-colors sm:flex-none', on ? 'bg-white text-brand shadow-sm' : 'text-gray-500 hover:text-brand')}>
                                <s.icon className="mr-1 inline size-3.5" />{s.label}
                            </Link>
                        );
                    })}
                </nav>

                {children}
            </div>
        </SiteLayout>
    );
}

/** A settings section's own part: what it's for, and any message. */
export default function SettingsLayout({ subtitle, children }: { subtitle?: React.ReactNode; children: React.ReactNode }) {
    const { flash } = usePage().props;

    return (
        <>
            {subtitle && <p className="mb-6 text-[13px] font-medium text-gray-500">{subtitle}</p>}
            {flash.status && <Alert className="mb-6 rounded-2xl p-4 text-[12px]">{flash.status}</Alert>}
            {flash.error && <Alert variant="destructive" className="mb-6 rounded-2xl p-4 text-[12px]">{flash.error}</Alert>}
            {children}
        </>
    );
}

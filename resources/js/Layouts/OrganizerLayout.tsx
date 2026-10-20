import { router, usePage } from '@inertiajs/react';
import { AppLink } from '@/components/app-link';
import { CalendarDays, ChevronRight, ArrowLeft, EyeOff, Receipt, Settings, ShieldUser, Wallet } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { PublishButton } from '@/components/organizer/publish-button';
import { Alert } from '@/components/ui/alert';
import type { OrganizerProps } from '@/types';
import { cn } from '@/lib/utils';

interface Tab {
    route: string;
    label: string;
    short: string;
    icon: React.ComponentType<{ className?: string }>;
    active: string[];
    badge?: number;
    params?: Record<string, string>;
}

/**
 * Every organizer page: who we're looking at, the area's tabs, where this
 * page sits (Events › Summit › Edit), its title, and on a draft event's
 * pages, that it isn't live yet with Publish right there. On phones the
 * tabs are an icon bar at the bottom, in thumb reach.
 */
export default function OrganizerLayout({
    title,
    subtitle,
    crumbs,
    draftEventId,
    children,
}: {
    title: string;
    subtitle?: React.ReactNode;
    /** [[label, url], ..., [label]]: the last is this page. */
    crumbs?: Array<[string, string?]>;
    /** On a draft event's pages: its id, for Publish. */
    draftEventId?: number | null;
    children: React.ReactNode;
}) {
    const { organizer, flash } = usePage<{ organizer: OrganizerProps }>().props;

    const tabs: Tab[] = [
        { route: 'organizer.home', label: 'Events', short: 'Events', icon: CalendarDays, active: ['organizer.home', 'organizer.events.*'] },
        { route: 'organizer.payments.index', label: 'Payments to confirm', short: 'To confirm', icon: Receipt, active: ['organizer.payments.*'], badge: organizer.paymentsToConfirm },
        ...(organizer.can.view_payment_method ? [{ route: 'organizer.accounts.index', label: 'Payment accounts', short: 'Accounts', icon: Wallet, active: ['organizer.accounts.*'] }] : []),
        { route: 'organizer.organization.edit', label: 'Settings', short: 'Settings', icon: Settings, active: ['organizer.organization.*', 'organizer.payout.*', 'organizer.team.*'], params: { from: 'events' } },
    ];
    const isOn = (patterns: string[]) => patterns.some((p) => route().current(p));

    const bottomBar = (
        <nav className="border-t border-gray-100 bg-white pb-[env(safe-area-inset-bottom)]">
            <div className={cn('grid', tabs.length === 4 ? 'grid-cols-4' : 'grid-cols-3')}>
                {tabs.map((tab) => {
                    const on = isOn(tab.active);
                    return (
                        <AppLink key={tab.route} to={tab.route} params={tab.params} aria-current={on ? 'page' : undefined} className={cn('relative flex flex-col items-center gap-1 py-2.5', on ? 'text-brand' : 'text-gray-400')}>
                            {on && <span className="absolute inset-x-6 top-0 h-0.5 rounded-full bg-brand" />}
                            <span className="relative">
                                <tab.icon className="size-5" />
                                {!!tab.badge && (
                                    <span className="absolute -right-3 -top-2 h-4 min-w-4 rounded-full bg-action px-1 text-center text-[9px] font-black leading-4 text-white">{tab.badge > 99 ? '99+' : tab.badge}</span>
                                )}
                            </span>
                            <span className="text-[9px] font-black uppercase tracking-wider">{tab.short}</span>
                        </AppLink>
                    );
                })}
            </div>
        </nav>
    );


    return (
        <SiteLayout bottomBar={bottomBar}>
            <div className="mx-auto max-w-5xl px-4 py-8">
                {organizer.actingAs && (
                    <div className="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p className="text-[11px] font-bold text-amber-800">
                            <ShieldUser className="mr-1 inline size-4" />
                            Super admin view of <strong>{organizer.organization.name}</strong>. Changes you make here are made on their behalf.
                        </p>
                        <button type="button" onClick={() => router.post(route('organizer.act-as.stop'))} className="rounded-full bg-amber-600 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-amber-700">
                            Back to admin
                        </button>
                    </div>
                )}

                <div className="mb-8">
                    <p className="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300">Ventiq · {organizer.organization.name}</p>

                    <nav className="mb-6 mt-3 hidden flex-wrap items-center gap-2 border-b border-gray-100 pb-5 sm:flex">
                        {tabs.map((tab) => (
                            <AppLink
                                key={tab.route}
                                to={tab.route}
                                params={tab.params}
                                prefetch
                                className={cn(
                                    'rounded-full border px-4 py-2 text-[10px] font-black uppercase tracking-widest transition-all',
                                    isOn(tab.active) ? 'border-brand bg-brand text-white' : 'border-gray-100 bg-white text-gray-500 hover:text-brand',
                                )}
                            >
                                <tab.icon className="mr-1 inline size-3.5" />{tab.label}
                                {!!tab.badge && <span className="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-action px-1.5 text-[10px] tracking-normal text-white">{tab.badge}</span>}
                            </AppLink>
                        ))}
                    </nav>

                    {crumbs && (
                        <nav aria-label="Breadcrumb" className="mb-2 mt-4 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] font-bold text-gray-400 sm:mt-0">
                            {crumbs.map(([label, url], i) => (
                                <span key={i} className="flex items-center gap-1.5">
                                    {i > 0 && <ChevronRight className="size-3 text-gray-300" aria-hidden />}
                                    {url ? (
                                        <a href={url} className="max-w-[14rem] truncate hover:text-brand">{i === 0 && <ArrowLeft className="mr-1 inline size-3" />}{label}</a>
                                    ) : (
                                        <span className="max-w-[14rem] truncate text-gray-500" aria-current="page">{label}</span>
                                    )}
                                </span>
                            ))}
                        </nav>
                    )}

                    <h1 className={cn('text-2xl font-black tracking-tight text-brand', !crumbs && 'mt-4 sm:mt-0')}>{title}</h1>


                    {subtitle && <div className="mt-1 text-[13px] font-medium text-gray-500">{subtitle}</div>}

                    {draftEventId && (
                        <div className="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <p className="text-[12px] font-bold text-amber-800"><EyeOff className="mr-1 inline size-4" />Draft: attendees can't see this event or register yet.</p>
                            {organizer.can.edit_event && <PublishButton eventId={draftEventId} />}
                        </div>
                    )}

                    {flash.status && <Alert className="mt-6 rounded-2xl p-4 text-[12px]">{flash.status}</Alert>}
                    {flash.error && <Alert variant="destructive" className="mt-6 rounded-2xl p-4 text-[12px]">{flash.error}</Alert>}
                </div>

                {children}
            </div>
        </SiteLayout>
    );
}

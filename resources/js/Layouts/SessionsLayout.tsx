import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, FileText, FolderOpen, LayoutGrid, ListTodo, Ticket } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { type AreaTab, useAreaTabs } from '@/components/area-tabs';
import { Alert } from '@/components/ui/alert';
import { cn } from '@/lib/utils';
import type { SessionsArea } from '@/types';

const AREA = 'sessions';

function useTabs() {
    const { sessionsArea } = usePage<{ sessionsArea: SessionsArea }>().props;
    const can = sessionsArea?.can;
    // Only the tabs this person's role opens (App\Support\SessionsAccess).
    const tabs: AreaTab[] = [
        { route: 'sessions.index', label: 'Desk', icon: LayoutGrid, active: ['sessions.index', 'sessions.checkin', 'sessions.series.*'] },
        ...(can?.view ? [
            { route: 'sessions.reports', label: 'Reports', icon: FileText, active: ['sessions.reports', 'sessions.report'], badge: sessionsArea?.needsReview },
            { route: 'sessions.actions.index', label: 'Actions', icon: ListTodo, active: ['sessions.actions.*'], badge: sessionsArea?.overdueActions },
            { route: 'programmes.index', label: 'Programmes', icon: FolderOpen, active: ['programmes.*'] },
        ] : []),
        ...(can?.plan ? [{ route: 'sessions.plan', label: 'Plan', icon: Ticket, active: ['sessions.plan', 'organization.session-plan.*'] }] : []),
    ];
    return { tabs, ...useAreaTabs(AREA, tabs, true) };
}

/**
 * Around every Sessions page but the live console and the pass, and kept
 * mounted while moving between them (see app.tsx), so the header and tabs
 * stay put. On phones the tabs are an icon bar at the bottom.
 */
export function SessionsShell({ children }: { children: React.ReactNode }) {
    const { sessionsArea } = usePage<{ sessionsArea: SessionsArea }>().props;
    const { tabs, isOn, href } = useTabs();

    const bottomBar = (
        <nav className="border-t border-gray-100 bg-white pb-[env(safe-area-inset-bottom)]">
            <div className="grid" style={{ gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))` }}>
                {tabs.map((tab) => {
                    const on = isOn(tab);
                    return (
                        <Link key={tab.route} href={href(tab)} aria-current={on ? 'page' : undefined} className={cn('relative flex flex-col items-center gap-1 py-2.5', on ? 'text-brand' : 'text-gray-400')}>
                            {on && <span className="absolute inset-x-6 top-0 h-0.5 rounded-full bg-brand" />}
                            <span className="relative">
                                <tab.icon className="size-5" />
                                {!!tab.badge && <span className="absolute -right-3 -top-2 h-4 min-w-4 rounded-full bg-action px-1 text-center text-[9px] font-black leading-4 text-white">{tab.badge > 99 ? '99+' : tab.badge}</span>}
                            </span>
                            <span className="text-[9px] font-black uppercase tracking-wider">{tab.label}</span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );

    return (
        <SiteLayout bottomBar={bottomBar}>
            <div className="mx-auto max-w-6xl px-4 py-8">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300">Ventiq Sessions · {sessionsArea?.organization}</p>
                <nav className="mb-6 mt-3 hidden flex-wrap items-center gap-2 border-b border-gray-100 pb-5 sm:flex">
                    {tabs.map((tab) => (
                        <Link
                            key={tab.route}
                            href={href(tab)}
                            prefetch
                            aria-current={isOn(tab) ? 'page' : undefined}
                            className={cn('rounded-full border px-4 py-2 text-[10px] font-black uppercase tracking-widest transition-colors', isOn(tab) ? 'border-brand bg-brand text-white' : 'border-gray-100 bg-white text-gray-500 hover:text-brand')}
                        >
                            <tab.icon className="mr-1 inline size-3.5" />{tab.label}
                            {!!tab.badge && <span className="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-action px-1.5 text-[10px] tracking-normal text-white">{tab.badge}</span>}
                        </Link>
                    ))}
                </nav>
                {children}
            </div>
        </SiteLayout>
    );
}

/** A Sessions page's own part: where it sits, its title and actions, and any message. */
export default function SessionsLayout({
    title,
    subtitle,
    crumbs,
    actions,
    width = 'max-w-6xl',
    children,
}: {
    title: string;
    subtitle?: React.ReactNode;
    /** [[label, url], ..., [label]]: the last is this page. */
    crumbs?: Array<[string, string?]>;
    actions?: React.ReactNode;
    width?: string;
    children: React.ReactNode;
}) {
    const { flash, errors } = usePage().props;
    const firstError = Object.values(errors ?? {})[0];

    return (
        <div className={cn('mx-auto w-full', width)}>
            {crumbs && (
                <nav aria-label="Breadcrumb" className="mb-2 mt-4 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] font-bold text-gray-400 sm:mt-0">
                    {crumbs.map(([label, url], i) => (
                        <span key={i} className="flex items-center gap-1.5">
                            {i > 0 && <ChevronRight className="size-3 text-gray-300" aria-hidden />}
                            {url ? (
                                <Link href={url} className="max-w-[14rem] truncate hover:text-brand">{i === 0 && <ArrowLeft className="mr-1 inline size-3" />}{label}</Link>
                            ) : (
                                <span className="max-w-[14rem] truncate text-gray-500" aria-current="page">{label}</span>
                            )}
                        </span>
                    ))}
                </nav>
            )}

            <div className={cn('mb-8 flex flex-wrap items-end justify-between gap-4', !crumbs && 'mt-4 sm:mt-0')}>
                <div className="min-w-0">
                    <h1 className="text-2xl font-black tracking-tight text-brand">{title}</h1>
                    {subtitle && <div className="mt-1 text-[13px] font-medium text-gray-500">{subtitle}</div>}
                </div>
                {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
            </div>

            {flash.status && <Alert className="mb-6 rounded-2xl p-4 text-[12px]">{flash.status}</Alert>}
            {(flash.error || firstError) && <Alert variant="destructive" className="mb-6 rounded-2xl p-4 text-[12px]">{flash.error || firstError}</Alert>}

            {children}
        </div>
    );
}

export { AREA as SESSIONS_AREA };

/** The area's main button style, and its quiet one. */
export const primaryButton = 'inline-flex items-center justify-center gap-2 rounded-2xl bg-action px-5 py-3 text-[11px] font-black uppercase tracking-widest text-white shadow-md transition-all hover:-translate-y-0.5 hover:shadow-lg disabled:opacity-50';
export const quietButton = 'inline-flex items-center justify-center gap-1.5 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-[10px] font-black uppercase tracking-widest text-brand transition-all hover:border-brand disabled:opacity-50';
export const field = 'w-full rounded-2xl border border-gray-200 bg-white px-5 py-4 font-bold text-brand outline-none transition-colors placeholder:font-semibold focus:border-action focus:ring-2 focus:ring-action/20';
export const fieldLabel = 'mb-1 ml-1 block text-[10px] font-black uppercase tracking-wider text-brand';

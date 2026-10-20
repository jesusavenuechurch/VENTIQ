import { Link, usePage } from '@inertiajs/react';
import { ArrowLeftRight, Banknote, BriefcaseBusiness, Building2, CalendarDays, Contact, Inbox, LayoutTemplate, type LucideIcon, Smartphone, Tags, Ticket, UsersRound, Video } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { useActiveInView, useAreaTabs } from '@/components/area-tabs';
import { Alert } from '@/components/ui/alert';
import { cn } from '@/lib/utils';

interface Section {
    route: string;
    label: string;
    icon: LucideIcon;
    /** route().current() patterns that light this tab. */
    active: string[];
}

/** VENTIQ's own admin sections. */
const SECTIONS: Section[] = [
    { route: 'ventiq.money.index', label: 'Money', icon: Banknote, active: ['ventiq.money.*'] },
    { route: 'ventiq.payouts.index', label: 'Payouts', icon: ArrowLeftRight, active: ['ventiq.payouts.*'] },
    { route: 'ventiq.organizations.index', label: 'Organizations', icon: Building2, active: ['ventiq.organizations.*'] },
    { route: 'ventiq.events.index', label: 'Events', icon: CalendarDays, active: ['ventiq.events.*'] },
    { route: 'ventiq.tickets.index', label: 'Tickets', icon: Ticket, active: ['ventiq.tickets.*'] },
    { route: 'ventiq.users.index', label: 'Users', icon: UsersRound, active: ['ventiq.users.*'] },
    { route: 'ventiq.agents.index', label: 'Agents', icon: BriefcaseBusiness, active: ['ventiq.agents.*'] },
    { route: 'ventiq.inbox.index', label: 'Inbox', icon: Inbox, active: ['ventiq.inbox.*'] },
    { route: 'ventiq.session-plans.index', label: 'Session plans', icon: Video, active: ['ventiq.session-plans.*'] },
    { route: 'ventiq.tiers.index', label: 'Ticket types', icon: Tags, active: ['ventiq.tiers.*'] },
    { route: 'ventiq.clients.index', label: 'Clients', icon: Contact, active: ['ventiq.clients.*'] },
    { route: 'ventiq.tier-templates.index', label: 'Tier templates', icon: LayoutTemplate, active: ['ventiq.tier-templates.*'] },
    { route: 'ventiq.app-releases.index', label: 'Scanner app', icon: Smartphone, active: ['ventiq.app-releases.*'] },
];

/**
 * Around every VENTIQ admin page (super admins), kept mounted while moving
 * between them (see app.tsx): its sections as a row of tabs that scrolls
 * sideways, with the one you're on kept in view.
 */
export function AdminShell({ children }: { children: React.ReactNode }) {
    const { isOn } = useAreaTabs('admin', SECTIONS);
    const row = useActiveInView<HTMLElement>();

    return (
        <SiteLayout>
            <div className="mx-auto max-w-6xl px-4 py-8">
                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300">Ventiq · Admin</p>

                <nav ref={row} aria-label="Admin" className="-mx-4 mb-6 mt-3 flex items-center gap-2 overflow-x-auto scroll-px-4 border-b border-gray-100 px-4 pb-5 [scrollbar-width:none]">
                    {SECTIONS.map((s) => (
                        <Link
                            key={s.route}
                            href={route(s.route)}
                            prefetch
                            aria-current={isOn(s) ? 'page' : undefined}
                            className={cn(
                                'shrink-0 rounded-full border px-4 py-2 text-[10px] font-black uppercase tracking-widest transition-colors',
                                isOn(s) ? 'border-brand bg-brand text-white' : 'border-gray-100 bg-white text-gray-500 hover:text-brand',
                            )}
                        >
                            <s.icon className="mr-1 inline size-3.5" />{s.label}
                        </Link>
                    ))}
                </nav>

                {children}
            </div>
        </SiteLayout>
    );
}

/** An admin page's own part: its title, actions and messages. */
export default function AdminLayout({ title, subtitle, actions, children }: { title: string; subtitle?: React.ReactNode; actions?: React.ReactNode; children: React.ReactNode }) {
    const { flash } = usePage().props;

    return (
        <>
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-black tracking-tight text-brand">{title}</h1>
                    {subtitle && <div className="mt-1 text-[13px] font-medium text-gray-500">{subtitle}</div>}
                </div>
                {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
            </div>

            {flash.status && <Alert className="mb-6 rounded-2xl p-4 text-[12px]">{flash.status}</Alert>}
            {flash.error && <Alert variant="destructive" className="mb-6 rounded-2xl p-4 text-[12px]">{flash.error}</Alert>}

            {children}
        </>
    );
}

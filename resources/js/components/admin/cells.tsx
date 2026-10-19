import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

const TONES: Record<string, string> = {
    published: 'bg-mint text-mint-ink', active: 'bg-mint text-mint-ink', completed: 'bg-mint text-mint-ink', settled: 'bg-mint text-mint-ink',
    draft: 'bg-action-soft text-action-ink', pending: 'bg-action-soft text-action-ink', partial: 'bg-action-soft text-action-ink', new: 'bg-action-soft text-action-ink',
    ongoing: 'bg-lilac text-lilac-ink', checked_in: 'bg-slate-100 text-slate-600', read: 'bg-slate-100 text-slate-600',
    cancelled: 'bg-rose-50 text-rose-600', expired: 'bg-rose-50 text-rose-600', failed: 'bg-rose-50 text-rose-600', void: 'bg-rose-50 text-rose-600', refunded: 'bg-rose-50 text-rose-600',
};

/** A status word in its colour (mint good, orange waiting, rose stopped). */
export function Pill({ value, label, className }: { value: string | null | undefined; label?: string; className?: string }) {
    if (!value) return <span className="text-gray-300">—</span>;
    return (
        <span className={cn('whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-widest', TONES[value] ?? 'bg-slate-100 text-slate-500', className)}>
            {label ?? value.replace(/_/g, ' ')}
        </span>
    );
}

/** An organization's name, linking to it in the admin. */
export function OrgLink({ id, name }: { id: number | null; name: string | null | undefined }) {
    if (!id) return <span className="text-gray-300">—</span>;
    return <Link href={route('ventiq.organizations.edit', id)} className="font-bold text-gray-600 hover:text-brand">{name}</Link>;
}

/** Into an organization's organizer view, optionally straight to one of its events. */
export function organizerView(organizationId: number, event?: { id: number; to?: 'edit' | 'attendees'; q?: string }): string {
    return route('organizer.act-as.start', { organization: organizationId, ...(event ? { event: event.id, to: event.to, q: event.q } : {}) });
}

export const smallButton = 'inline-flex items-center gap-1 whitespace-nowrap rounded-full border border-gray-200 bg-white px-3 py-1 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-brand';

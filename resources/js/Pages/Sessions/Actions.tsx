import { Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import SessionsLayout from '@/Layouts/SessionsLayout';
import { type ActionPointCard, ActionPointRow, type Owner } from '@/components/sessions/action-points';
import { Pagination, type Paginated } from '@/components/pagination';
import { cn } from '@/lib/utils';
import type { SessionsArea } from '@/types';

type Show = 'open' | 'overdue' | 'mine' | 'proposed' | 'done';

interface Props {
    show: Show;
    points: Paginated<ActionPointCard>;
    counts: Record<'open' | 'overdue' | 'mine' | 'proposed', number>;
    owners: Owner[];
}

/** Everything the organization agreed to do, from every session: what's open, overdue, yours, waiting to be confirmed, done. */
export default function Actions({ show, points, counts, owners }: Props) {
    const { can } = usePage<{ sessionsArea: SessionsArea }>().props.sessionsArea;
    const tabs: [Show, string, number | null][] = [
        ['open', 'Open', counts.open],
        ['overdue', 'Overdue', counts.overdue],
        ['mine', 'Mine', counts.mine],
        ['proposed', 'To confirm', counts.proposed],
        ['done', 'Done', null],
    ];

    return (
        <SessionsLayout title="Action points" subtitle="What was agreed in your sessions, and who's doing it." width="max-w-4xl">
            <div className="mb-6 flex w-full gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 sm:w-fit">
                {tabs.map(([key, label, n]) => (
                    <Link key={key} href={route('sessions.actions.index', key === 'open' ? {} : { show: key })} preserveScroll className={cn('flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl px-4 py-2.5 text-[11px] font-black uppercase tracking-widest transition-colors', show === key ? 'bg-white text-brand shadow-sm' : 'text-gray-500 hover:text-brand')}>
                        {label}
                        {!!n && <span className={cn('rounded-full px-1.5 text-[10px]', key === 'overdue' ? 'bg-rose-100 text-rose-700' : key === 'proposed' ? 'bg-lilac text-lilac-ink' : 'bg-gray-200 text-gray-600')}>{n}</span>}
                    </Link>
                ))}
            </div>

            {points.data.length > 0 ? (
                <ul className="divide-y divide-gray-50 rounded-2xl border border-gray-100 bg-white">
                    {points.data.map((p) => <ActionPointRow key={p.id} point={p} owners={owners} canEdit={can.run} showSession />)}
                </ul>
            ) : (
                <p className="rounded-2xl border border-dashed border-gray-200 py-14 text-center text-[12px] italic text-gray-400">
                    {{ open: "Nothing open. When a session ends, its action points come here.", overdue: 'Nothing overdue.', mine: 'Nothing on your name.', proposed: 'No suggestions waiting.', done: 'Nothing done yet.' }[show]}
                </p>
            )}
            <div className="mt-6"><Pagination page={points} /></div>
        </SessionsLayout>
    );
}

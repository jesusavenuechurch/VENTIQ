import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import SessionsLayout from '@/Layouts/SessionsLayout';
import { Pagination, type Paginated } from '@/components/pagination';
import { cn } from '@/lib/utils';
import type { SessionsArea } from '@/types';

interface Report {
    id: number;
    title: string;
    date: string | null;
    reviewed: string | null;
}

/** Every session report, the ones to review first. */
export default function Reports({ tab, reports }: { tab: 'needs_review' | 'reviewed'; reports: Paginated<Report> }) {
    const { sessionsArea } = usePage<{ sessionsArea: SessionsArea }>().props;

    return (
        <SessionsLayout title="Reports" subtitle="Every report, whether or not you've opened it yet." width="max-w-4xl">
            <div className="mb-6 flex w-fit gap-1 rounded-2xl bg-slate-100 p-1">
                {([['needs_review', `To review${sessionsArea.needsReview ? ` (${sessionsArea.needsReview})` : ''}`], ['reviewed', 'Reviewed']] as const).map(([key, label]) => (
                    <Link key={key} href={route('sessions.reports', { tab: key })} preserveScroll className={cn('rounded-xl px-4 py-2.5 text-[11px] font-black uppercase tracking-widest transition-colors', tab === key ? 'bg-white text-brand shadow-sm' : 'text-gray-500 hover:text-brand')}>
                        {label}
                    </Link>
                ))}
            </div>

            <div className="space-y-3">
                {reports.data.map((r) => (
                    <Link key={r.id} href={route('sessions.report', r.id)} className="flex items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white px-5 py-4 transition-all hover:border-brand/30 hover:shadow-sm">
                        <div className="min-w-0">
                            <p className="truncate text-[13px] font-bold text-brand">{r.title}</p>
                            <p className="mt-0.5 text-[11px] font-bold text-gray-400">{r.date}</p>
                        </div>
                        <span className="flex shrink-0 items-center gap-2">
                            {r.reviewed
                                ? <span className="text-[10px] font-black uppercase tracking-widest text-emerald-600">Reviewed {r.reviewed}</span>
                                : <span className="text-[10px] font-black uppercase tracking-widest text-action">To review</span>}
                            <ChevronRight className="size-4 text-gray-300" />
                        </span>
                    </Link>
                ))}
                {reports.data.length === 0 && (
                    <p className="py-16 text-center text-[12px] italic text-gray-400">{tab === 'reviewed' ? 'Nothing reviewed yet.' : "Nothing waiting for review. You're caught up."}</p>
                )}
            </div>
            <div className="mt-6"><Pagination page={reports} /></div>
        </SessionsLayout>
    );
}

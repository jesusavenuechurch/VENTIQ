import { Link, router, usePage } from '@inertiajs/react';
import { Award, Download, ExternalLink, FileText, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ScheduleSessionDialog, type SessionType } from '@/components/sessions/schedule-dialog';
import SessionsLayout, { primaryButton, quietButton } from '@/Layouts/SessionsLayout';
import { cn } from '@/lib/utils';
import type { SessionsArea } from '@/types';

interface Props {
    programme: { id: number; name: string; dates: string | null; venue: string | null; certificates: boolean };
    sessions: { id: number; title: string; when: string; status: string }[];
    certificates: { id: number; name: string; issued: string | null; url: string }[];
    eligible: number;
    types: SessionType[];
    today: string;
    /** Opened from /sessions/create (old links). */
    openSchedule: boolean;
}

const STATUS: Record<string, [string, string]> = {
    draft: ['Scheduled', 'bg-gray-50 text-gray-400'],
    active: ['Live', 'bg-amber-50 text-amber-600'],
    completed: ['Finished', 'bg-blue-50 text-blue-500'],
    reported: ['Reported', 'bg-emerald-50 text-emerald-600'],
};

/** One programme: its sessions, its certificates and its report. */
export default function Show({ programme, sessions, certificates, eligible, types, today, openSchedule }: Props) {
    const [issuing, setIssuing] = useState(false);
    const [scheduling, setScheduling] = useState(false);
    useEffect(() => {
        if (openSchedule) setScheduling(true);
    }, [openSchedule]);
    const { can } = usePage<{ sessionsArea: SessionsArea }>().props.sessionsArea;
    const sessionHref = (s: { id: number; status: string }) => (s.status === 'reported' ? route('sessions.report', s.id) : can.run ? route('sessions.show', s.id) : null);

    return (
        <SessionsLayout
            title={programme.name}
            subtitle={[programme.dates, programme.venue].filter(Boolean).join(' · ')}
            crumbs={[['Programmes', route('programmes.index')], [programme.name]]}
            width="max-w-4xl"
            actions={
                <>
                    {can.run && <button type="button" onClick={() => setScheduling(true)} className={primaryButton}><Plus className="size-4" />Add a session</button>}
                    <Link href={route('programmes.report', programme.id)} className={quietButton}><FileText className="size-3.5" />Programme report</Link>
                    {can.checkin && <a href={route('programmes.participants.pdf', programme.id)} className={quietButton}><Download className="size-3.5" />Participant list</a>}
                </>
            }
        >
            {programme.certificates && (
                <div className="mb-8 rounded-2xl border border-gray-100 bg-white p-5">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest text-brand"><Award className="size-4 text-action" />Certificates</p>
                            <p className="mt-1 text-[12px] font-medium text-gray-500">{certificates.length} issued · {eligible} eligible (checked in at least once)</p>
                        </div>
                        {can.run && <button
                            type="button"
                            disabled={issuing}
                            onClick={() => router.post(route('programmes.certificates.issue', programme.id), {}, { preserveScroll: true, onStart: () => setIssuing(true), onFinish: () => setIssuing(false) })}
                            className={cn(quietButton, 'border-brand bg-brand text-white hover:bg-action')}
                        >
                            {issuing ? 'Issuing…' : 'Issue certificates'}
                        </button>}
                    </div>
                    {certificates.length > 0 && (
                        <div className="mt-4 divide-y divide-gray-50 border-t border-gray-100">
                            {certificates.map((c) => (
                                <a key={c.id} href={c.url} target="_blank" rel="noreferrer" className="flex items-center justify-between gap-3 py-3 hover:text-action">
                                    <span>
                                        <span className="block text-[13px] font-bold text-brand">{c.name}</span>
                                        <span className="block text-[11px] text-gray-400">Issued {c.issued}</span>
                                    </span>
                                    <ExternalLink className="size-3.5 text-gray-300" />
                                </a>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <p className="mb-3 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Sessions</p>
            <div className="space-y-3">
                {sessions.map((s) => {
                    const [label, tone] = STATUS[s.status] ?? [s.status, 'bg-gray-50 text-gray-400'];
                    const href = sessionHref(s);
                    const Row = href ? Link : 'div';
                    return (
                        <Row key={s.id} href={href ?? ''} className="flex items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white px-5 py-4 transition-all hover:border-brand/30 hover:shadow-sm">
                            <div className="min-w-0">
                                <p className="truncate text-[13px] font-bold text-brand">{s.title}</p>
                                <p className="mt-0.5 text-[11px] font-bold text-gray-400">{s.when}</p>
                            </div>
                            <span className={cn('shrink-0 rounded-full px-3 py-1.5 text-[9px] font-black uppercase tracking-widest', tone)}>{label}</span>
                        </Row>
                    );
                })}
                {sessions.length === 0 && <p className="py-12 text-center text-[12px] italic text-gray-400">No sessions yet. Add the first one above.</p>}
            </div>
            {can.run && <ScheduleSessionDialog open={scheduling} onOpenChange={setScheduling} types={types} programme={{ id: programme.id, name: programme.name }} today={today} />}
        </SessionsLayout>
    );
}

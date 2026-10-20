import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Award, Check, Copy, Download, ExternalLink, FileText, Link2, Plus, Search, UserPlus, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { ScheduleSessionDialog, type SessionType } from '@/components/sessions/schedule-dialog';
import SessionsLayout, { field, fieldLabel, primaryButton, quietButton } from '@/Layouts/SessionsLayout';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { SessionsArea } from '@/types';

type DayMark = 'came' | 'missed' | 'ahead';
type Standing = 'certified' | 'qualified' | 'on_track' | 'short' | 'enrolled' | 'withdrawn';

interface Person {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    organization: string | null;
    position: string | null;
    source: string;
    days: DayMark[];
    attended: number;
    status: Standing;
    certificate: { issued: string; number: string; url: string } | null;
}

interface Props {
    programme: { id: number; name: string; dates: string | null; venue: string | null; certificates: boolean; minDays: number | null; enrolOpen: boolean; enrolUrl: string };
    sessions: { id: number; title: string; when: string; status: string }[];
    board: {
        days: { id: number; label: string; date: string | null; ahead: boolean }[];
        required: number | null;
        people: Person[];
        counts: Partial<Record<Standing, number>>;
    };
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

const STANDING: Record<Standing, [string, string]> = {
    certified: ['Certified', 'bg-mint text-mint-ink'],
    qualified: ['Meets the rule', 'bg-lilac text-lilac-ink'],
    on_track: ['On track', 'bg-blue-50 text-blue-600'],
    short: ['Missed too many', 'bg-amber-50 text-amber-700'],
    enrolled: ['Enrolled', 'bg-gray-100 text-gray-500'],
    withdrawn: ['Withdrawn', 'bg-gray-50 text-gray-400'],
};

const card = 'rounded-2xl border border-gray-100 bg-white p-5';
const cardTitle = 'flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest text-brand';
const opts = { preserveScroll: true };

/** One programme: who's enrolled and how they're doing, its certificates, its sessions and its report. */
export default function Show({ programme, sessions, board, types, today, openSchedule }: Props) {
    const [scheduling, setScheduling] = useState(false);
    const [adding, setAdding] = useState(false);
    const [query, setQuery] = useState('');
    useEffect(() => {
        if (openSchedule) setScheduling(true);
    }, [openSchedule]);
    const { can } = usePage<{ sessionsArea: SessionsArea }>().props.sessionsArea;
    const sessionHref = (s: { id: number; status: string }) => (s.status === 'reported' ? route('sessions.report', s.id) : can.run ? route('sessions.show', s.id) : null);
    const enrolled = board.people.filter((p) => p.status !== 'withdrawn').length;
    const people = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? board.people.filter((p) => [p.name, p.organization, p.email, p.phone].some((v) => v?.toLowerCase().includes(q))) : board.people;
    }, [board.people, query]);

    return (
        <SessionsLayout
            title={programme.name}
            subtitle={[programme.dates, programme.venue].filter(Boolean).join(' · ')}
            crumbs={[['Programmes', route('programmes.index')], [programme.name]]}
            width="max-w-5xl"
            actions={
                <>
                    {can.run && <button type="button" onClick={() => setScheduling(true)} className={primaryButton}><Plus className="size-4" />Add a session</button>}
                    <Link href={route('programmes.report', programme.id)} className={quietButton}><FileText className="size-3.5" />Programme report</Link>
                    {can.checkin && <a href={route('programmes.participants.pdf', programme.id)} className={quietButton}><Download className="size-3.5" />Participant list</a>}
                </>
            }
        >
            <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2">
                <Enrolment programme={programme} enrolled={enrolled} canEdit={can.run} onAdd={() => setAdding(true)} />
                <Certificates programme={programme} board={board} canEdit={can.run} />
            </div>

            <div className="mb-10">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">People · {enrolled}</p>
                    {board.people.length > 6 && (
                        <label className="relative">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-gray-300" />
                            <input aria-label="Find someone" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Find someone" className="w-56 rounded-full border border-gray-200 bg-white py-2 pl-8 pr-4 text-[12px] font-medium outline-none focus:border-action" />
                        </label>
                    )}
                </div>
                {board.people.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-gray-200 py-10 text-center">
                        <p className="text-[13px] font-bold text-gray-500">No one's enrolled yet</p>
                        <p className="mx-auto mt-1 max-w-sm text-[12px] text-gray-400">Share the enrolment link, add people yourself, or they're added when they check in to a day.</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-gray-50 rounded-2xl border border-gray-100 bg-white">
                        {people.map((p) => <PersonRow key={p.id} person={p} days={board.days} programmeId={programme.id} canEdit={can.run} />)}
                        {people.length === 0 && <li className="px-5 py-6 text-[12px] italic text-gray-400">No one matches "{query}".</li>}
                    </ul>
                )}
            </div>

            <p className="mb-3 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Sessions</p>
            <div className="space-y-3">
                {sessions.map((s, i) => {
                    const [label, tone] = STATUS[s.status] ?? [s.status, 'bg-gray-50 text-gray-400'];
                    const href = sessionHref(s);
                    const Row = href ? Link : 'div';
                    return (
                        <Row key={s.id} href={href ?? ''} className="flex items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white px-5 py-4 transition-all hover:border-brand/30 hover:shadow-sm">
                            <div className="min-w-0">
                                <p className="truncate text-[13px] font-bold text-brand"><span className="text-gray-300">Day {i + 1} · </span>{s.title}</p>
                                <p className="mt-0.5 text-[11px] font-bold text-gray-400">{s.when}</p>
                            </div>
                            <span className={cn('shrink-0 rounded-full px-3 py-1.5 text-[9px] font-black uppercase tracking-widest', tone)}>{label}</span>
                        </Row>
                    );
                })}
                {sessions.length === 0 && <p className="py-12 text-center text-[12px] italic text-gray-400">No sessions yet. Add the first one above: each is a day of the programme.</p>}
            </div>
            {can.run && <ScheduleSessionDialog open={scheduling} onOpenChange={setScheduling} types={types} programme={{ id: programme.id, name: programme.name }} today={today} />}
            {adding && <AddPeople programmeId={programme.id} onClose={() => setAdding(false)} />}
        </SessionsLayout>
    );
}

function Enrolment({ programme, enrolled, canEdit, onAdd }: { programme: Props['programme']; enrolled: number; canEdit: boolean; onAdd: () => void }) {
    const [copied, setCopied] = useState(false);
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(programme.enrolUrl);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Copy the enrolment link', programme.enrolUrl);
        }
    };

    return (
        <div className={card}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className={cardTitle}><UserPlus className="size-4 text-action" />Enrolment</p>
                    <p className="mt-1 text-[12px] font-medium text-gray-500">{enrolled === 1 ? '1 person' : `${enrolled} people`} on the programme</p>
                </div>
                {canEdit && <button type="button" onClick={onAdd} className={cn(quietButton, 'px-3 py-2')}><Plus className="size-3.5" />Add people</button>}
            </div>
            <div className={cn('mt-4 flex items-center gap-2 rounded-xl border px-3 py-2', programme.enrolOpen ? 'border-gray-100 bg-gray-50' : 'border-amber-100 bg-amber-50')}>
                <Link2 className="size-3.5 shrink-0 text-gray-400" />
                <span className="min-w-0 flex-1 truncate font-mono text-[11px] text-gray-500">{programme.enrolUrl}</span>
                <button type="button" onClick={copy} className={cn('flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white', copied ? 'bg-emerald-500' : 'bg-brand hover:bg-action')}>
                    {copied ? <Check className="size-3" /> : <Copy className="size-3" />}{copied ? 'Copied' : 'Copy'}
                </button>
            </div>
            <p className="mt-2 text-[11px] text-gray-400">{programme.enrolOpen ? 'Anyone with the link can enrol. People who check in to a day are added too.' : "The link is closed: it shows the programme but doesn't take new people."}</p>
            {canEdit && (
                <button type="button" onClick={() => router.patch(route('programmes.settings', programme.id), { enrolment_open: !programme.enrolOpen }, opts)} className="mt-2 text-[11px] font-bold text-brand underline">
                    {programme.enrolOpen ? 'Close enrolment' : 'Open enrolment'}
                </button>
            )}
        </div>
    );
}

function Certificates({ programme, board, canEdit }: { programme: Props['programme']; board: Props['board']; canEdit: boolean }) {
    const total = board.days.length;
    const [issuing, setIssuing] = useState(false);
    const c = board.counts;
    const ready = c.qualified ?? 0;
    // A change that would issue certificates straight away is confirmed first: they're emailed.
    const [pending, setPending] = useState<{ change: Record<string, number | boolean | null>; issues: number } | null>(null);
    const wouldIssue = (needed: number) => board.people.filter((p) => !['certified', 'withdrawn'].includes(p.status) && p.attended >= needed).length;
    const save = (change: Record<string, number | boolean | null>, needed: number | null) => {
        const issues = needed ? wouldIssue(needed) : 0;
        issues > 0 ? setPending({ change, issues }) : router.patch(route('programmes.settings', programme.id), change, opts);
    };
    const rule = (value: string) => save({ certificate_min_days: value === 'all' ? null : Number(value) }, value === 'all' ? total : Number(value));

    return (
        <div className={card}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className={cardTitle}><Award className="size-4 text-action" />Certificates</p>
                    <p className="mt-1 text-[12px] font-medium text-gray-500">{programme.certificates ? 'Issued and emailed on their own, the evening someone meets the rule.' : 'Off for this programme.'}</p>
                    {canEdit && <Link href={route('sessions.certificates.design', { programme: programme.id })} className="mt-1 inline-block text-[11px] font-bold text-brand underline">Design and signatures</Link>}
                </div>
                {canEdit && (
                    <label className="flex shrink-0 cursor-pointer items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-500">
                        <input type="checkbox" checked={programme.certificates} onChange={(e) => save({ certificates_enabled: e.target.checked }, e.target.checked ? board.required : null)} className="size-4 accent-brand" />
                        On
                    </label>
                )}
            </div>
            {programme.certificates && (
                <>
                    <div className="mt-4">
                        <label htmlFor="rule" className={cn(fieldLabel, 'text-mint-ink')}>Who gets one</label>
                        {total === 0 ? (
                            <p className="ml-1 text-[12px] text-gray-400">Add the programme's days first.</p>
                        ) : (
                            <NativeSelect id="rule" disabled={!canEdit} value={programme.minDays && programme.minDays < total ? String(programme.minDays) : 'all'} onChange={(e) => rule(e.target.value)} className="border-mint-ink/25 bg-mint py-3 font-black text-mint-ink focus:border-mint-ink focus:bg-mint">
                                <option value="all">Everyone who attends every day{total > 1 ? ` (all ${total})` : ''}</option>
                                {Array.from({ length: Math.max(0, total - 1) }, (_, i) => total - 1 - i).map((n) => <option key={n} value={n}>At least {n} of the {total} days</option>)}
                            </NativeSelect>
                        )}
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2">
                        {(['certified', 'qualified', 'on_track', 'short'] as Standing[]).filter((s) => c[s]).map((s) => (
                            <span key={s} className={cn('rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest', STANDING[s][1])}>{c[s]} {STANDING[s][0]}</span>
                        ))}
                    </div>
                    {canEdit && ready > 0 && (
                        <button type="button" disabled={issuing} onClick={() => router.post(route('programmes.certificates.issue', programme.id), {}, { ...opts, onStart: () => setIssuing(true), onFinish: () => setIssuing(false) })} className={cn(quietButton, 'mt-4 border-brand bg-brand text-white hover:bg-action')}>
                            {issuing ? 'Issuing…' : `Issue ${ready} now`}
                        </button>
                    )}
                </>
            )}
            <ConfirmDialog
                open={!!pending}
                onOpenChange={(o) => !o && setPending(null)}
                destructive={false}
                title={`Issue ${pending?.issues} ${pending?.issues === 1 ? 'certificate' : 'certificates'} now?`}
                confirmLabel="Save and issue"
                onConfirm={() => pending && router.patch(route('programmes.settings', programme.id), pending.change, { ...opts, onFinish: () => setPending(null) })}
            >
                {pending?.issues === 1 ? 'One person meets' : `${pending?.issues} people meet`} the rule with this change. Their certificates are issued and emailed as soon as you save.
            </ConfirmDialog>
        </div>
    );
}

function PersonRow({ person: p, days, programmeId, canEdit }: { person: Person; days: Props['board']['days']; programmeId: number; canEdit: boolean }) {
    const [label, tone] = STANDING[p.status];
    const withdrawn = p.status === 'withdrawn';

    return (
        <li className={cn('flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center', withdrawn && 'opacity-60')}>
            <div className="min-w-0 flex-1">
                <p className={cn('truncate text-[13px] font-bold text-brand', withdrawn && 'line-through')}>{p.name}</p>
                <p className="truncate text-[11px] text-gray-400">{[p.organization, p.position].filter(Boolean).join(' · ') || p.email || p.phone || (p.source === 'self' ? 'Enrolled by the link' : '')}</p>
            </div>
            <div className="flex items-center justify-between gap-4 sm:justify-end">
                {days.length > 0 && (
                    <span className="flex items-center gap-1" aria-label={`Came to ${p.attended} of ${days.length} days`}>
                        {p.days.map((d, i) => (
                            <span
                                key={i}
                                title={`${days[i]?.label} · ${days[i]?.date ?? ''}: ${d === 'came' ? 'came' : d === 'missed' ? 'missed' : 'still to come'}`}
                                className={cn('flex size-5 items-center justify-center rounded-full text-[9px]', d === 'came' ? 'bg-mint-ink text-white' : d === 'missed' ? 'bg-rose-50 text-rose-400' : 'border border-dashed border-gray-300')}
                            >
                                {d === 'came' ? <Check className="size-3" /> : d === 'missed' ? <X className="size-3" /> : null}
                            </span>
                        ))}
                    </span>
                )}
                <span className="flex items-center gap-2">
                    {p.certificate ? (
                        <a href={p.certificate.url} target="_blank" rel="noreferrer" title={`${p.certificate.number}, issued ${p.certificate.issued}`} className={cn('inline-flex items-center gap-1 rounded-full px-3 py-1 text-[9px] font-black uppercase tracking-widest', tone)}>{label}<ExternalLink className="size-2.5" /></a>
                    ) : (
                        <span className={cn('rounded-full px-3 py-1 text-[9px] font-black uppercase tracking-widest', tone)}>{label}</span>
                    )}
                    {canEdit && !p.certificate && (
                        <button type="button" onClick={() => router.post(route(withdrawn ? 'programmes.enrolments.restore' : 'programmes.enrolments.withdraw', [programmeId, p.id]), {}, opts)} className="text-[10px] font-bold text-gray-400 underline hover:text-brand">
                            {withdrawn ? 'Put back' : 'Withdraw'}
                        </button>
                    )}
                </span>
            </div>
        </li>
    );
}

/** One person, or a list pasted from a spreadsheet. */
function AddPeople({ programmeId, onClose }: { programmeId: number; onClose: () => void }) {
    const [mode, setMode] = useState<'one' | 'list'>('one');
    const one = useForm({ full_name: '', email: '', phone: '', institution: '', position: '' });
    const list = useForm({ lines: '' });
    const lines = list.data.lines.split('\n').filter((l) => l.trim()).length;

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent aria-describedby="add-people-help" className="max-h-[92dvh] max-w-lg overflow-y-auto">
                <div className="space-y-5 p-6 sm:p-8">
                    <div>
                        <DialogTitle className="pr-10 text-xl normal-case not-italic tracking-tight">Add people</DialogTitle>
                        <DialogDescription id="add-people-help" className="mt-1 text-[13px] font-medium normal-case tracking-normal text-gray-500">They're enrolled for every day. Someone already on it just has their details updated.</DialogDescription>
                    </div>
                    <div className="flex gap-1 rounded-full bg-gray-100 p-1">
                        {(['one', 'list'] as const).map((m) => (
                            <button key={m} type="button" onClick={() => setMode(m)} className={cn('flex-1 rounded-full py-2 text-[10px] font-black uppercase tracking-widest', mode === m ? 'bg-white text-brand shadow-sm' : 'text-gray-400')}>{m === 'one' ? 'One person' : 'Paste a list'}</button>
                        ))}
                    </div>
                    {mode === 'one' ? (
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                one.post(route('programmes.enrolments.store', programmeId), { ...opts, onSuccess: () => one.reset() });
                            }}
                        >
                            {Object.values(one.errors)[0] && <p role="alert" className="rounded-xl bg-rose-50 p-3 text-[12px] font-bold text-rose-600">{Object.values(one.errors)[0]}</p>}
                            <div>
                                <label htmlFor="e-name" className={fieldLabel}>Full name</label>
                                <input id="e-name" required autoFocus value={one.data.full_name} onChange={(e) => one.setData('full_name', e.target.value)} className={field} />
                            </div>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="e-email" className={fieldLabel}>Email</label>
                                    <input id="e-email" type="email" value={one.data.email} onChange={(e) => one.setData('email', e.target.value)} placeholder="For the certificate" className={field} />
                                </div>
                                <div>
                                    <label htmlFor="e-phone" className={fieldLabel}>Phone</label>
                                    <input id="e-phone" type="tel" value={one.data.phone} onChange={(e) => one.setData('phone', e.target.value)} className={field} />
                                </div>
                                <div>
                                    <label htmlFor="e-org" className={fieldLabel}>Organisation</label>
                                    <input id="e-org" value={one.data.institution} onChange={(e) => one.setData('institution', e.target.value)} className={field} />
                                </div>
                                <div>
                                    <label htmlFor="e-pos" className={fieldLabel}>Position</label>
                                    <input id="e-pos" value={one.data.position} onChange={(e) => one.setData('position', e.target.value)} className={field} />
                                </div>
                            </div>
                            <button disabled={one.processing} className="w-full rounded-2xl bg-brand py-4 text-[11px] font-black uppercase tracking-[0.3em] text-white hover:bg-action disabled:opacity-50">Enrol</button>
                            <p className="text-center text-[11px] text-gray-400">The form stays open for the next person.</p>
                        </form>
                    ) : (
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                list.post(route('programmes.enrolments.import', programmeId), { ...opts, onSuccess: onClose });
                            }}
                        >
                            {list.errors.lines && <p role="alert" className="rounded-xl bg-rose-50 p-3 text-[12px] font-bold text-rose-600">{list.errors.lines}</p>}
                            <label htmlFor="e-lines" className={fieldLabel}>One person a line</label>
                            <textarea id="e-lines" autoFocus rows={8} value={list.data.lines} onChange={(e) => list.setData('lines', e.target.value)} placeholder={'Mpho Sello, mpho@example.com, 5800 0001, Queen II Hospital, Nurse\nLineo Ramoeletsi, lineo@example.com'} className={cn(field, 'resize-y font-medium text-[13px]')} />
                            <p className="ml-1 text-[11px] text-gray-400">Name first, then email, phone, organisation and position, as you have them, split by commas or copied straight from a spreadsheet.</p>
                            <button disabled={list.processing || lines === 0} className="w-full rounded-2xl bg-brand py-4 text-[11px] font-black uppercase tracking-[0.3em] text-white hover:bg-action disabled:opacity-50">{lines > 0 ? `Enrol ${lines}` : 'Enrol'}</button>
                        </form>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

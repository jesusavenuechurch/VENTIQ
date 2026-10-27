import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ScheduleSessionDialog, type SessionType } from '@/components/sessions/schedule-dialog';
import { clearDraft, readDraft } from '@/lib/session-draft';
import { ArrowLeft, CalendarPlus, ChevronRight, Clock, Mic, Repeat, Sparkles, Ticket, UsersRound, Zap } from 'lucide-react';
import SessionsLayout, { primaryButton, quietButton } from '@/Layouts/SessionsLayout';
import { AppLink } from '@/components/app-link';
import { cn } from '@/lib/utils';
import type { SessionsArea, SharedProps } from '@/types';

type Column = 'ready' | 'live' | 'upcoming' | 'recent';

interface Tile {
    id: number;
    title: string;
    type: string;
    status: string;
    attendees: number;
    expected: number | null;
    presenters: number;
    speaker: number | null;
    starts: string | null;
    updated: string;
    tracking: boolean;
    series: { id: number; rule: string } | null;
}

interface Props {
    columns: Record<Column, Tile[]>;
    filter: Column | null;
    members: number;
    pendingInvites: number;
    series: { id: number; title: string; rule: string; next: string | null }[];
    types: SessionType[];
    today: string;
    /** Opened from /sessions/create (old links). */
    openSchedule: boolean;
}

const COLUMNS: Record<Column, { label: string; tint: string; empty: string }> = {
    ready: { label: 'Ready to review', tint: '#FDF3D9', empty: 'Nothing waiting' },
    live: { label: 'Happening now', tint: '#FFE9CE', empty: 'Nothing live' },
    upcoming: { label: 'Coming up', tint: '#E9EEF5', empty: 'Nothing scheduled' },
    recent: { label: 'Recently finished', tint: '#F1F5F9', empty: 'Nothing yet' },
};
const ROTATIONS = ['-2deg', '1.5deg', '-1deg', '2deg', '-1.5deg'];

/** The desk: sessions to review, live, coming up and just finished, as notes on a board. */
export default function Index({ columns, filter, members, pendingInvites, series, types, today, openSchedule }: Props) {
    const { auth, sessionsArea } = usePage<SharedProps & { sessionsArea: SessionsArea }>().props;
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    const can = sessionsArea.can;
    // A session half scheduled earlier in this visit: offer to finish it.
    const [draft, setDraft] = useState<{ title: string; programme: number | null } | null>(null);
    const [scheduling, setScheduling] = useState(false);
    const checkDraft = () => {
        const d = readDraft();
        setDraft(d?.data.title?.trim() && !d.data.programme_id ? { title: d.data.title.trim(), programme: null } : null);
    };
    useEffect(() => {
        checkDraft();
        if (openSchedule) setScheduling(true);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    const schedule = (open: boolean) => {
        setScheduling(open);
        if (!open) checkDraft();
    };
    const outOfSessions = sessionsArea.quota.remaining <= 0;
    const anything = (Object.keys(COLUMNS) as Column[]).some((c) => columns[c].length > 0);

    return (
        <SessionsLayout
            title={`${greeting}, ${auth.user?.firstName ?? 'there'}`}
            subtitle={
                <span className="flex flex-wrap items-center gap-2">
                    What's happening today?
                    {/* The plan and the team at a glance: small badges, out of the way of the buttons. */}
                    {can.plan && (
                        <AppLink to="sessions.plan" className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest', outOfSessions && !sessionsArea.quota.corporate ? 'bg-rose-50 text-rose-700' : 'bg-mint text-mint-ink')}>
                            <Ticket className="size-3" />
                            {sessionsArea.quota.corporate ? `${sessionsArea.quota.used} this month` : `${Math.max(0, sessionsArea.quota.remaining)} ${sessionsArea.quota.remaining === 1 ? 'session' : 'sessions'} left`}
                        </AppLink>
                    )}
                    <AppLink to="organizer.team.index" params={{ from: 'sessions' }} title={`${members} ${members === 1 ? 'member' : 'members'}${pendingInvites ? `, ${pendingInvites} invited` : ''}: your team`} className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-brand">
                        <UsersRound className="size-3" />{members}{pendingInvites > 0 && <span className="text-amber-600">+{pendingInvites}</span>}
                    </AppLink>
                </span>
            }
            actions={
                <>
                    {can.run && <button type="button" onClick={() => schedule(true)} className={primaryButton}><Sparkles className="size-4" />Schedule a session</button>}
                    {/* A meeting started now, nothing to fill in: straight into the notes. */}
                    {can.run && (
                        <button type="button" onClick={() => router.post(route('sessions.quick'))} title="A meeting that starts now: you chair it, add items as you go" className="inline-flex items-center justify-center gap-2 rounded-2xl bg-lilac px-5 py-3 text-[11px] font-black uppercase tracking-widest text-lilac-ink shadow-md transition-all hover:-translate-y-0.5 hover:shadow-lg">
                            <Zap className="size-4" />Quick catch-up
                        </button>
                    )}
                    {can.run && <AppLink to="organizer.events.create" className={quietButton}><CalendarPlus className="size-3.5" />Create an event</AppLink>}
                </>
            }
        >
            {draft && can.run && (
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-action/30 bg-white p-4 shadow-sm">
                    <p className="min-w-0 text-[13px] font-bold text-brand">
                        <Sparkles className="mr-1.5 inline size-4 text-action" />You were scheduling <span className="text-action">{draft.title}</span>
                    </p>
                    <span className="flex shrink-0 items-center gap-3">
                        <button type="button" onClick={() => { clearDraft(); setDraft(null); }} className="text-[11px] font-bold text-gray-400 hover:text-brand">Discard</button>
                        <button type="button" onClick={() => schedule(true)} className={primaryButton}>Finish scheduling</button>
                    </span>
                </div>
            )}

            {outOfSessions && can.plan && (
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-100 bg-amber-50 p-4">
                    <p className="text-[12px] font-bold text-amber-800">You've used the sessions in your plan this month. Add more, or move to a bigger plan.</p>
                    <AppLink to="sessions.plan" className="shrink-0 rounded-full bg-amber-500 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white">Add sessions</AppLink>
                </div>
            )}

            {series.length > 0 && !filter && (
                <div className="mb-6">
                    <p className="mb-2 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Repeating</p>
                    <div className="flex gap-3 overflow-x-auto pb-1 [scrollbar-width:none]">
                        {series.map((s) => (
                            <Link key={s.id} href={route('sessions.series.show', s.id)} className="flex min-w-[15rem] shrink-0 items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3 transition-all hover:border-brand/30 hover:shadow-sm">
                                <span className="min-w-0">
                                    <span className="block truncate text-[13px] font-bold text-brand"><Repeat className="mr-1.5 inline size-3.5 text-action" />{s.title}</span>
                                    <span className="block truncate text-[11px] text-gray-400">{s.rule}{s.next && ` · next ${s.next}`}</span>
                                </span>
                                <ChevronRight className="size-4 shrink-0 text-gray-300" />
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {filter ? (
                <>
                    <Link href={route('sessions.index')} className="mb-4 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-brand"><ArrowLeft className="size-3" />Back to the desk</Link>
                    <Board>
                        <p className="mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">{COLUMNS[filter].label} · {columns[filter].length}</p>
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {columns[filter].map((t) => <Note key={t.id} tile={t} column={filter} can={can} />)}
                            {columns[filter].length === 0 && <p className="text-[11px] italic text-gray-400">{COLUMNS[filter].empty}</p>}
                        </div>
                    </Board>
                </>
            ) : anything ? (
                <Board>
                    <div className="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-0">
                        {(Object.keys(COLUMNS) as Column[]).map((c, i) => (
                            <div key={c} className={cn(i > 0 && 'lg:border-l lg:border-dashed lg:border-slate-900/10 lg:pl-6', i < 3 && 'lg:pr-6')}>
                                <p className="mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">{COLUMNS[c].label}</p>
                                <div className="space-y-5">
                                    {columns[c].slice(0, 4).map((t) => <Note key={t.id} tile={t} column={c} can={can} />)}
                                    {columns[c].length === 0 && <p className="text-[11px] italic text-gray-400">{COLUMNS[c].empty}</p>}
                                </div>
                                {columns[c].length > 4 && (
                                    <Link href={route('sessions.index', { filter: c })} className="mt-3 inline-block text-[11px] font-bold text-brand underline">See all {columns[c].length}</Link>
                                )}
                            </div>
                        ))}
                    </div>
                </Board>
            ) : (
                <div className="rounded-[2rem] border border-dashed border-gray-200 py-16 text-center">
                    <p className="text-sm font-bold text-gray-400">Your sessions will show up here.</p>
                    {can.run && <button type="button" onClick={() => schedule(true)} className={cn(primaryButton, 'mt-6')}><Sparkles className="size-4" />Schedule your first session</button>}
                </div>
            )}
            {can.run && <ScheduleSessionDialog open={scheduling} onOpenChange={schedule} types={types} programme={null} today={today} />}
        </SessionsLayout>
    );
}

function Board({ children }: { children: React.ReactNode }) {
    return <div className="rounded-3xl bg-[#F7F3EC] p-6">{children}</div>;
}

/** A session as a taped-on sticky note. */
function Note({ tile, column, can }: { tile: Tile; column: Column; can: SessionsArea['can'] }) {
    // Where it opens depends on the role: the console to run it, the report
    // to read it, or the check-in list at the door. Otherwise just a note.
    const href = can.run
        ? (tile.status === 'reported' ? route('sessions.report', tile.id) : route('sessions.show', tile.id))
        : can.view && tile.status === 'reported'
          ? route('sessions.report', tile.id)
          : can.checkin && tile.tracking
            ? route('sessions.checkin', tile.id)
            : null;
    const Tag = href ? Link : 'div';
    const people =
        column === 'live' ? `${tile.attendees} checked in` : column === 'upcoming' ? `${tile.attendees || tile.expected || '—'} expected` : `${tile.attendees} attended`;
    const status = {
        ready: 'Report ready',
        live: 'Live now',
        upcoming: tile.starts ? `Starts ${tile.starts}` : 'Scheduled',
        recent: tile.updated,
    }[column];

    return (
        <Tag
            href={href ?? ''}
            className="group relative block rounded-b-[10px] rounded-t px-3.5 pb-3.5 pt-[18px] shadow-[0_10px_20px_rgba(15,23,42,0.12)] transition-all hover:!rotate-0 hover:-translate-y-1 hover:shadow-[0_16px_28px_rgba(15,23,42,0.18)]"
            style={{ background: COLUMNS[column].tint, transform: `rotate(${ROTATIONS[tile.id % ROTATIONS.length]})` }}
        >
            <span className="absolute -top-2.5 left-1/2 h-[18px] w-[50px] -translate-x-1/2 -rotate-3 border border-white/80 bg-white/60 shadow-sm" />
            <p className="mb-1 line-clamp-2 text-[13px] font-black leading-snug text-brand">{tile.title}</p>
            <p className="mb-2.5 text-[9px] font-black uppercase tracking-widest text-slate-900/40">{tile.type}{tile.series && <span title={tile.series.rule}><Repeat className="ml-1.5 inline size-3" /></span>}</p>
            <div className="mb-2.5 flex flex-wrap items-center gap-3 text-[10px] font-bold text-slate-900/55">
                <span className="inline-flex items-center gap-1"><UsersRound className="size-3" />{people}</span>
                {column === 'live' && tile.speaker ? (
                    <span className="inline-flex items-center gap-1"><Mic className="size-3" />Speaker {tile.speaker} of {tile.presenters}</span>
                ) : tile.presenters > 0 ? (
                    <span className="inline-flex items-center gap-1"><Mic className="size-3" />{tile.presenters} {tile.presenters === 1 ? 'presenter' : 'presenters'}</span>
                ) : null}
            </div>
            <p className="flex items-center gap-1 text-[9px] font-bold text-slate-900/45">
                {column === 'live' ? <span className="size-1.5 animate-pulse rounded-full bg-emerald-500" /> : column === 'upcoming' && <Clock className="size-3" />}
                {status}
            </p>
        </Tag>
    );
}

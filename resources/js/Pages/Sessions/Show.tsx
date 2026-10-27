import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronLeft, ChevronRight, Link2, ListOrdered, PencilSparkles, Plus, QrCode, X } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { ActionPointCard } from '@/components/sessions/action-points';
import { json } from '@/lib/http';
import { cn } from '@/lib/utils';

type Status = 'upcoming' | 'active' | 'completed';
type InsightType = 'theme' | 'decision' | 'action' | 'question';

interface Line {
    time: string;
    text: string;
}

interface Insight {
    id: number;
    type: InsightType | string;
    text: string;
    pending?: boolean;
    /** The line it came from, typed here: taking the line back takes the note too. */
    line?: string;
}

interface Segment {
    id: number;
    name: string;
    role: string | null;
    presenting: boolean;
    topic: string;
    status: Status;
    paused: boolean;
    duration: number;
    logs: Line[];
    insights: Insight[];
}

/** A segment on this page: plus where its notebook pages break, and which one is open. */
interface Speaker extends Segment {
    breaks: number[];
    page: number;
}

interface Role {
    value: string;
    label: string;
    presenting: boolean;
}

interface Props {
    session: { id: number; title: string; status: string; tracking: boolean; checkinUrl: string | null; expected: number | null; participants: number; agenda: boolean; chair: string | null; secretary: string | null };
    segments: Segment[];
    roles: Role[];
    /** In a series: action points still open from earlier meetings. */
    carried: ActionPointCard[];
}

const PALETTE = ['#D4AF37', '#10b981', '#0f172a', '#64748b', '#94a3b8'];
const COLORS: Record<string, string> = { theme: '#D4AF37', decision: '#10b981', action: '#0f172a', question: '#64748b' };
const TINTS: Record<string, string> = { theme: '#FBF3D9', decision: '#DFF5EC', action: '#E7EAF0', question: '#EEF1F4' };
const label = 'text-[10px] font-bold uppercase tracking-[2px] text-slate-400';
const pill = 'inline-block rounded-full px-3 py-1.5 text-[9px] font-black uppercase tracking-[1.5px] text-white';

const clock = (sec: number) => `${String(Math.floor(sec / 60)).padStart(2, '0')}:${String(sec % 60).padStart(2, '0')}`;
const initials = (name: string) => name.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
/** A meeting's agenda item: the item is its topic, and who leads it (if anyone) its name. */
const itemOf = (s: Segment) => s.topic || s.name;
const leadOf = (s: Segment) => (s.topic && s.name !== s.topic ? s.name : null);

async function call<T = Record<string, unknown>>(url: string, method = 'POST', body?: unknown): Promise<T | null> {
    try {
        const { ok, data } = await json<T>(url, { method, body });
        return ok ? data : null;
    } catch {
        return null;
    }
}

/**
 * The live session: who's presenting (left), a notebook page to type
 * notes on as they speak (middle) and Ventiq Assist's sticky notes
 * (right). The clock pauses on its own while the tab is in the
 * background. Each line is saved as it's written; the last one can be
 * pulled back to fix a mistyped Enter.
 */
export default function Show({ session, segments, roles, carried }: Props) {
    const base = `/sessions/${session.id}`;
    const [speakers, setSpeakers] = useState<Speaker[]>(() => segments.map((s) => ({ ...s, breaks: [0], page: 0 })));
    const active = segments.find((s) => s.status === 'active');
    const [isLive, setIsLive] = useState(!!active || session.status === 'active');
    const [liveId, setLiveId] = useState<number | null>(active?.id ?? null);
    const [selectedId, setSelectedId] = useState<number | null>(active?.id ?? segments[0]?.id ?? null);
    const [paused, setPaused] = useState<null | 'manual' | 'auto'>(active?.paused ? 'manual' : null);
    const [globalClock, setGlobalClock] = useState(() => segments.reduce((sum, s) => sum + (s.duration || 0), 0));
    const [draft, setDraft] = useState('');
    const [thinking, setThinking] = useState(0);
    const [participants, setParticipants] = useState(session.participants);
    const [copied, setCopied] = useState(false);
    const [adding, setAdding] = useState(false);
    const [newPerson, setNewPerson] = useState({ name: '', title: '', role: '', presenting: true });
    const agenda = session.agenda;
    const nameOf = (s: Segment) => (agenda ? itemOf(s) : s.name);
    const [removing, setRemoving] = useState<Speaker | null>(null);
    const [flipping, setFlipping] = useState(false);
    const [fill, setFill] = useState(0);
    const [finishing, setFinishing] = useState(false);
    const [finishFailed, setFinishFailed] = useState(false);
    // On a phone the notebook has the screen; the agenda and Ventiq Assist slide in when wanted.
    const [drawer, setDrawer] = useState<null | 'agenda' | 'assist'>(null);
    const [startError, setStartError] = useState('');

    const notesRef = useRef<HTMLDivElement>(null);
    const draftRef = useRef<HTMLTextAreaElement>(null);
    // Lines and undos go to the server in the order they happened.
    const queue = useRef<Promise<unknown>>(Promise.resolve());
    const send = (url: string, body?: unknown) => {
        queue.current = queue.current.then(() => call(url, 'POST', body));
        return queue.current;
    };

    const selected = speakers.find((s) => s.id === selectedId) ?? null;
    const live = speakers.find((s) => s.id === liveId) ?? null;
    const capturingHere = isLive && !!selected && selected.id === liveId;
    const onLatestPage = !selected || selected.page === selected.breaks.length - 1;
    const completed = speakers.filter((s) => s.status === 'completed').length;
    const isLastSpeaker = useMemo(() => {
        const i = speakers.findIndex((s) => s.id === liveId);
        return i === -1 || i === speakers.length - 1;
    }, [speakers, liveId]);
    const update = useCallback((id: number, change: (s: Speaker) => Partial<Speaker>) => {
        setSpeakers((all) => all.map((s) => (s.id === id ? { ...s, ...change(s) } : s)));
    }, []);

    // The clock: the whole session, and whoever is on.
    useEffect(() => {
        if (!isLive || paused) return;
        const t = window.setInterval(() => {
            setGlobalClock((c) => c + 1);
            setSpeakers((all) => all.map((s) => (s.id === liveId ? { ...s, duration: s.duration + 1 } : s)));
        }, 1000);
        return () => window.clearInterval(t);
    }, [isLive, paused, liveId]);

    // Check-ins as they come in.
    useEffect(() => {
        if (!session.tracking) return;
        const t = window.setInterval(async () => {
            const data = await call<{ count: number }>(route('sessions.participants.count', session.id), 'GET');
            if (data) setParticipants(data.count);
        }, 8000);
        return () => window.clearInterval(t);
    }, [session.tracking, session.id]);

    const pause = useCallback(async (reason: 'manual' | 'auto') => {
        if (!isLive || paused || !liveId) return;
        setPaused(reason);
        await call(`${base}/segments/${liveId}/pause`);
    }, [isLive, paused, liveId, base]);

    const resume = useCallback(async () => {
        if (!isLive || !paused || !liveId) return;
        setPaused(null);
        const data = await call<{ duration_seconds?: number }>(`${base}/segments/${liveId}/resume`);
        if (typeof data?.duration_seconds === 'number') update(liveId, () => ({ duration: data.duration_seconds! }));
        window.setTimeout(() => draftRef.current?.focus(), 0);
    }, [isLive, paused, liveId, base, update]);

    // Away from the tab: pause; back: resume, unless they paused it themselves.
    useEffect(() => {
        const on = () => {
            if (document.hidden) void pause('auto');
            else if (paused === 'auto') void resume();
        };
        document.addEventListener('visibilitychange', on);
        return () => document.removeEventListener('visibilitychange', on);
    }, [pause, resume, paused]);

    // The draft line grows with what's typed; how full the page is.
    const measure = useCallback(() => {
        const el = draftRef.current;
        if (el) {
            el.style.height = 'auto';
            el.style.height = `${el.scrollHeight}px`;
        }
        const notes = notesRef.current;
        const content = notes?.firstElementChild as HTMLElement | null;
        if (notes && content) setFill(content.scrollHeight / notes.clientHeight);
    }, []);
    useLayoutEffect(measure, [draft, selectedId, measure]);

    // A full page turns: older lines stay in the notes, reachable with ‹.
    const turnPageIfFull = useCallback(() => {
        const notes = notesRef.current;
        const content = notes?.firstElementChild as HTMLElement | null;
        if (!notes || !content || !liveId || content.scrollHeight <= notes.clientHeight + 2) return;
        setFlipping(true);
        window.setTimeout(() => {
            update(liveId, (s) => {
                const last = s.breaks[s.breaks.length - 1];
                const next = Math.max(last, s.logs.length - 1);
                const breaks = next > last ? [...s.breaks, next] : s.breaks;
                return { breaks, page: breaks.length - 1 };
            });
            setFlipping(false);
            setFill(0);
            draftRef.current?.focus();
        }, 240);
    }, [liveId, update]);

    async function start() {
        setStartError('');
        const res = await json<{ active_segment_id?: number | null; message?: string }>(`${base}/start`, { method: 'POST' }).catch(() => null);
        if (!res?.ok) {
            // A used-up plan (402) says so; anything else is the connection.
            setStartError(res?.data.message ?? "It didn't start. Check your connection and try again.");
            return;
        }
        const data = res.data;
        setIsLive(true);
        const first = data?.active_segment_id ?? speakers[0]?.id ?? null;
        if (first) {
            update(first, () => ({ status: 'active' }));
            setLiveId(first);
            setSelectedId(first);
        }
        window.setTimeout(() => draftRef.current?.focus(), 0);
    }

    async function surface(speakerId: number, line: string) {
        const pendingId = -Date.now();
        setThinking((n) => n + 1);
        update(speakerId, (s) => ({ insights: [...s.insights, { id: pendingId, type: 'theme', text: '', pending: true, line }] }));
        const data = await call<{ category: string; text?: string }>(`${base}/segments/${speakerId}/tag`, 'POST', { line });
        update(speakerId, (s) => ({
            insights: !data || data.category === 'none' || !data.text
                ? s.insights.filter((i) => i.id !== pendingId)
                : s.insights.map((i) => (i.id === pendingId ? { id: pendingId, type: data.category, text: data.text!, line } : i)),
        }));
        setThinking((n) => n - 1);
    }

    function commit() {
        const text = draft.trim();
        if (!text || !live) return;
        update(live.id, (s) => ({ logs: [...s.logs, { time: new Date().toTimeString().slice(0, 5), text }] }));
        setDraft('');
        void send(`${base}/segments/${live.id}/log`, { text });
        void surface(live.id, text);
        window.setTimeout(turnPageIfFull, 0);
    }

    // Only the latest line, on the page being written, while it's live.
    function editLastLine() {
        if (!live || !live.logs.length) return;
        const last = live.logs[live.logs.length - 1];
        update(live.id, (s) => {
            const breaks = s.breaks.filter((b) => b < s.logs.length - 1 || b === 0);
            // Its sticky note (or the one still being thought about) goes with it.
            const noteIndex = s.insights.map((i) => i.line).lastIndexOf(last.text);
            return { logs: s.logs.slice(0, -1), breaks, page: Math.min(s.page, breaks.length - 1), insights: noteIndex >= 0 ? s.insights.filter((_, j) => j !== noteIndex) : s.insights };
        });
        setDraft(last.text);
        void send(`${base}/segments/${live.id}/log/undo`);
        window.setTimeout(() => {
            const el = draftRef.current;
            el?.focus();
            el?.setSelectionRange(el.value.length, el.value.length);
        }, 0);
    }

    async function finishSegment() {
        if (!live || finishing) return;
        setFinishing(true);
        await queue.current;
        setFinishFailed(false);
        const data = await call<{ next_segment_id: number | null }>(`${base}/segments/${live.id}/finish`);
        setFinishing(false);
        if (!data) {
            setFinishFailed(true);
            return;
        }
        update(live.id, () => ({ status: 'completed' }));
        const next = data.next_segment_id;
        if (next) {
            update(next, () => ({ status: 'active' }));
            setLiveId(next);
            setSelectedId(next);
            setDraft('');
        } else {
            setIsLive(false);
            router.visit(route('sessions.report', session.id));
        }
    }

    async function addPerson() {
        const name = newPerson.name.trim();
        const title = newPerson.title.trim();
        if (agenda ? !title : !name) return;
        const data = await call<{ status: string; segment: { id: number; name: string; topic: string; role: string | null; is_presenting: boolean; status: Status } }>(`${base}/segments`, 'POST', agenda
            ? { title, name: name || null, presenting: true }
            : { name, role: newPerson.role || null, presenting: newPerson.presenting });
        if (data?.status !== 'ok') return;
        const seg = data.segment;
        setSpeakers((all) => [...all, { id: seg.id, name: seg.name, role: seg.role, presenting: !!seg.is_presenting, topic: seg.topic ?? '', status: seg.status, paused: false, duration: 0, logs: [], insights: [], breaks: [0], page: 0 }]);
        setNewPerson({ name: '', title: '', role: '', presenting: true });
        setAdding(false);
        if (seg.status === 'active') {
            setLiveId(seg.id);
            setSelectedId(seg.id);
        }
    }

    async function removePerson(s: Speaker) {
        const data = await call<{ status: string }>(`${base}/segments/${s.id}`, 'DELETE');
        setRemoving(null);
        if (data?.status !== 'ok') return;
        setSpeakers((all) => all.filter((x) => x.id !== s.id));
        if (selectedId === s.id) setSelectedId(speakers.find((x) => x.id !== s.id)?.id ?? null);
    }

    // The presentation's name: saved a moment after typing stops.
    const topicTimer = useRef<number>(0);
    function setTopic(value: string) {
        if (!selected) return;
        const id = selected.id;
        update(id, () => ({ topic: value }));
        window.clearTimeout(topicTimer.current);
        topicTimer.current = window.setTimeout(() => void call(`${base}/segments/${id}`, 'PATCH', { title: value }), 600);
    }

    const canRemove = (s: Speaker) => s.status !== 'active' && s.logs.length === 0;
    const visible = selected ? selected.logs.slice(selected.breaks[selected.page] ?? 0, selected.breaks[selected.page + 1] ?? selected.logs.length) : [];
    const canEdit = (i: number) => capturingHere && onLatestPage && !paused && i === visible.length - 1;
    const aiState = thinking > 0 ? 'thinking' : isLive && draft.trim() ? 'following' : isLive ? 'listening' : 'idle';

    const copyLink = async () => {
        if (!session.checkinUrl) return;
        try {
            await navigator.clipboard.writeText(session.checkinUrl);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Copy the check-in link', session.checkinUrl);
        }
    };

    const presenting = speakers.filter((s) => s.presenting);
    const others = speakers.filter((s) => !s.presenting);
    const order: Record<Status, number> = { active: 0, upcoming: 1, completed: 2 };

    const roster = (
        <>
            {session.tracking && (
                <div className="rounded-2xl border-2 border-action bg-white p-4">
                    <div className="mb-1 flex items-center justify-between">
                        <p className="text-[10px] font-black uppercase tracking-widest text-slate-900">Checked in</p>
                        <span className="text-[13px] font-black text-slate-900">{participants}{session.expected ? ` / ${session.expected}` : ''}</span>
                    </div>
                    <p className="mb-3 text-[10px] font-semibold leading-snug text-slate-400">Share the QR code or link so people check themselves in.</p>
                    <div className="grid grid-cols-2 gap-2">
                        <a href={route('sessions.checkin.pass', session.id)} target="_blank" rel="noreferrer" className="flex flex-col items-center gap-1 rounded-xl bg-action py-3 text-[10px] font-black uppercase tracking-wide text-white hover:opacity-90">
                            <QrCode className="size-4" />Show QR
                        </a>
                        <button type="button" onClick={copyLink} className={cn('flex flex-col items-center gap-1 rounded-xl py-3 text-[10px] font-black uppercase tracking-wide text-white hover:opacity-90', copied ? 'bg-emerald-500' : 'bg-slate-900')}>
                            {copied ? <Check className="size-4" /> : <Link2 className="size-4" />}{copied ? 'Copied' : 'Copy link'}
                        </button>
                    </div>
                    <a href={route('sessions.checkin', session.id)} target="_blank" rel="noreferrer" className="mt-3 block text-center text-[10px] font-bold text-slate-400 underline">Check-in list</a>
                </div>
            )}

            {presenting.length > 0 && (
                <div>
                    <p className={cn(label, 'mb-2 px-2')}>{agenda ? 'Agenda' : 'Presenting'}</p>
                    <div className="max-h-[280px] space-y-1 overflow-y-auto">
                        {[...presenting].sort((a, b) => order[a.status] - order[b.status]).map((s) => (
                            <RosterRow
                                key={s.id}
                                s={s}
                                name={nameOf(s)}
                                mark={agenda ? String(presenting.indexOf(s) + 1) : undefined}
                                color={PALETTE[speakers.indexOf(s) % PALETTE.length]}
                                selected={selectedId === s.id}
                                onSelect={() => { setSelectedId(s.id); setDrawer(null); }}
                                onRemove={canRemove(s) ? () => setRemoving(s) : undefined}
                                below={s.status === 'active' ? <span className={cn(pill, 'mt-1 bg-[#D4AF37]')}>{agenda ? 'On now' : 'Live'}</span> : agenda && leadOf(s) ? <span className="block truncate text-[9px] font-bold text-slate-400">{leadOf(s)}</span> : null}
                                trailing={!canRemove(s) && <span className="shrink-0 text-[9px] font-bold text-slate-400">{s.status === 'completed' ? <Check className="size-3" /> : clock(s.duration)}</span>}
                            />
                        ))}
                    </div>
                </div>
            )}

            {others.length > 0 && (
                <div>
                    <p className={cn(label, 'mb-2 px-2')}>Not presenting</p>
                    <div className="max-h-[200px] space-y-1 overflow-y-auto">
                        {others.map((s) => (
                            <RosterRow
                                key={s.id}
                                s={s}
                                color={PALETTE[speakers.indexOf(s) % PALETTE.length]}
                                selected={selectedId === s.id}
                                onSelect={() => { setSelectedId(s.id); setDrawer(null); }}
                                onRemove={canRemove(s) ? () => setRemoving(s) : undefined}
                                below={<span className="text-[8px] font-bold uppercase tracking-wide text-slate-300">{roles.find((r) => r.value === s.role)?.label ?? s.role ?? 'Not presenting'}</span>}
                            />
                        ))}
                    </div>
                </div>
            )}

            <div className="px-2">
                {adding ? (
                    <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); void addPerson(); }}>
                        {agenda && <input autoFocus aria-label="Agenda item" value={newPerson.title} onChange={(e) => setNewPerson({ ...newPerson, title: e.target.value })} onKeyDown={(e) => e.key === 'Escape' && setAdding(false)} placeholder="Item…" className="w-full rounded-lg bg-white px-3 py-2 text-[12px] font-semibold text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-[#D4AF37]" />}
                        <input autoFocus={!agenda} aria-label={agenda ? 'Led by' : 'Name'} value={newPerson.name} onChange={(e) => setNewPerson({ ...newPerson, name: e.target.value })} onKeyDown={(e) => e.key === 'Escape' && setAdding(false)} placeholder={agenda ? 'Led by (optional)' : 'Name…'} className="w-full rounded-lg bg-white px-3 py-2 text-[12px] font-semibold text-slate-900 outline-none ring-1 ring-slate-200 focus:ring-[#D4AF37]" />
                        {!agenda && <div className="flex items-center gap-2">
                            {roles.length > 0 && (
                                <select aria-label="Role" value={newPerson.role} onChange={(e) => { const r = roles.find((x) => x.value === e.target.value); setNewPerson({ ...newPerson, role: e.target.value, presenting: r ? r.presenting : newPerson.presenting }); }} className="min-w-0 flex-1 rounded-lg bg-white px-2 py-2 text-[11px] font-semibold outline-none ring-1 ring-slate-200">
                                    <option value="">Role…</option>
                                    {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                                </select>
                            )}
                            <label className="flex items-center gap-1 whitespace-nowrap text-[9px] font-black uppercase text-slate-500">
                                <input type="checkbox" checked={newPerson.presenting} onChange={(e) => setNewPerson({ ...newPerson, presenting: e.target.checked })} className="accent-brand" />Presenting
                            </label>
                        </div>}
                        <div className="flex gap-2">
                            <button className="flex-1 rounded-lg bg-slate-900 py-2 text-[10px] font-black uppercase tracking-widest text-white">Add</button>
                            <button type="button" onClick={() => setAdding(false)} className="rounded-lg bg-white px-3 py-2 text-[10px] font-black uppercase tracking-widest text-slate-400 ring-1 ring-slate-200">Cancel</button>
                        </div>
                    </form>
                ) : (
                    <button type="button" onClick={() => setAdding(true)} className={cn(label, 'flex items-center gap-1 py-2 hover:text-slate-900')}><Plus className="size-3" />{agenda ? 'Add item' : 'Add person'}</button>
                )}
            </div>
        </>
    );

    // Ticked off during the meeting: done in this session.
    const [doneHere, setDoneHere] = useState<Set<number>>(() => new Set(carried.filter((p) => p.status === 'done').map((p) => p.id)));
    const toggleCarried = async (p: ActionPointCard) => {
        const done = doneHere.has(p.id);
        const next = new Set(doneHere);
        done ? next.delete(p.id) : next.add(p.id);
        setDoneHere(next);
        const ok = await call(route(done ? 'sessions.actions.reopen' : 'sessions.actions.done', p.id), 'POST', done ? {} : { session_id: session.id });
        if (!ok) setDoneHere(doneHere);
    };
    const fromLastTime = carried.length > 0 && (
        <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-4">
            <p className="text-[10px] font-black uppercase tracking-widest text-slate-900">From last time</p>
            <p className="mb-3 text-[10px] font-semibold text-slate-400">Still open. Tick what's done.</p>
            <ul className="space-y-2.5">
                {carried.map((p) => {
                    const done = doneHere.has(p.id);
                    return (
                        <li key={p.id}>
                            <label className="flex cursor-pointer items-start gap-2.5">
                                <input type="checkbox" checked={done} onChange={() => toggleCarried(p)} className="mt-0.5 size-4 shrink-0 accent-emerald-600" />
                                <span className="min-w-0">
                                    <span className={cn('block text-[12px] font-semibold leading-snug text-slate-900', done && 'text-slate-400 line-through')}>{p.task}</span>
                                    <span className={cn('block text-[10px] text-slate-400', p.overdue && !done && 'font-bold text-rose-600')}>{[p.owner, p.dueLabel && (p.overdue ? `overdue, ${p.dueLabel}` : `by ${p.dueLabel}`)].filter(Boolean).join(' · ')}</span>
                                </span>
                            </label>
                        </li>
                    );
                })}
            </ul>
        </div>
    );

    const assist = (
        <div className="space-y-6">
            {(!selected || selected.insights.length === 0) && <p className="px-1 text-[12px] italic text-slate-400">Sticky notes collect here as things come up.</p>}
            {selected?.insights.map((n, i) => (
                <div key={n.id} className="relative animate-in fade-in slide-in-from-bottom-2 rounded-b-[10px] rounded-t-md px-3.5 pb-3.5 pt-4 shadow-[0_8px_18px_rgba(15,23,42,0.10)]" style={{ background: TINTS[n.type] ?? '#F1F5F9', transform: `rotate(${['-2deg', '1.5deg', '-0.8deg'][i % 3]})` }}>
                    <span className="absolute -top-2 left-1/2 h-4 w-[46px] -translate-x-1/2 -rotate-3 border border-white/70 bg-white/55" />
                    {n.pending ? (
                        <div className="flex justify-center gap-1 py-1">
                            {[0, 150, 300].map((d) => <span key={d} className="size-1.5 animate-bounce rounded-full bg-slate-900/25" style={{ animationDelay: `${d}ms` }} />)}
                        </div>
                    ) : (
                        <>
                            <span className={cn(pill, 'px-2 py-0.5 text-[8px]')} style={{ background: COLORS[n.type] ?? '#94a3b8' }}>{n.type}</span>
                            <p className="mt-2 text-[13px] font-semibold leading-snug text-slate-900">{n.text}</p>
                        </>
                    )}
                </div>
            ))}
        </div>
    );

    return (
        <div className="min-h-dvh bg-black text-slate-900 lg:h-dvh lg:overflow-hidden lg:p-8">
            <Head title={session.title} />
            <div className="relative mx-auto flex min-h-dvh max-w-[1500px] flex-col overflow-hidden bg-white lg:h-full lg:min-h-0 lg:flex-row lg:rounded-[40px] lg:shadow-[0_40px_100px_rgba(0,0,0,0.5)]">
                {/* Left: who's presenting */}
                {drawer && <button type="button" aria-label="Close" onClick={() => setDrawer(null)} className="fixed inset-0 z-30 bg-slate-900/40 animate-in fade-in lg:hidden" />}
                <aside className={cn('fixed inset-y-0 left-0 z-40 flex w-[86%] max-w-[320px] flex-col bg-slate-50 shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:h-full lg:w-64 lg:max-w-none lg:translate-x-0 lg:border-r-2 lg:border-dashed lg:border-slate-200 lg:shadow-none', drawer === 'agenda' ? 'translate-x-0' : '-translate-x-full')}>
                    <div className="flex items-start justify-between px-5 pb-4 pt-6">
                        <div className="min-w-0">
                            <Link href={route('sessions.index')} className="flex items-center gap-1 text-[14px] font-black tracking-tight"><ArrowLeft className="size-3.5" />VENTIQ</Link>
                            <p className={cn(label, 'mt-2')}>Live session</p>
                            <p className="mt-0.5 truncate text-[11px] font-bold uppercase text-slate-400">{session.title}</p>
                            {agenda && (session.chair || session.secretary) && (
                                <p className="mt-1 text-[10px] font-semibold leading-snug text-slate-400">
                                    {[session.chair && `Chair: ${session.chair}`, session.secretary && `Secretary: ${session.secretary}`].filter(Boolean).join(' · ')}
                                </p>
                            )}
                        </div>
                        <button type="button" aria-label="Close" onClick={() => setDrawer(null)} className="flex size-8 shrink-0 items-center justify-center rounded-full bg-black/5 text-slate-500 lg:hidden"><X className="size-4" /></button>
                    </div>
                    <div className="flex-1 space-y-5 overflow-y-auto px-3 pb-4">{roster}</div>
                    <div className="flex items-center justify-between border-t-2 border-dashed border-slate-200 px-5 py-4">
                        <span className={label}>{completed}/{speakers.length} {agenda ? 'items' : 'done'}</span>
                        <span className="text-[10px] font-black">{clock(globalClock)}</span>
                    </div>
                </aside>

                {/* Middle: the notebook */}
                <main className="relative flex min-h-dvh flex-1 flex-col overflow-hidden lg:h-full lg:min-h-0">
                    <div className="sticky top-0 z-20 flex items-center gap-2 border-b border-dashed border-slate-200 bg-white/95 px-3 py-2.5 backdrop-blur lg:hidden">
                        <Link href={route('sessions.index')} aria-label="Back to the desk" className="flex size-9 shrink-0 items-center justify-center rounded-full text-slate-500"><ArrowLeft className="size-4" /></Link>
                        <button type="button" onClick={() => setDrawer('agenda')} className="flex min-w-0 flex-1 items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-left">
                            <ListOrdered className="size-4 shrink-0 text-slate-500" />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-[11px] font-black uppercase leading-tight">{session.title}</span>
                                <span className="block text-[9px] font-bold uppercase tracking-wider text-slate-400">{agenda ? 'Agenda' : 'People'} · {completed}/{speakers.length}{session.tracking ? ` · ${participants} in` : ''} · {clock(globalClock)}</span>
                            </span>
                        </button>
                        <button type="button" onClick={() => setDrawer('assist')} aria-label="Ventiq Assist" className="relative flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-white">
                            <PencilSparkles className="size-4" />
                            {(selected?.insights.length ?? 0) + carried.length > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#D4AF37] px-1 text-[9px] font-black text-slate-900 ring-2 ring-white">{(selected?.insights.length ?? 0) + carried.length}</span>
                            )}
                        </button>
                    </div>
                    <div className="pointer-events-none absolute inset-y-0 left-0 z-[5] hidden w-[18px] opacity-80 sm:block" style={{ backgroundImage: 'radial-gradient(circle at center, #fff 0 3px, #cbd5e1 3px 4px, transparent 4px)', backgroundSize: '100% 28px' }} />
                    <div className="pointer-events-none absolute -bottom-5 -left-2.5 select-none text-[130px] font-black uppercase italic tracking-[-4px] text-slate-900/[0.03]">Ventiq</div>

                    <header className="relative z-10 flex flex-wrap items-start justify-between gap-4 px-6 pb-4 pt-5 sm:pl-14 lg:pb-5 lg:pl-16 lg:pr-14 lg:pt-8">
                        <div className="min-w-0 flex-1">
                            <span className={cn(pill, paused || isLive ? 'bg-[#D4AF37]' : 'bg-slate-300')}>{paused ? (paused === 'auto' ? 'Away from page' : 'Paused') : isLive ? 'Capturing' : 'Ready'}</span>
                            <h1 className="mt-3 text-[26px] font-extrabold uppercase leading-none tracking-tight">{selected ? nameOf(selected) : agenda ? 'Add an agenda item' : isLive ? 'Add a presenter to begin' : 'Add a presenter'}</h1>
                            {selected && agenda && leadOf(selected) && <p className="mt-1.5 text-[15px] font-semibold text-slate-400">Led by {leadOf(selected)}</p>}
                            {isLive && live && !capturingHere && (
                                <button type="button" onClick={() => setSelectedId(live.id)} className={cn(pill, 'mt-3 bg-[#D4AF37] normal-case tracking-normal')}>
                                    Back to {nameOf(live)}, {agenda ? 'on now' : 'speaking now'} →
                                </button>
                            )}
                            {selected && !agenda && (
                                <input aria-label="Presentation name" value={selected.topic} onChange={(e) => setTopic(e.target.value)} placeholder="Untitled: click to name this presentation" className="mt-1.5 w-full border-b border-dashed border-transparent bg-transparent text-[15px] font-semibold text-slate-400 outline-none focus:border-slate-300 focus:text-slate-900" />
                            )}
                        </div>
                        <div className="flex shrink-0 items-center gap-3">
                            {selected && selected.breaks.length > 1 && (
                                <div className="flex items-center gap-2">
                                    <button type="button" aria-label="Previous page" disabled={selected.page === 0} onClick={() => update(selected.id, (s) => ({ page: s.page - 1 }))} className="text-slate-400 disabled:opacity-20"><ChevronLeft className="size-4" /></button>
                                    <span className={label}>Page {selected.page + 1} of {selected.breaks.length}</span>
                                    <button type="button" aria-label="Next page" disabled={onLatestPage} onClick={() => update(selected.id, (s) => ({ page: s.page + 1 }))} className="text-slate-400 disabled:opacity-20"><ChevronRight className="size-4" /></button>
                                </div>
                            )}
                            {capturingHere && (
                                <button type="button" disabled={paused === 'auto'} onClick={() => (paused ? resume() : pause('manual'))} className={cn(pill, 'transition hover:opacity-80', paused ? 'bg-slate-300' : 'bg-slate-900')}>
                                    {paused ? (paused === 'auto' ? 'Away' : 'Resume') : 'Pause'}
                                </button>
                            )}
                            {capturingHere && !paused && (
                                <button type="button" disabled={finishing} onClick={finishSegment} className={cn(pill, 'bg-slate-900 transition hover:opacity-80 disabled:opacity-50')}>
                                    {finishing ? (isLastSpeaker ? 'Finishing…' : 'Moving on…') : <>{isLastSpeaker ? (agenda ? 'Finish meeting' : 'Finish session') : agenda ? 'Next item' : 'Next speaker'} →</>}
                                </button>
                            )}
                        </div>
                    </header>
                    {finishFailed && (
                        <p role="alert" className="mx-4 mt-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-amber-50 px-4 py-3 text-[12px] font-bold text-amber-800">
                            That didn't get a reply. Your notes are saved.
                            <span className="flex gap-3">
                                <button type="button" onClick={finishSegment} className="underline">Try again</button>
                                <a href={route('sessions.report', session.id)} className="underline">Open the report</a>
                            </span>
                        </p>
                    )}

                    <div ref={notesRef} className={cn('relative z-10 flex-1 overflow-hidden px-6 pb-6 sm:pl-14 lg:pl-16 lg:pr-14', flipping ? 'animate-out fade-out slide-out-to-left-6 duration-200' : 'animate-in fade-in slide-in-from-right-6 duration-200')}>
                        <div className="max-w-2xl">
                            {!isLive && completed === 0 && !speakers.some((s) => s.logs.length) && (
                                <div className="pt-12">
                                    <p className="text-[16px] italic text-slate-400">{agenda ? 'The page is empty. Start the meeting and write as each item is discussed.' : 'The page is empty. Start the session and write as they speak.'}</p>
                                    <button type="button" onClick={start} className={cn(pill, 'mt-5 bg-emerald-500 px-5 py-2.5 text-[10px]')}>Begin capturing</button>
                                    {startError && (
                                        <p role="alert" className="mt-4 max-w-md text-[13px] font-semibold text-rose-600">
                                            {startError} <Link href={route('sessions.plan')} className="underline">Plan</Link>
                                        </p>
                                    )}
                                </div>
                            )}
                            {!isLive && speakers.length > 0 && completed === speakers.length && (
                                <div className="pt-12">
                                    <p className="text-[16px] italic text-slate-400">{agenda ? 'Every item has been taken.' : 'Every presenter has finished.'}</p>
                                    <Link href={route('sessions.report', session.id)} className={cn(pill, 'mt-5 bg-slate-900 px-5 py-2.5 text-[10px]')}>{agenda ? 'See the minutes →' : 'See the report →'}</Link>
                                </div>
                            )}

                            {visible.map((line, i) => (
                                <div key={`${selected?.page}-${i}`} onClick={() => canEdit(i) && editLastLine()} className={cn('group flex items-baseline gap-4 border-b border-dashed border-slate-200 py-3', canEdit(i) && 'cursor-pointer')}>
                                    <span className="w-14 shrink-0 text-[10px] font-black text-slate-300">{line.time.slice(0, 5)}</span>
                                    <p className="flex-1 whitespace-pre-wrap break-words text-[15px] leading-relaxed">{line.text}</p>
                                    {canEdit(i) && <span className="shrink-0 text-[9px] font-black uppercase tracking-widest text-[#D4AF37] opacity-0 transition group-hover:opacity-100">Edit</span>}
                                </div>
                            ))}

                            {capturingHere && !onLatestPage && (
                                <div className="flex items-center gap-3 py-3">
                                    <span className={cn(label, 'text-[#D4AF37]')}>Looking at an earlier page</span>
                                    <button type="button" onClick={() => update(selected!.id, (s) => ({ page: s.breaks.length - 1 }))} className={cn(label, 'underline')}>Back to the current page →</button>
                                </div>
                            )}
                            {capturingHere && onLatestPage && !paused && fill > 0.82 && <p className={cn(label, 'text-[#D4AF37]')}>Page filling up: the next line may turn the page</p>}
                            {capturingHere && onLatestPage && paused && (
                                <p className={cn(label, 'py-3')}>{paused === 'auto' ? 'Paused while this tab is in the background' : 'Paused: resume when ready'}</p>
                            )}
                            {capturingHere && onLatestPage && !paused && (
                                <div className="flex min-w-0 items-start gap-4 py-3">
                                    <span className="w-14 shrink-0 pt-[3px] text-[10px] font-black text-[#D4AF37]">{clock(live?.duration ?? 0)}</span>
                                    <textarea
                                        ref={draftRef}
                                        autoFocus
                                        rows={1}
                                        value={draft}
                                        onChange={(e) => setDraft(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' && !e.shiftKey) {
                                                e.preventDefault();
                                                commit();
                                            }
                                        }}
                                        placeholder="Keep typing: press Enter after each point"
                                        aria-label="Notes"
                                        className="block w-full min-w-0 flex-1 resize-none overflow-hidden whitespace-pre-wrap break-words border-none bg-transparent text-[15px] italic leading-relaxed text-slate-900 caret-[#D4AF37] outline-none"
                                    />
                                </div>
                            )}
                            {selected && !capturingHere && selected.logs.length > 0 && <p className={cn(label, 'pt-4')}>Finished: view only</p>}
                        </div>
                    </div>
                </main>

                {/* Right: Ventiq Assist */}
                <aside className={cn('fixed inset-y-0 right-0 z-40 flex w-[86%] max-w-[340px] flex-col bg-slate-50 shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:h-full lg:w-[300px] lg:max-w-none lg:translate-x-0 lg:border-l-2 lg:border-dashed lg:border-slate-200 lg:shadow-none', drawer === 'assist' ? 'translate-x-0' : 'translate-x-full')}>
                    <div className="flex w-full items-center justify-between gap-3 px-6 pb-4 pt-6">
                        <span>
                            <span className="block text-[12px] font-black uppercase">Ventiq Assist</span>
                            <span className={cn(label, 'mt-1 block')}>{{ thinking: 'Reading', following: 'Following along', listening: 'Listening', idle: 'Idle' }[aiState]}</span>
                        </span>
                        <span className="flex h-4 items-end gap-[2px]">
                            {[1, 2, 3, 4, 5].map((i) => (
                                <span key={i} className="w-[2.5px] animate-pulse rounded-sm bg-[#D4AF37]" style={{ height: aiState === 'idle' ? 3 : 4 + ((i * 5) % 11), animationDelay: `${i * 90}ms`, opacity: aiState === 'thinking' ? 1 : aiState === 'following' ? 0.65 : 0.25 }} />
                            ))}
                        </span>
                        <button type="button" aria-label="Close" onClick={() => setDrawer(null)} className="flex size-8 shrink-0 items-center justify-center rounded-full bg-black/5 text-slate-500 lg:hidden"><X className="size-4" /></button>
                    </div>
                    <div className="flex-1 overflow-y-auto px-5 pb-6 pt-2">
                        {fromLastTime}
                        {assist}
                    </div>
                </aside>
            </div>

            <ConfirmDialog open={!!removing} onOpenChange={(o) => !o && setRemoving(null)} title={`Remove ${removing ? nameOf(removing) : ''}?`} confirmLabel="Remove" onConfirm={() => removing && removePerson(removing)}>
                {agenda ? "Nothing's been noted under it, so no notes are lost." : "They haven't presented, so no notes are lost."}
            </ConfirmDialog>
        </div>
    );
}

function RosterRow({ s, name = s.name, mark, color, selected, onSelect, onRemove, below, trailing }: { s: Speaker; name?: string; mark?: string; color: string; selected: boolean; onSelect: () => void; onRemove?: () => void; below?: React.ReactNode; trailing?: React.ReactNode }) {
    return (
        <div onClick={onSelect} className={cn('group mb-1 flex cursor-pointer items-center gap-2.5 rounded-2xl px-2 py-2 transition', selected ? 'bg-white shadow-md' : 'hover:bg-white/70')}>
            <span className="relative shrink-0">
                {s.status === 'active' && <span className="absolute -inset-[3px] animate-ping rounded-full border-[1.5px] border-[#D4AF37]" />}
                <span className="flex size-8 items-center justify-center rounded-full text-[10px] font-black text-white shadow-sm ring-2 ring-white" style={{ background: color }}>{mark ?? initials(name)}</span>
            </span>
            <div className="min-w-0 flex-1">
                <p className={cn('truncate text-[11.5px] font-black uppercase', s.status === 'completed' && 'line-through opacity-40')}>{name}</p>
                {below}
            </div>
            {trailing}
            {onRemove && (
                <button type="button" aria-label={`Remove ${name}`} onClick={(e) => { e.stopPropagation(); onRemove(); }} className="flex size-6 shrink-0 items-center justify-center rounded-full bg-black/5 text-slate-400 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                    <X className="size-3" />
                </button>
            )}
        </div>
    );
}

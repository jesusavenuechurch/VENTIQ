import { router, usePoll } from '@inertiajs/react';
import { Check, ChevronDown, Download, LoaderCircle, Pencil, RotateCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import SessionsLayout, { quietButton } from '@/Layouts/SessionsLayout';
import { json } from '@/lib/http';
import { cn } from '@/lib/utils';

interface Props {
    session: { id: number; title: string; date: string; status: string; report: string | null; reviewed: string | null; tracking: boolean; failed: boolean };
    segments: { id: number; name: string; role: string | null; summary: string | null; logs: { time: string; text: string }[] }[];
}

/** A session's report: written by Ventiq Assist from the notes, editable, and the notes it came from. */
export default function Report({ session, segments }: Props) {
    const generating = session.status === 'completed' && !session.failed;
    const [editing, setEditing] = useState(false);
    const [text, setText] = useState(session.report ?? '');
    const [saved, setSaved] = useState(session.report ?? '');
    const [saving, setSaving] = useState(false);
    const [justSaved, setJustSaved] = useState(false);
    const [error, setError] = useState('');

    // While the report is being written, check every few seconds.
    const { start, stop } = usePoll(3000, { only: ['session', 'segments'] }, { autoStart: generating });
    useEffect(() => (generating ? start() : stop()), [generating, start, stop]);
    // The report arriving from the check above.
    useEffect(() => {
        if (editing) return;
        setText(session.report ?? '');
        setSaved(session.report ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [session.report]);

    async function save() {
        setSaving(true);
        setError('');
        const { ok } = await json(route('sessions.report.update', session.id), { method: 'PATCH', body: { session_report: text } }).catch(() => ({ ok: false }));
        setSaving(false);
        if (!ok) return setError("That didn't save. Check your connection and try again.");
        setSaved(text);
        setEditing(false);
        setJustSaved(true);
        window.setTimeout(() => setJustSaved(false), 2000);
    }

    return (
        <SessionsLayout title={session.title} subtitle={<>{session.date}{session.reviewed && <span className="text-emerald-600"> · Reviewed {session.reviewed}</span>}</>} crumbs={[['Reports', route('sessions.reports')], [session.title]]}>
            {session.status === 'draft' || session.status === 'active' ? (
                <Empty>This session is still going. Its report is written when the last presenter finishes.</Empty>
            ) : session.status !== 'reported' ? (
                session.failed ? (
                    <div className="rounded-[2rem] border border-dashed border-rose-200 bg-rose-50/40 py-16 text-center">
                        <p className="text-sm font-bold text-rose-600">The report couldn't be written</p>
                        <p className="mx-auto mt-1 max-w-md text-[12px] font-medium text-gray-500">The notes are safe, nothing was lost. Try again.</p>
                        <button type="button" onClick={() => router.post(route('sessions.report.generate', session.id))} className={cn(quietButton, 'mt-6')}><RotateCw className="size-3.5" />Try again</button>
                    </div>
                ) : (
                    <div className="rounded-[2rem] border border-dashed border-gray-200 py-16 text-center">
                        <LoaderCircle className="mx-auto mb-5 size-8 animate-spin text-action" />
                        <p className="text-sm font-bold text-gray-500">Writing your report…</p>
                        <p className="mt-1 text-[12px] font-medium text-gray-400">This usually takes a few seconds. You can leave this page; it'll be under Reports.</p>
                    </div>
                )
            ) : (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="rounded-2xl border border-gray-100 bg-white p-6 md:p-8 lg:col-span-2">
                        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <span className={cn('text-[10px] font-black uppercase tracking-widest', justSaved ? 'text-emerald-600' : 'text-gray-300')}>{justSaved ? 'Saved' : editing ? 'Editing' : ''}</span>
                            <div className="flex flex-wrap justify-end gap-2">
                                {editing ? (
                                    <>
                                        <button type="button" onClick={() => { setText(saved); setEditing(false); }} className={quietButton}>Cancel</button>
                                        <button type="button" disabled={saving} onClick={save} className={cn(quietButton, 'border-brand bg-brand text-white hover:bg-action')}>{saving ? 'Saving…' : 'Save'}</button>
                                    </>
                                ) : (
                                    <button type="button" onClick={() => setEditing(true)} className={quietButton}><Pencil className="size-3.5" />Edit</button>
                                )}
                                <a href={route('sessions.report.pdf', session.id)} className={quietButton}><Download className="size-3.5" />PDF</a>
                                {session.tracking && <a href={route('sessions.checkin.pdf', session.id)} className={quietButton}><Download className="size-3.5" />Attendance</a>}
                            </div>
                        </div>
                        {error && <p className="mb-3 text-[12px] font-bold text-rose-600">{error}</p>}
                        {editing ? (
                            <textarea aria-label="Report" value={text} onChange={(e) => setText(e.target.value)} rows={20} className="w-full resize-y rounded-2xl border-none bg-gray-50 px-5 py-4 text-[13px] font-medium leading-relaxed text-gray-700 outline-none focus:ring-2 focus:ring-action/20" />
                        ) : (
                            <div className="whitespace-pre-wrap text-[13px] font-medium leading-relaxed text-gray-700">{saved}</div>
                        )}

                        <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-6">
                            {session.reviewed ? (
                                <span className="flex items-center gap-1 text-[11px] font-black uppercase tracking-widest text-emerald-600"><Check className="size-4" />Reviewed {session.reviewed}</span>
                            ) : (
                                <>
                                    <span className="text-[12px] font-medium text-gray-400">Marking it reviewed lets attendees know the notes are ready.</span>
                                    <button type="button" onClick={() => router.post(route('sessions.report.review', session.id), {}, { preserveScroll: true })} className="rounded-2xl bg-action px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:shadow-lg">Mark as reviewed</button>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="rounded-2xl bg-[#F7F3EC] p-5">
                        <p className="mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Notes it was written from</p>
                        <div className="space-y-3">
                            {segments.map((s) => (
                                <details key={s.id} className="group overflow-hidden rounded-xl border border-gray-100 bg-white">
                                    <summary className="flex cursor-pointer list-none items-center justify-between px-4 py-3">
                                        <span>
                                            <span className="block text-[12px] font-bold text-brand">{s.name}</span>
                                            {s.role && <span className="block text-[10px] font-bold text-gray-400">{s.role}</span>}
                                        </span>
                                        <ChevronDown className="size-4 text-gray-300 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <div className="px-4 pb-4">
                                        {s.summary && (
                                            <>
                                                <p className="mb-1 mt-2 text-[9px] font-black uppercase tracking-wide text-gray-400">Summary</p>
                                                <p className="mb-3 text-[12px] font-medium text-gray-600">{s.summary}</p>
                                            </>
                                        )}
                                        {s.logs.length > 0 && (
                                            <>
                                                <p className="mb-1 text-[9px] font-black uppercase tracking-wide text-gray-400">As typed</p>
                                                <div className="max-h-48 space-y-1 overflow-y-auto pr-1">
                                                    {s.logs.map((l, i) => <p key={i} className="text-[11px] text-gray-500"><span className="font-mono text-gray-300">{l.time}</span> {l.text}</p>)}
                                                </div>
                                            </>
                                        )}
                                        {!s.summary && s.logs.length === 0 && <p className="mt-2 text-[11px] italic text-gray-300">Nothing written for this one.</p>}
                                    </div>
                                </details>
                            ))}
                            {segments.length === 0 && <p className="text-[11px] italic text-gray-400">No one was added to this session.</p>}
                        </div>
                    </div>
                </div>
            )}
        </SessionsLayout>
    );
}

function Empty({ children }: { children: React.ReactNode }) {
    return <div className="rounded-[2rem] border border-dashed border-gray-200 py-16 text-center text-sm font-bold text-gray-400">{children}</div>;
}

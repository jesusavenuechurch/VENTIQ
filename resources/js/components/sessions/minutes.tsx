import { Link, router } from '@inertiajs/react';
import { Check, Download, Pencil } from 'lucide-react';
import { useState } from 'react';
import { quietButton } from '@/Layouts/SessionsLayout';
import type { ActionPointCard } from '@/components/sessions/action-points';
import { cn } from '@/lib/utils';

export interface MinutesData {
    heading: { organization: string | null; title: string; type: string; date: string; time: string | null; venue: string | null; chair: string | null; secretary: string | null };
    present: string[];
    apologies: string[];
    previous: { id: number; date: string; confirmed: boolean } | null;
    arising: ActionPointCard[];
    items: { id: number; title: string; lead: string | null; discussion: string | null; decisions: string[]; noted: boolean }[];
    actions: ActionPointCard[];
    aob: string | null;
    next: string | null;
    confirmed: { by: string | null; date: string } | null;
}

const heading = 'text-[11px] font-black uppercase tracking-[0.15em] text-brand';
const body = 'text-[13px] font-medium leading-relaxed text-gray-700';
const none = 'text-[13px] italic text-gray-400';

/**
 * A meeting's minutes, section by section in the usual order. Each part
 * that can be changed has its own pencil; what Ventiq Assist wrote is
 * kept unless someone changes it. Confirming them is reviewing the report.
 */
export function Minutes({ sessionId, minutes, canEdit, tracking }: { sessionId: number; minutes: MinutesData; canEdit: boolean; tracking: boolean }) {
    const h = minutes.heading;
    const save = (payload: Record<string, string | Record<number, Record<string, string>>>, done: () => void, failed: () => void) =>
        router.patch(route('sessions.minutes.update', sessionId), payload, { preserveScroll: true, preserveState: true, only: ['minutes', 'flash', 'errors'], onSuccess: done, onError: failed });
    let n = 0;
    const num = () => ++n;

    return (
        <div className="rounded-2xl border border-gray-100 bg-white p-6 md:p-10">
            <div className="mb-8 flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    {h.organization && <p className="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400">{h.organization}</p>}
                    <h2 className="mt-1 text-xl font-black tracking-tight text-brand">Minutes: {h.title}</h2>
                    <p className="mt-1 text-[13px] font-medium text-gray-500">
                        {h.type} held on {h.date}{h.time && ` at ${h.time}`}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a href={route('sessions.report.pdf', sessionId)} className={quietButton}><Download className="size-3.5" />PDF</a>
                    {tracking && <a href={route('sessions.checkin.pdf', sessionId)} className={quietButton}><Download className="size-3.5" />Attendance</a>}
                </div>
            </div>

            <dl className="mb-8 grid grid-cols-1 gap-x-8 gap-y-3 rounded-2xl bg-gray-50 px-5 py-4 sm:grid-cols-3">
                <Fact label="Chair" value={h.chair} />
                <Fact label="Secretary" value={h.secretary} />
                <Editable canEdit={canEdit} label="Venue" value={h.venue ?? ''} rows={1} onSave={(v, done, failed) => save({ venue: v }, done, failed)} compact>
                    <Fact label="Venue" value={h.venue} />
                </Editable>
            </dl>

            <div className="space-y-8">
                <Section n={num()} title="Present">
                    <Editable canEdit={canEdit} label="Present" hint="One name per line." value={minutes.present.join('\n')} onSave={(v, done, failed) => save({ present: v }, done, failed)}>
                        {minutes.present.length ? <p className={body}>{minutes.present.join(', ')}</p> : <p className={none}>Not recorded.</p>}
                    </Editable>
                </Section>

                <Section n={num()} title="Apologies">
                    <Editable canEdit={canEdit} label="Apologies" hint="One name per line." value={minutes.apologies.join('\n')} onSave={(v, done, failed) => save({ apologies: v }, done, failed)}>
                        {minutes.apologies.length ? <p className={body}>{minutes.apologies.join(', ')}</p> : <p className={body}>None.</p>}
                    </Editable>
                </Section>

                {minutes.previous && (
                    <Section n={num()} title="Minutes of the previous meeting">
                        <p className={body}>
                            The minutes of the meeting of <Link href={route('sessions.report', minutes.previous.id)} className="font-bold text-brand underline">{minutes.previous.date}</Link>{' '}
                            {minutes.previous.confirmed ? 'were confirmed.' : 'have not yet been confirmed.'}
                        </p>
                    </Section>
                )}

                {minutes.arising.length > 0 && (
                    <Section n={num()} title="Matters arising">
                        <ul className="space-y-2">
                            {minutes.arising.map((a) => (
                                <li key={a.id} className="flex items-start gap-3">
                                    <span className={cn('mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-widest', a.status === 'done' ? 'bg-mint text-mint-ink' : a.overdue ? 'bg-rose-50 text-rose-600' : 'bg-gray-100 text-gray-500')}>{a.status === 'done' ? 'Done' : a.overdue ? 'Overdue' : 'Open'}</span>
                                    <span className={body}>
                                        {a.task}
                                        <span className="text-[11px] text-gray-400"> · {[a.owner, a.dueLabel && `due ${a.dueLabel}`, a.session?.date && `from ${a.session.date}`].filter(Boolean).join(' · ')}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                {minutes.items.map((item) => (
                    <Section key={item.id} n={num()} title={item.title} sub={item.lead ? `Led by ${item.lead}` : undefined}>
                        <Editable canEdit={canEdit} label={`Discussion: ${item.title}`} value={item.discussion ?? ''} rows={5} onSave={(v, done, failed) => save({ items: { [item.id]: { discussion: v } } }, done, failed)}>
                            {item.discussion ? <p className={cn(body, 'whitespace-pre-wrap')}>{item.discussion}</p> : <p className={none}>{item.noted ? 'No discussion recorded.' : 'Nothing was noted for this item.'}</p>}
                        </Editable>
                        <div className="mt-3">
                            <Editable canEdit={canEdit} label={`Decisions: ${item.title}`} hint="One decision per line." value={item.decisions.join('\n')} rows={3} onSave={(v, done, failed) => save({ items: { [item.id]: { decisions: v } } }, done, failed)}>
                                {item.decisions.length > 0 ? (
                                    <>
                                        <p className="mb-1 text-[10px] font-black uppercase tracking-widest text-mint-ink">Resolved</p>
                                        <ul className="list-disc space-y-1 pl-5">{item.decisions.map((d, i) => <li key={i} className={body}>{d}</li>)}</ul>
                                    </>
                                ) : (
                                    canEdit && <p className="text-[11px] text-gray-300">No decisions.</p>
                                )}
                            </Editable>
                        </div>
                    </Section>
                ))}

                <Section n={num()} title="Action points">
                    {minutes.actions.length > 0 ? (
                        <ul className="space-y-1.5">
                            {minutes.actions.map((a) => (
                                <li key={a.id} className={cn(body, a.status === 'proposed' && 'text-lilac-ink')}>
                                    {a.task}
                                    <span className="text-[11px] text-gray-400"> · {a.owner ?? 'No one yet'}{a.dueLabel && ` · by ${a.dueLabel}`}{a.status === 'proposed' && ' · suggested'}</span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className={body}>None.</p>
                    )}
                    {canEdit && <p className="mt-2 text-[11px] text-gray-400">Change them, and who has each, in Action points below.</p>}
                </Section>

                <Section n={num()} title="Any other business">
                    <Editable canEdit={canEdit} label="Any other business" value={minutes.aob ?? ''} rows={4} onSave={(v, done, failed) => save({ aob: v }, done, failed)}>
                        <p className={cn(body, 'whitespace-pre-wrap')}>{minutes.aob ?? 'None.'}</p>
                    </Editable>
                </Section>

                <Section n={num()} title="Next meeting">
                    <Editable canEdit={canEdit} label="Next meeting" value={minutes.next ?? ''} rows={1} onSave={(v, done, failed) => save({ next: v }, done, failed)}>
                        <p className={body}>{minutes.next ?? 'To be confirmed.'}</p>
                    </Editable>
                </Section>
            </div>

            <div className="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-6">
                {minutes.confirmed ? (
                    <span className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest text-emerald-600"><Check className="size-4" />Confirmed{minutes.confirmed.by && ` by ${minutes.confirmed.by}`}, {minutes.confirmed.date}</span>
                ) : !canEdit ? (
                    <span className="text-[12px] font-medium text-gray-400">Draft: not confirmed yet.</span>
                ) : (
                    <>
                        <span className="max-w-md text-[12px] font-medium text-gray-400">Draft. Confirming keeps the suggested action points, signs the minutes off in your name and lets attendees know.</span>
                        <button type="button" onClick={() => router.post(route('sessions.report.review', sessionId), {}, { preserveScroll: true })} className="rounded-2xl bg-action px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:shadow-lg">Confirm the minutes</button>
                    </>
                )}
            </div>
        </div>
    );
}

function Fact({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-[9px] font-black uppercase tracking-widest text-gray-400">{label}</dt>
            <dd className={cn('text-[13px] font-bold', value ? 'text-brand' : 'font-medium italic text-gray-300')}>{value || 'Not set'}</dd>
        </div>
    );
}

function Section({ n, title, sub, children }: { n: number; title: string; sub?: string; children: React.ReactNode }) {
    return (
        <section>
            <h3 className={cn(heading, 'flex items-baseline gap-2 border-b border-gray-100 pb-2')}>
                <span className="text-gray-300">{n}.</span>
                <span className="min-w-0">{title}</span>
            </h3>
            {sub && <p className="mt-1 text-[11px] font-bold text-gray-400">{sub}</p>}
            <div className="mt-3">{children}</div>
        </section>
    );
}

/** A part of the minutes with a pencil to change it in place. */
function Editable({ canEdit, label, hint, value, rows = 2, compact, onSave, children }: { canEdit: boolean; label: string; hint?: string; value: string; rows?: number; compact?: boolean; onSave: (value: string, done: () => void, failed: () => void) => void; children: React.ReactNode }) {
    const [editing, setEditing] = useState(false);
    const [text, setText] = useState(value);
    const [saving, setSaving] = useState(false);

    if (!canEdit) return <>{children}</>;
    if (!editing) {
        return (
            <div className="group relative pr-9">
                {children}
                <button type="button" aria-label={`Change ${label.toLowerCase()}`} onClick={() => { setText(value); setEditing(true); }} className={cn('absolute right-0 rounded-full p-2 text-gray-300 transition hover:bg-gray-50 hover:text-brand sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100', compact ? 'top-0' : '-top-1')}>
                    <Pencil className="size-3.5" />
                </button>
            </div>
        );
    }
    const done = () => {
        setSaving(false);
        setEditing(false);
    };

    return (
        <form
            className={cn('space-y-2', compact && 'sm:col-span-1')}
            onSubmit={(e) => {
                e.preventDefault();
                setSaving(true);
                onSave(text, done, () => setSaving(false));
            }}
        >
            {rows === 1 ? (
                <input autoFocus aria-label={label} value={text} onChange={(e) => setText(e.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-[13px] font-medium text-gray-700 outline-none focus:border-action focus:ring-2 focus:ring-action/20" />
            ) : (
                <textarea autoFocus aria-label={label} rows={rows} value={text} onChange={(e) => setText(e.target.value)} className="w-full resize-y rounded-xl border border-gray-200 bg-white px-4 py-3 text-[13px] font-medium leading-relaxed text-gray-700 outline-none focus:border-action focus:ring-2 focus:ring-action/20" />
            )}
            <div className="flex flex-wrap items-center gap-2">
                <button disabled={saving} className="rounded-full bg-brand px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action disabled:opacity-50">{saving ? 'Saving…' : 'Save'}</button>
                <button type="button" onClick={() => setEditing(false)} className="rounded-full px-3 py-2 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">Cancel</button>
                {hint && <span className="text-[11px] text-gray-400">{hint}</span>}
            </div>
        </form>
    );
}

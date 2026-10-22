import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, ChevronRight, CornerDownLeft, Pencil, Plus, Repeat, X } from 'lucide-react';
import { useState } from 'react';
import SessionsLayout, { field, fieldLabel, quietButton } from '@/Layouts/SessionsLayout';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { SessionType } from '@/components/sessions/schedule-dialog';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { SessionsArea } from '@/types';

interface Person {
    name: string;
    title?: string | null;
    role: string | null;
    presenting: boolean;
}

interface Meeting {
    id: number;
    date: string;
    status: string;
    reviewed: boolean;
}

interface Props {
    series: {
        id: number;
        title: string;
        type: string;
        typeLabel: string;
        custom_type_label: string | null;
        rule: string;
        start_time: string;
        expected_duration: number | null;
        expected_participants: number | null;
        tracking: boolean;
        ends_on: string | null;
        endsLabel: string | null;
        ended: boolean;
        people: Person[];
        chair: string;
        secretary: string;
        location: string;
    };
    upcoming: Meeting[];
    past: Meeting[];
    types: SessionType[];
}

const STATUS: Record<string, [string, string]> = {
    draft: ['Missed', 'bg-gray-50 text-gray-400'],
    active: ['Live', 'bg-amber-50 text-amber-600'],
    completed: ['Report on its way', 'bg-blue-50 text-blue-500'],
    reported: ['Report ready', 'bg-emerald-50 text-emerald-600'],
};

/** A repeating session: its rule, the meetings ahead (skip one), the ones held, and changing or ending it. */
export default function Series({ series, upcoming, past, types }: Props) {
    const { can } = usePage<{ sessionsArea: SessionsArea }>().props.sessionsArea;
    const [editing, setEditing] = useState(false);
    const [ending, setEnding] = useState(false);
    const [skipping, setSkipping] = useState<Meeting | null>(null);

    return (
        <SessionsLayout
            title={series.title}
            subtitle={
                <span className="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
                    <Repeat className="size-3.5 text-action" />
                    {series.ended ? 'Ended' : series.rule}
                    {!series.ended && series.endsLabel && <span>· until {series.endsLabel}</span>}
                    <span>· {series.typeLabel}</span>
                </span>
            }
            crumbs={[['Desk', route('sessions.index')], [series.title]]}
            width="max-w-4xl"
            actions={
                can.run && !series.ended ? (
                    <>
                        <button type="button" onClick={() => setEditing(true)} className={quietButton}><Pencil className="size-3.5" />Change</button>
                        <button type="button" onClick={() => setEnding(true)} className={cn(quietButton, 'text-rose-600 hover:border-rose-300')}>End the series</button>
                    </>
                ) : null
            }
        >
            {!series.ended && (
                <>
                    <h2 className="mb-3 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Coming up</h2>
                    <div className="mb-10 space-y-2">
                        {upcoming.map((m, i) => (
                            <div key={m.id} className="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white px-5 py-4">
                                <span className="flex min-w-0 items-center gap-3">
                                    <CalendarClock className={cn('size-4 shrink-0', i === 0 ? 'text-action' : 'text-gray-300')} />
                                    <span className="min-w-0">
                                        <span className="block text-[13px] font-bold text-brand">{m.date}</span>
                                        {i === 0 && <span className="block text-[11px] text-gray-400">Next meeting</span>}
                                    </span>
                                </span>
                                <span className="flex shrink-0 items-center gap-3">
                                    {can.run && <button type="button" onClick={() => setSkipping(m)} className="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600">Skip</button>}
                                    {can.run && <Link href={route('sessions.show', m.id)} className="text-[10px] font-black uppercase tracking-widest text-brand hover:text-action">Open</Link>}
                                </span>
                            </div>
                        ))}
                        {upcoming.length === 0 && <p className="text-[12px] italic text-gray-400">No more meetings: the series reaches its end date.</p>}
                    </div>
                </>
            )}

            <h2 className="mb-3 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Held</h2>
            <div className="space-y-2">
                {past.map((m) => {
                    const [label, tone] = STATUS[m.status] ?? [m.status, 'bg-gray-50 text-gray-400'];
                    const href = m.status === 'reported' ? route('sessions.report', m.id) : can.run && m.status !== 'draft' ? route('sessions.show', m.id) : null;
                    const Row = href ? Link : 'div';
                    return (
                        <Row key={m.id} href={href ?? ''} className="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white px-5 py-4 transition-all hover:border-brand/30">
                            <span className="text-[13px] font-bold text-brand">{m.date}</span>
                            <span className="flex items-center gap-2">
                                <span className={cn('rounded-full px-3 py-1.5 text-[9px] font-black uppercase tracking-widest', tone)}>{m.reviewed ? 'Reviewed' : label}</span>
                                {href && <ChevronRight className="size-4 text-gray-300" />}
                            </span>
                        </Row>
                    );
                })}
                {past.length === 0 && <p className="text-[12px] italic text-gray-400">None yet. Each meeting's report will be here.</p>}
            </div>

            {editing && <EditSeries series={series} types={types} onClose={() => setEditing(false)} />}

            <ConfirmDialog open={ending} onOpenChange={setEnding} title={`End "${series.title}"?`} confirmLabel="End the series" onConfirm={() => router.post(route('sessions.series.end', series.id), {}, { onFinish: () => setEnding(false) })}>
                The meetings coming up are removed. The ones already held stay, with their reports.
            </ConfirmDialog>
            <ConfirmDialog open={!!skipping} onOpenChange={(o) => !o && setSkipping(null)} title={`Skip the meeting on ${skipping?.date}?`} confirmLabel="Skip it" onConfirm={() => skipping && router.post(route('sessions.skip', skipping.id), {}, { preserveScroll: true, onFinish: () => setSkipping(null) })}>
                Only this one. The series carries on, and another meeting is added at the end.
            </ConfirmDialog>
        </SessionsLayout>
    );
}

/** The series' details and people, for every meeting not held yet. */
function EditSeries({ series, types, onClose }: { series: Props['series']; types: SessionType[]; onClose: () => void }) {
    const form = useForm({
        title: series.title,
        type: series.type,
        custom_type_label: series.custom_type_label ?? '',
        start_time: series.start_time,
        expected_duration: series.expected_duration?.toString() ?? '',
        expected_participants: series.expected_participants?.toString() ?? '',
        ends_on: series.ends_on ?? '',
        people: series.people.map((p) => ({ name: p.name, title: p.title ?? '', role: p.role ?? '', presenting: p.presenting })),
        chair: series.chair,
        secretary: series.secretary,
        location: series.location,
    });
    const [name, setName] = useState('');
    const [lead, setLead] = useState('');
    const roles = types.find((t) => t.key === form.data.type)?.roles ?? [];
    // A meeting: agenda items (and who leads each), a chair and a secretary.
    const agenda = !!types.find((t) => t.key === form.data.type)?.minutes;
    const typed = () => (!name.trim() ? [] : agenda ? [{ name: lead.trim(), title: name.trim(), role: '', presenting: true }] : [{ name: name.trim(), title: '', role: '', presenting: true }]);
    const add = () => {
        if (!name.trim()) return;
        form.setData('people', [...form.data.people, ...typed()]);
        setName('');
        setLead('');
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent aria-describedby="edit-series-help" className="max-h-[92dvh] max-w-2xl overflow-y-auto max-sm:bottom-0 max-sm:top-auto max-sm:w-full max-sm:max-w-none max-sm:translate-y-0 max-sm:rounded-b-none max-sm:rounded-t-[2rem]">
                <form
                    className="space-y-5 p-6 sm:p-8"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const pending = typed();
                        form.transform((d) => ({ ...d, people: [...d.people, ...pending] }));
                        form.put(route('sessions.series.update', series.id), { preserveScroll: true, onSuccess: onClose });
                    }}
                >
                    <div>
                        <DialogTitle className="pr-10 text-2xl normal-case not-italic tracking-tight">Change the series</DialogTitle>
                        <DialogDescription id="edit-series-help" className="mt-1 text-[13px] font-medium normal-case tracking-normal text-gray-500">For every meeting not held yet. {series.rule}.</DialogDescription>
                    </div>
                    {Object.values(form.errors)[0] && <p role="alert" className="rounded-2xl bg-rose-50 p-3 text-[12px] font-bold text-rose-700">{Object.values(form.errors)[0]}</p>}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <label htmlFor="s-title" className={fieldLabel}>Name</label>
                            <input id="s-title" required value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label htmlFor="s-type" className={fieldLabel}>Type</label>
                            <NativeSelect id="s-type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className="border-gray-200 bg-white py-4 font-bold text-brand">
                                {types.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                            </NativeSelect>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div>
                            <label htmlFor="s-time" className={fieldLabel}>Start time</label>
                            <input id="s-time" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label htmlFor="s-length" className={fieldLabel}>Length (minutes)</label>
                            <input id="s-length" type="number" min={1} value={form.data.expected_duration} onChange={(e) => form.setData('expected_duration', e.target.value)} placeholder="Optional" className={field} />
                        </div>
                        <div className="col-span-2 sm:col-span-1">
                            <label htmlFor="s-ends" className={fieldLabel}>Until</label>
                            <input id="s-ends" type="date" value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} className={field} />
                        </div>
                    </div>
                    {agenda && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label htmlFor="s-chair" className={fieldLabel}>Chair</label>
                                <input id="s-chair" value={form.data.chair} onChange={(e) => form.setData('chair', e.target.value)} className={field} />
                            </div>
                            <div>
                                <label htmlFor="s-secretary" className={fieldLabel}>Secretary</label>
                                <input id="s-secretary" value={form.data.secretary} onChange={(e) => form.setData('secretary', e.target.value)} className={field} />
                            </div>
                            <div>
                                <label htmlFor="s-location" className={fieldLabel}>Venue</label>
                                <input id="s-location" value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} className={field} />
                            </div>
                        </div>
                    )}
                    <div>
                        <p className={fieldLabel}>{agenda ? 'Standing agenda' : 'People at every meeting'}</p>
                        <div className="flex gap-2">
                            <input aria-label={agenda ? 'Agenda item' : 'Name'} value={name} onChange={(e) => setName(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), add())} placeholder={agenda ? 'Item, then Enter' : 'Name, then Enter'} className={field} />
                            {agenda && <input aria-label="Led by" value={lead} onChange={(e) => setLead(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), add())} placeholder="Led by (optional)" className={cn(field, 'w-44 min-w-0')} />}
                            <button type="button" aria-label={agenda ? 'Add item' : 'Add person'} onClick={add} className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-brand text-white hover:bg-action"><Plus className="size-4" /></button>
                        </div>
                        <p className="ml-1 mt-1 flex items-center gap-1 text-[11px] text-gray-400"><CornerDownLeft className="size-3" />{agenda ? 'Press Enter after each item. Every meeting starts with these; add more on the day.' : 'Press Enter after each name.'}</p>
                        {form.data.people.length > 0 && (
                            <ol className="mt-3 divide-y divide-gray-50 rounded-2xl border border-gray-100">
                                {form.data.people.map((p, i) => (
                                    <li key={i} className="flex items-center justify-between gap-3 px-4 py-2.5">
                                        <span className="min-w-0 truncate text-[13px] font-bold text-brand">{i + 1}. {agenda ? p.title || p.name : p.name}{agenda && p.title && p.name && <span className="font-medium text-gray-400"> · led by {p.name}</span>}</span>
                                        <span className="flex items-center gap-2">
                                            {!agenda && roles.length > 0 && (
                                                <NativeSelect aria-label={`Role for ${p.name}`} value={p.role} onChange={(e) => form.setData('people', form.data.people.map((x, j) => (j === i ? { ...x, role: e.target.value, presenting: roles.find((r) => r.value === e.target.value)?.presenting ?? x.presenting } : x)))} className="w-36 border-gray-200 bg-white py-2 text-[12px]">
                                                    <option value="">Role…</option>
                                                    {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                                                </NativeSelect>
                                            )}
                                            <button type="button" aria-label={`Remove ${p.name}`} onClick={() => form.setData('people', form.data.people.filter((_, j) => j !== i))} className="text-gray-300 hover:text-rose-500"><X className="size-4" /></button>
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </div>
                    <button disabled={form.processing} className="w-full rounded-2xl bg-brand py-4 text-[11px] font-black uppercase tracking-[0.3em] text-white hover:bg-action disabled:opacity-50">Save for the meetings ahead</button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

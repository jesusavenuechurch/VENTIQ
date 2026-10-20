import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CornerDownLeft, FolderOpen, LoaderCircle, Plus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { field, fieldLabel } from '@/Layouts/SessionsLayout';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { clearDraft, readDraft, saveDraft } from '@/lib/session-draft';
import { cn } from '@/lib/utils';
import type { SessionsArea } from '@/types';

interface Role {
    value: string;
    label: string;
    presenting: boolean;
}

export interface SessionType {
    key: string;
    label: string;
    /** A meeting: agenda items, a chair and a secretary, and minutes. */
    minutes: boolean;
    roles: Role[];
}

interface Props {
    types: SessionType[];
    /** Scheduling into a programme (from its page). */
    programme: { id: number; name: string } | null;
    today: string;
}

interface Person {
    name: string;
    /** A meeting's agenda item (name is then who leads it). */
    title?: string;
    role: string;
    presenting: boolean;
}

type Data = {
    title: string;
    type: string;
    custom_type_label: string;
    date: string;
    start_time: string;
    people: Person[];
    expected_duration: string;
    track_participants: boolean;
    expected_participants: string;
    programme_id: number | null;
    repeat: string;
    ends_on: string;
    chair: string;
    secretary: string;
    location: string;
};

const emptyPerson: Person = { name: '', title: '', role: '', presenting: true };

const ordinal = (n: number) => n + (n % 100 >= 11 && n % 100 <= 13 ? 'th' : ['th', 'st', 'nd', 'rd'][n % 10] ?? 'th');

/** How the session can repeat, in words for its first date (as SessionSeries::describe()). */
function repeatOptions(date: string): [string, string][] {
    const d = date ? new Date(`${date}T12:00:00`) : new Date();
    const day = d.toLocaleDateString('en', { weekday: 'long' });
    const nth = Math.ceil(d.getDate() / 7);
    return [
        ['', "Doesn't repeat"],
        ['weekly', `Every ${day}`],
        ['fortnightly', `Every 2 weeks on ${day}`],
        ['monthly_date', `Monthly on the ${ordinal(d.getDate())}`],
        ['monthly_weekday', `Monthly on the ${nth >= 5 ? 'last' : ordinal(nth)} ${day}`],
    ];
}

/**
 * Schedule a session, over the desk or a programme's page: what it is,
 * then who's leading it. Closing it keeps what's been typed (the desk
 * offers to finish it); it's cleared once the session is made.
 */
export function ScheduleSessionDialog({ open, onOpenChange, ...props }: Props & { open: boolean; onOpenChange: (open: boolean) => void }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                aria-describedby="schedule-session-help"
                className="max-h-[92dvh] max-w-2xl overflow-y-auto max-sm:bottom-0 max-sm:top-auto max-sm:w-full max-sm:max-w-none max-sm:translate-y-0 max-sm:rounded-b-none max-sm:rounded-t-[2rem]"
            >
                <div className="p-6 sm:p-8">
                    <DialogTitle className="pr-10 text-2xl normal-case not-italic tracking-tight">Schedule a session</DialogTitle>
                    <DialogDescription id="schedule-session-help" className="mt-1 text-[13px] font-medium normal-case tracking-normal text-gray-500">Start it live today, or line it up for later.</DialogDescription>
                    <div className="mt-6">
                        <ScheduleForm {...props} onDone={() => onOpenChange(false)} />
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function ScheduleForm({ types, programme, today, onDone }: Props & { onDone: () => void }) {
    // A draft for this same programme (or none) comes back as it was left.
    const draft = useRef((() => {
        const d = readDraft<Data>();
        return d && (d.data.programme_id ?? null) === (programme?.id ?? null) ? d : null;
    })()).current;
    const { sessionsArea } = usePage<{ sessionsArea: SessionsArea }>().props;
    const blank: Data = {
        title: '',
        type: types[0]?.key ?? 'presentation',
        custom_type_label: '',
        date: today,
        start_time: '',
        people: [],
        expected_duration: '',
        track_participants: false,
        expected_participants: '',
        programme_id: programme?.id ?? null,
        repeat: '',
        ends_on: '',
        chair: '',
        secretary: '',
        location: '',
    };
    // An older draft may not have every field yet.
    const form = useForm<Data>(draft ? { ...blank, ...draft.data } : blank);
    const [step, setStep] = useState<1 | 2>(draft?.step ?? 1);
    const [person, setPerson] = useState<Person>(draft?.person ?? emptyPerson);
    const nameRef = useRef<HTMLInputElement>(null);
    const roles = types.find((t) => t.key === form.data.type)?.roles ?? [];
    const agenda = !!types.find((t) => t.key === form.data.type)?.minutes;
    const startsToday = form.data.date === today || !form.data.date;

    useEffect(() => {
        saveDraft({ data: form.data, step, person });
    }, [form.data, step, person]);
    useEffect(() => {
        if (step === 2) nameRef.current?.focus();
    }, [step]);

    // A person, or for a meeting an agenda item (and who leads it).
    const typed = (p: Person): Person | null => {
        if (agenda) {
            const title = (p.title ?? '').trim();
            return title ? { name: p.name.trim(), title, role: '', presenting: true } : null;
        }
        const name = p.name.trim();
        return name ? { ...p, name, title: '' } : null;
    };
    const addPerson = () => {
        const next = typed(person);
        if (!next) return;
        form.setData('people', [...form.data.people, next]);
        setPerson({ ...emptyPerson, role: person.role, presenting: person.presenting });
        nameRef.current?.focus();
    };
    const pickRole = (value: string) => {
        const role = roles.find((r) => r.value === value);
        setPerson((p) => ({ ...p, role: value, presenting: role ? role.presenting : p.presenting }));
    };

    const submit = () => {
        // A name still in the box counts: it would be lost otherwise.
        const pending = typed(person);
        form.transform((d) => ({ ...d, people: [...d.people, ...(pending ? [pending] : [])], ...(agenda ? {} : { chair: '', secretary: '' }), custom_type_label: d.type === 'custom' ? d.custom_type_label : '', repeat: programme ? '' : d.repeat, ends_on: d.repeat ? d.ends_on : '' }));
        form.post(route('sessions.store'), {
            preserveScroll: true,
            onSuccess: () => {
                clearDraft();
                onDone();
            },
            onError: (errors) => (errors.title || (errors as Record<string, string>).ends_on) && setStep(1),
        });
    };

    const steps = ['Details', agenda ? 'Agenda' : 'People'];
    const [restored, setRestored] = useState(!!draft && !!draft.data.title);
    const startAgain = () => {
        form.setData(blank);
        form.clearErrors();
        clearDraft();
        setPerson(emptyPerson);
        setStep(1);
        setRestored(false);
    };

    return (
        <>
            {programme && (
                <p className="mb-6 flex items-center gap-2 rounded-2xl bg-mint/40 p-4 text-[12px] font-bold text-mint-ink"><FolderOpen className="size-4" />Part of {programme.name}</p>
            )}

            {sessionsArea.quota.remaining <= 0 && (
                <p className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-[12px] font-bold text-amber-800">
                    You've used this month's sessions. You can still schedule; add more before the next one starts.
                    {sessionsArea.can.plan && <Link href={route('sessions.plan')} className="shrink-0 rounded-full bg-amber-500 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white">Add sessions</Link>}
                </p>
            )}

            {(form.errors as Record<string, string>).quota && <p role="alert" className="mb-6 rounded-2xl bg-rose-50 p-4 text-[12px] font-bold text-rose-700">{(form.errors as Record<string, string>).quota}</p>}

            {restored && (
                <p className="mb-6 flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-gray-50 px-4 py-3 text-[12px] font-medium text-gray-500">
                    Picking up where you left off.
                    <button type="button" onClick={startAgain} className="font-bold text-brand underline">Start again</button>
                </p>
            )}

            <ol className="mb-8 flex items-center gap-3">
                {steps.map((label, i) => {
                    const n = (i + 1) as 1 | 2;
                    return (
                        <li key={label} className="flex items-center gap-3">
                            {i > 0 && <span className="h-px w-8 bg-gray-200" />}
                            <button type="button" disabled={n === 2 && !form.data.title.trim()} onClick={() => setStep(n)} className={cn('flex items-center gap-2 text-[11px] font-black uppercase tracking-widest disabled:cursor-not-allowed', step === n ? 'text-brand' : 'text-gray-400 hover:text-brand')}>
                                <span className={cn('flex size-6 items-center justify-center rounded-full text-[11px]', step === n ? 'bg-brand text-white' : 'bg-gray-100 text-gray-400')}>{n}</span>
                                {label}
                            </button>
                        </li>
                    );
                })}
            </ol>

            {step === 1 ? (
                <form className="space-y-6" onSubmit={(e) => { e.preventDefault(); if (form.data.title.trim()) setStep(2); }}>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <label htmlFor="title" className={fieldLabel}>Session name</label>
                            <input id="title" required autoFocus value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="e.g. Computer Science Presentations" className={field} />
                            {form.errors.title && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-600">{form.errors.title}</p>}
                        </div>
                        <div>
                            <label htmlFor="type" className={cn(fieldLabel, 'text-lilac-ink')}>Type</label>
                            <NativeSelect id="type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className="border-lilac-ink/25 bg-lilac py-4 font-black text-lilac-ink focus:border-lilac-ink focus:bg-lilac">
                                {types.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                            </NativeSelect>
                        </div>
                    </div>
                    {form.data.type === 'custom' && (
                        <div>
                            <label htmlFor="custom" className={fieldLabel}>What kind of session?</label>
                            <input id="custom" value={form.data.custom_type_label} onChange={(e) => form.setData('custom_type_label', e.target.value)} placeholder="e.g. Retreat, Site visit" className={field} />
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div>
                            <label htmlFor="date" className={fieldLabel}>Date</label>
                            <input id="date" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label htmlFor="time" className={fieldLabel}>Start time</label>
                            <input id="time" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} className={field} />
                        </div>
                        <div className="col-span-2 sm:col-span-1">
                            <label htmlFor="duration" className={fieldLabel}>Length (minutes)</label>
                            <input id="duration" type="number" min={1} value={form.data.expected_duration} onChange={(e) => form.setData('expected_duration', e.target.value)} placeholder="Optional" className={field} />
                        </div>
                    </div>

                    {!programme && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="sm:col-span-2">
                                <label htmlFor="repeat" className={cn(fieldLabel, 'text-mint-ink')}>Repeats</label>
                                <NativeSelect id="repeat" value={form.data.repeat} onChange={(e) => form.setData('repeat', e.target.value)} className={form.data.repeat ? 'border-mint-ink/30 bg-mint py-4 font-black text-mint-ink focus:border-mint-ink focus:bg-mint' : 'border-mint-ink/20 bg-white py-4 font-bold text-mint-ink focus:border-mint-ink'}>
                                    {repeatOptions(form.data.date).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </NativeSelect>
                            </div>
                            {form.data.repeat && (
                                <div>
                                    <label htmlFor="ends_on" className={fieldLabel}>Until (optional)</label>
                                    <input id="ends_on" type="date" min={form.data.date} value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} className={field} />
                                </div>
                            )}
                            {form.data.repeat && <p className="-mt-2 ml-1 text-[11px] text-gray-400 sm:col-span-3">The next 4 meetings go on the desk, and more as the weeks go by. Each one picks up from the last.</p>}
                            {(form.errors as Record<string, string>).ends_on && <p className="ml-1 text-[11px] font-bold text-rose-600 sm:col-span-3">{(form.errors as Record<string, string>).ends_on}</p>}
                        </div>
                    )}

                    <div>
                        <label className="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white px-5 py-4">
                            <span>
                                <span className="block text-[11px] font-black uppercase tracking-wide text-brand">Track who comes</span>
                                <span className="mt-0.5 block text-[11px] font-medium text-gray-400">A QR code and link so people check themselves in when they arrive</span>
                            </span>
                            <input type="checkbox" checked={form.data.track_participants} onChange={(e) => form.setData('track_participants', e.target.checked)} className="size-5 accent-brand" />
                        </label>
                        {form.data.track_participants && (
                            <div className="mt-3">
                                <label htmlFor="expected" className={fieldLabel}>Expected people (optional)</label>
                                <input id="expected" type="number" min={1} value={form.data.expected_participants} onChange={(e) => form.setData('expected_participants', e.target.value)} placeholder="e.g. 20" className={field} />
                                <p className="ml-1 mt-1 text-[11px] text-gray-400">Only shown as "12/20 checked in". There's no limit.</p>
                            </div>
                        )}
                    </div>

                    <button className="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand py-5 text-[11px] font-black uppercase tracking-[0.3em] text-white shadow-xl transition-all hover:bg-action">
                        {agenda ? 'Next: the agenda' : "Next: who's leading it"} <ArrowRight className="size-4" />
                    </button>
                </form>
            ) : (
                <div className="space-y-6">
                    <div className="rounded-2xl border border-gray-100 bg-white p-5">
                        <p className="text-[13px] font-black text-brand">{form.data.title}</p>
                        <p className="text-[11px] font-bold text-gray-400">{types.find((t) => t.key === form.data.type)?.label}{form.data.date && ` · ${form.data.date}`}{form.data.start_time && ` · ${form.data.start_time}`}{form.data.repeat && ` · ${repeatOptions(form.data.date).find(([v]) => v === form.data.repeat)?.[1]}`}</p>
                    </div>

                    {agenda ? (
                        <>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label htmlFor="chair" className={fieldLabel}>Chair</label>
                                    <input id="chair" value={form.data.chair} onChange={(e) => form.setData('chair', e.target.value)} placeholder="Who chairs it" className={field} />
                                </div>
                                <div>
                                    <label htmlFor="secretary" className={fieldLabel}>Secretary</label>
                                    <input id="secretary" value={form.data.secretary} onChange={(e) => form.setData('secretary', e.target.value)} placeholder="Who minutes it" className={field} />
                                </div>
                                <div>
                                    <label htmlFor="location" className={fieldLabel}>Venue</label>
                                    <input id="location" value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} placeholder="e.g. Boardroom" className={field} />
                                </div>
                            </div>

                            <div>
                                <p className={fieldLabel}>Agenda</p>
                                <form
                                    className="flex flex-col gap-2 sm:flex-row sm:items-center"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        addPerson();
                                    }}
                                >
                                    <input ref={nameRef} aria-label="Agenda item" value={person.title ?? ''} onChange={(e) => setPerson({ ...person, title: e.target.value })} placeholder="Item, then Enter" className={cn(field, 'sm:flex-1')} />
                                    <div className="flex items-center gap-2">
                                        <input aria-label="Led by" value={person.name} onChange={(e) => setPerson({ ...person, name: e.target.value })} placeholder="Led by (optional)" className={cn(field, 'min-w-0 sm:w-48')} />
                                        <button aria-label="Add item" className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand text-white transition-colors hover:bg-action"><Plus className="size-4" /></button>
                                    </div>
                                </form>
                                <p className="ml-1 mt-2 flex items-center gap-1 text-[11px] text-gray-400"><CornerDownLeft className="size-3" />Press Enter after each item. The previous minutes and matters arising are in the minutes on their own.</p>
                            </div>
                        </>
                    ) : (
                        <div>
                            <p className={fieldLabel}>Presenters and other people</p>
                            <form
                                className="flex flex-col gap-2 sm:flex-row sm:items-center"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    addPerson();
                                }}
                            >
                                <input ref={nameRef} aria-label="Name" value={person.name} onChange={(e) => setPerson({ ...person, name: e.target.value })} placeholder="Name, then Enter" className={cn(field, 'sm:flex-1')} />
                                <div className="flex items-center gap-2">
                                    {roles.length > 0 && (
                                        <NativeSelect aria-label="Role" value={person.role} onChange={(e) => pickRole(e.target.value)} className="w-40 border-gray-200 bg-white py-4 font-bold text-brand">
                                            <option value="">Role…</option>
                                            {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                                        </NativeSelect>
                                    )}
                                    <label className="flex items-center gap-1.5 whitespace-nowrap text-[10px] font-black uppercase tracking-wide text-gray-500">
                                        <input type="checkbox" checked={person.presenting} onChange={(e) => setPerson({ ...person, presenting: e.target.checked })} className="accent-brand" />Presenting
                                    </label>
                                    <button aria-label="Add person" className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand text-white transition-colors hover:bg-action"><Plus className="size-4" /></button>
                                </div>
                            </form>
                            <p className="ml-1 mt-2 flex items-center gap-1 text-[11px] text-gray-400"><CornerDownLeft className="size-3" />Press Enter after each name. You can add people during the session too.</p>
                        </div>
                    )}

                    {form.data.people.length > 0 ? (
                        <ol className="divide-y divide-gray-50 rounded-2xl border border-gray-100 bg-white">
                            {form.data.people.map((p, i) => (
                                <li key={i} className="flex items-center justify-between gap-3 px-5 py-3">
                                    <span className="flex min-w-0 items-center gap-3">
                                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[11px] font-black text-gray-500">{i + 1}</span>
                                        <span className="min-w-0">
                                            <span className="block truncate text-[13px] font-bold text-brand">{agenda ? p.title || p.name : p.name}</span>
                                            <span className="block text-[11px] text-gray-400">
                                                {agenda
                                                    ? p.title && p.name ? `Led by ${p.name}` : 'No one leading it'
                                                    : [roles.find((r) => r.value === p.role)?.label, p.presenting ? 'Presenting' : 'Not presenting'].filter(Boolean).join(' · ')}
                                            </span>
                                        </span>
                                    </span>
                                    <button type="button" aria-label={`Remove ${agenda ? p.title || p.name : p.name}`} onClick={() => form.setData('people', form.data.people.filter((_, j) => j !== i))} className="text-gray-300 hover:text-rose-500"><X className="size-4" /></button>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <p className="rounded-2xl border border-dashed border-gray-200 py-6 text-center text-[12px] text-gray-400">{agenda ? 'No items yet. They come up in the order you add them.' : 'No one yet. They present in the order you add them.'}</p>
                    )}

                    <div className="flex flex-col-reverse gap-3 sm:flex-row">
                        <button type="button" onClick={() => setStep(1)} className="flex items-center justify-center gap-2 rounded-2xl bg-gray-100 px-6 py-5 text-[11px] font-black uppercase tracking-widest text-gray-500"><ArrowLeft className="size-4" />Back</button>
                        <button type="button" disabled={form.processing} onClick={submit} className="flex flex-1 items-center justify-center rounded-2xl bg-action py-5 text-[11px] font-black uppercase tracking-[0.3em] text-white shadow-xl transition-all hover:shadow-2xl disabled:opacity-50">
                            {form.processing ? <LoaderCircle className="size-5 animate-spin" /> : form.data.repeat ? 'Schedule the meetings' : startsToday ? 'Schedule and open' : 'Schedule session'}
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}

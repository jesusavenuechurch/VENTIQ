import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CornerDownLeft, FolderOpen, LoaderCircle, Plus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SessionsLayout, { SESSIONS_AREA, field, fieldLabel } from '@/Layouts/SessionsLayout';
import { forgetTab } from '@/components/area-tabs';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { SessionsArea } from '@/types';

interface Role {
    value: string;
    label: string;
    presenting: boolean;
}

interface Props {
    types: { key: string; label: string; roles: Role[] }[];
    programme: { id: number; name: string } | null;
    today: string;
}

interface Person {
    name: string;
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
};

// A half-filled form comes back when you return to it (see the Desk tab).
const DRAFT = 'sessions:create-draft';
const emptyPerson: Person = { name: '', role: '', presenting: true };

function readDraft(programmeId: number | null): { data: Data; step: 1 | 2; person: Person } | null {
    try {
        const draft = JSON.parse(window.sessionStorage.getItem(DRAFT) ?? 'null');
        return draft && draft.data?.programme_id === programmeId ? draft : null;
    } catch {
        return null;
    }
}

/** Schedule a session in two steps: what it is, then who's leading it. */
export default function Create({ types, programme, today }: Props) {
    const draft = useRef(readDraft(programme?.id ?? null)).current;
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
    };
    const form = useForm<Data>(draft?.data ?? blank);
    const [step, setStep] = useState<1 | 2>(draft?.step ?? 1);
    const [person, setPerson] = useState<Person>(draft?.person ?? emptyPerson);
    const nameRef = useRef<HTMLInputElement>(null);
    const roles = types.find((t) => t.key === form.data.type)?.roles ?? [];
    const startsToday = form.data.date === today || !form.data.date;

    useEffect(() => {
        try {
            window.sessionStorage.setItem(DRAFT, JSON.stringify({ data: form.data, step, person }));
        } catch {
            // Private mode: no draft.
        }
    }, [form.data, step, person]);
    useEffect(() => {
        if (step === 2) nameRef.current?.focus();
    }, [step]);

    const addPerson = () => {
        const name = person.name.trim();
        if (!name) return;
        form.setData('people', [...form.data.people, { ...person, name }]);
        setPerson({ ...emptyPerson, role: person.role, presenting: person.presenting });
        nameRef.current?.focus();
    };
    const pickRole = (value: string) => {
        const role = roles.find((r) => r.value === value);
        setPerson((p) => ({ ...p, role: value, presenting: role ? role.presenting : p.presenting }));
    };

    const submit = () => {
        // A name still in the box counts: it would be lost otherwise.
        const pending = person.name.trim() ? [{ ...person, name: person.name.trim() }] : [];
        form.transform((d) => ({ ...d, people: [...d.people, ...pending], custom_type_label: d.type === 'custom' ? d.custom_type_label : '' }));
        form.post(route('sessions.store'), {
            onSuccess: () => {
                try {
                    window.sessionStorage.removeItem(DRAFT);
                } catch {
                    // Nothing stored.
                }
                forgetTab(SESSIONS_AREA, 'sessions.index');
            },
            onError: (errors) => errors.title && setStep(1),
        });
    };

    const steps = ['The session', "Who's leading it"];
    const [restored, setRestored] = useState(!!draft && !!draft.data.title);
    const startAgain = () => {
        form.setData(blank);
        form.clearErrors();
        setPerson(emptyPerson);
        setStep(1);
        setRestored(false);
    };

    return (
        <SessionsLayout title="Schedule a session" subtitle="Start it live today, or line it up for later." crumbs={[['Desk', route('sessions.index')], ['Schedule a session']]} width="max-w-3xl">
            {programme && (
                <p className="mb-6 flex items-center gap-2 rounded-2xl bg-mint/40 p-4 text-[12px] font-bold text-mint-ink"><FolderOpen className="size-4" />Part of {programme.name}</p>
            )}

            {sessionsArea.quota.remaining <= 0 && (
                <p className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-[12px] font-bold text-amber-800">
                    You've used this month's sessions. Add more first, then come back: this form keeps what you've typed.
                    <Link href={route('sessions.plan')} className="shrink-0 rounded-full bg-amber-500 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white">Add sessions</Link>
                </p>
            )}

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
                            <label htmlFor="type" className={fieldLabel}>Type</label>
                            <NativeSelect id="type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className="border-gray-200 bg-white py-4 font-bold text-brand">
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
                        Next: who's leading it <ArrowRight className="size-4" />
                    </button>
                </form>
            ) : (
                <div className="space-y-6">
                    <div className="rounded-2xl border border-gray-100 bg-white p-5">
                        <p className="text-[13px] font-black text-brand">{form.data.title}</p>
                        <p className="text-[11px] font-bold text-gray-400">{types.find((t) => t.key === form.data.type)?.label}{form.data.date && ` · ${form.data.date}`}{form.data.start_time && ` · ${form.data.start_time}`}</p>
                    </div>

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

                    {form.data.people.length > 0 ? (
                        <ol className="divide-y divide-gray-50 rounded-2xl border border-gray-100 bg-white">
                            {form.data.people.map((p, i) => (
                                <li key={i} className="flex items-center justify-between gap-3 px-5 py-3">
                                    <span className="flex min-w-0 items-center gap-3">
                                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[11px] font-black text-gray-500">{i + 1}</span>
                                        <span className="min-w-0">
                                            <span className="block truncate text-[13px] font-bold text-brand">{p.name}</span>
                                            <span className="block text-[11px] text-gray-400">
                                                {[roles.find((r) => r.value === p.role)?.label, p.presenting ? 'Presenting' : 'Not presenting'].filter(Boolean).join(' · ')}
                                            </span>
                                        </span>
                                    </span>
                                    <button type="button" aria-label={`Remove ${p.name}`} onClick={() => form.setData('people', form.data.people.filter((_, j) => j !== i))} className="text-gray-300 hover:text-rose-500"><X className="size-4" /></button>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <p className="rounded-2xl border border-dashed border-gray-200 py-6 text-center text-[12px] text-gray-400">No one yet. They present in the order you add them.</p>
                    )}

                    <div className="flex flex-col-reverse gap-3 sm:flex-row">
                        <button type="button" onClick={() => setStep(1)} className="flex items-center justify-center gap-2 rounded-2xl bg-gray-100 px-6 py-5 text-[11px] font-black uppercase tracking-widest text-gray-500"><ArrowLeft className="size-4" />Back</button>
                        <button type="button" disabled={form.processing} onClick={submit} className="flex flex-1 items-center justify-center rounded-2xl bg-action py-5 text-[11px] font-black uppercase tracking-[0.3em] text-white shadow-xl transition-all hover:shadow-2xl disabled:opacity-50">
                            {form.processing ? <LoaderCircle className="size-5 animate-spin" /> : startsToday ? 'Schedule and open' : 'Schedule session'}
                        </button>
                    </div>
                </div>
            )}
        </SessionsLayout>
    );
}

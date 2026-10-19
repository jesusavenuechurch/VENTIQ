import { useForm } from '@inertiajs/react';
import { FolderOpen, LoaderCircle, Plus, X } from 'lucide-react';
import { useState } from 'react';
import SessionsLayout, { field, fieldLabel } from '@/Layouts/SessionsLayout';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';

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

/** Schedule a session: start it live today, or line it up for later. */
export default function Create({ types, programme, today }: Props) {
    const form = useForm({
        title: '',
        type: types[0]?.key ?? 'presentation',
        custom_type_label: '',
        date: today,
        start_time: '',
        people: [] as Person[],
        expected_duration: '',
        track_participants: false,
        expected_participants: '',
        programme_id: programme?.id ?? null,
    });
    const [person, setPerson] = useState<Person>({ name: '', role: '', presenting: true });
    const roles = types.find((t) => t.key === form.data.type)?.roles ?? [];

    const addPerson = () => {
        const name = person.name.trim();
        if (!name) return;
        form.setData('people', [...form.data.people, { ...person, name }]);
        setPerson({ name: '', role: '', presenting: true });
    };
    const pickRole = (value: string) => {
        const role = roles.find((r) => r.value === value);
        setPerson((p) => ({ ...p, role: value, presenting: role ? role.presenting : p.presenting }));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, custom_type_label: d.type === 'custom' ? d.custom_type_label : '' }));
        form.post(route('sessions.store'));
    };
    const startsToday = form.data.date === today || !form.data.date;

    return (
        <SessionsLayout
            title="Schedule a session"
            subtitle="Start it live today, or line it up for later."
            crumbs={[['Desk', route('sessions.index')], ['Schedule a session']]}
            width="max-w-2xl"
        >
            {programme && (
                <p className="mb-6 flex items-center gap-2 rounded-2xl bg-mint/40 p-4 text-[12px] font-bold text-mint-ink"><FolderOpen className="size-4" />Part of {programme.name}</p>
            )}

            <form onSubmit={submit} className="space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label htmlFor="type" className={fieldLabel}>Session type</label>
                        <NativeSelect id="type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className="py-4">
                            {types.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                        </NativeSelect>
                    </div>
                    {form.data.type === 'custom' && (
                        <div>
                            <label htmlFor="custom" className={fieldLabel}>What kind of session?</label>
                            <input id="custom" value={form.data.custom_type_label} onChange={(e) => form.setData('custom_type_label', e.target.value)} placeholder="e.g. Retreat, Site visit" className={field} />
                        </div>
                    )}
                </div>

                <div>
                    <label htmlFor="title" className={fieldLabel}>Session name</label>
                    <input id="title" required value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="e.g. Computer Science Presentations" className={field} />
                    {form.errors.title && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-600">{form.errors.title}</p>}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label htmlFor="date" className={fieldLabel}>Date</label>
                        <input id="date" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} className={field} />
                    </div>
                    <div>
                        <label htmlFor="time" className={fieldLabel}>Start time</label>
                        <input id="time" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} className={field} />
                    </div>
                </div>

                <div>
                    <p className={fieldLabel}>People leading this session</p>
                    {form.data.people.length > 0 && (
                        <div className="mb-3 flex flex-wrap gap-2">
                            {form.data.people.map((p, i) => (
                                <span key={i} className="inline-flex items-center gap-2 rounded-full bg-gray-50 py-1.5 pl-3 pr-2 text-[11px] font-bold text-brand">
                                    {p.name}
                                    {p.role && <span className="font-normal text-gray-400">· {roles.find((r) => r.value === p.role)?.label ?? p.role}</span>}
                                    <span className={cn('text-[9px] font-black uppercase tracking-wide', p.presenting ? 'text-emerald-600' : 'text-gray-400')}>{p.presenting ? 'Presenting' : 'Not presenting'}</span>
                                    <button type="button" aria-label={`Remove ${p.name}`} onClick={() => form.setData('people', form.data.people.filter((_, j) => j !== i))} className="text-gray-300 hover:text-rose-500"><X className="size-3.5" /></button>
                                </span>
                            ))}
                        </div>
                    )}
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input aria-label="Name" value={person.name} onChange={(e) => setPerson({ ...person, name: e.target.value })} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addPerson())} placeholder="Name" className={cn(field, 'py-3.5 sm:flex-1')} />
                        <div className="flex items-center gap-2">
                            {roles.length > 0 && (
                                <NativeSelect aria-label="Role" value={person.role} onChange={(e) => pickRole(e.target.value)} className="w-40 py-3.5">
                                    <option value="">Role…</option>
                                    {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                                </NativeSelect>
                            )}
                            <label className="flex items-center gap-1.5 whitespace-nowrap text-[10px] font-black uppercase tracking-wide text-gray-500">
                                <input type="checkbox" checked={person.presenting} onChange={(e) => setPerson({ ...person, presenting: e.target.checked })} className="accent-brand" />Presenting
                            </label>
                            <button type="button" aria-label="Add person" onClick={addPerson} className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand text-white transition-colors hover:bg-action"><Plus className="size-4" /></button>
                        </div>
                    </div>
                    <p className="ml-1 mt-1 text-[11px] text-gray-400">You can add people during the session too.</p>
                </div>

                <div>
                    <label htmlFor="duration" className={fieldLabel}>Expected length in minutes (optional)</label>
                    <input id="duration" type="number" min={1} value={form.data.expected_duration} onChange={(e) => form.setData('expected_duration', e.target.value)} placeholder="e.g. 60" className={field} />
                </div>

                <div>
                    <label className="flex cursor-pointer items-center justify-between gap-4 rounded-2xl bg-gray-50 px-5 py-4">
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

                <button disabled={form.processing} className="flex w-full items-center justify-center rounded-2xl bg-brand py-5 text-[11px] font-black uppercase tracking-[0.3em] text-white shadow-xl transition-all hover:bg-action disabled:opacity-50">
                    {form.processing ? <LoaderCircle className="size-5 animate-spin" /> : startsToday ? 'Schedule and open' : 'Schedule session'}
                </button>
            </form>
        </SessionsLayout>
    );
}

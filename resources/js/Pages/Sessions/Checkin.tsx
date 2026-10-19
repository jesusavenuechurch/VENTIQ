import { useForm } from '@inertiajs/react';
import { Check, Download, IdCard, Pencil } from 'lucide-react';
import { useState } from 'react';
import SessionsLayout, { field, quietButton } from '@/Layouts/SessionsLayout';
import { cn } from '@/lib/utils';

interface Participant {
    id: number;
    name: string | null;
    phone: string | null;
    email: string | null;
    photo: string | null;
    time: string | null;
    institution: string | null;
    position: string | null;
    notified: boolean;
}

const small = 'rounded-xl border-none bg-gray-50 px-4 py-3 text-sm font-bold text-brand outline-none focus:ring-2 focus:ring-action/20';

/** Who's checked in to a session, and checking someone in by hand. */
export default function Checkin({ session, participants }: { session: { id: number; title: string }; participants: Participant[] }) {
    const form = useForm({ full_name: '', phone: '', email: '', institution: '', position: '' });

    return (
        <SessionsLayout
            title="Check-in"
            subtitle={`${session.title} · ${participants.length} checked in`}
            crumbs={[['Session', route('sessions.show', session.id)], ['Check-in']]}
            actions={<a href={route('sessions.checkin.pdf', session.id)} className={quietButton}><Download className="size-3.5" />PDF</a>}
            width="max-w-3xl"
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('sessions.checkin.store', session.id), { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="mb-8 rounded-2xl border border-gray-100 bg-white p-4"
            >
                <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Check someone in</p>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <input aria-label="Full name" required autoFocus value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} placeholder="Full name" className={cn(field, 'sm:col-span-2')} />
                    <input aria-label="Phone" type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="Phone" className={small} />
                    <input aria-label="Email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder="Email, for their attendance card" className={small} />
                    <input aria-label="Institution" value={form.data.institution} onChange={(e) => form.setData('institution', e.target.value)} placeholder="Organization or institution" className={small} />
                    <input aria-label="Position" value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} placeholder="Position or role" className={small} />
                </div>
                <button disabled={form.processing} className="mt-3 w-full rounded-2xl bg-brand py-4 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:bg-action disabled:opacity-50">Check in</button>
            </form>

            <div className="space-y-2">
                {participants.map((p) => <Row key={p.id} sessionId={session.id} p={p} />)}
                {participants.length === 0 && <p className="py-10 text-center text-sm italic text-gray-400">No one checked in yet.</p>}
            </div>
        </SessionsLayout>
    );
}

function Row({ sessionId, p }: { sessionId: number; p: Participant }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ full_name: p.name ?? '', phone: p.phone ?? '', email: p.email ?? '', institution: p.institution ?? '', position: p.position ?? '' });
    const initials = (p.name ?? '?').split(' ').map((n) => n[0]).slice(0, 2).join('');

    if (editing) {
        return (
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch(route('sessions.checkin.update', [sessionId, p.id]), { preserveScroll: true, onSuccess: () => setEditing(false) });
                }}
                className="space-y-2 rounded-2xl border border-gray-100 bg-white px-5 py-4"
            >
                <input aria-label="Full name" required value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} placeholder="Full name" className={cn(small, 'w-full')} />
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <input aria-label="Phone" type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="Phone" className={small} />
                    <input aria-label="Email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder="Email" className={small} />
                    <input aria-label="Institution" value={form.data.institution} onChange={(e) => form.setData('institution', e.target.value)} placeholder="Organization or institution" className={small} />
                    <input aria-label="Position" value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} placeholder="Position or role" className={small} />
                </div>
                {Object.values(form.errors)[0] && <p className="text-[11px] font-bold text-rose-600">{Object.values(form.errors)[0]}</p>}
                <div className="flex gap-2 pt-1">
                    <button disabled={form.processing} className="flex-1 rounded-xl bg-brand py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action">Save</button>
                    <button type="button" onClick={() => { form.reset(); setEditing(false); }} className="rounded-xl bg-gray-50 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-gray-500">Cancel</button>
                </div>
            </form>
        );
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white px-5 py-3">
            <div className="flex min-w-0 items-center gap-3">
                {p.photo ? (
                    <img src={p.photo} alt="" className="size-10 shrink-0 rounded-full border border-gray-100 object-cover" />
                ) : (
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-action text-[11px] font-black text-white">{initials}</span>
                )}
                <div className="min-w-0">
                    <p className="truncate text-sm font-black text-brand">{p.name}</p>
                    <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">{p.time}{p.notified && <span className="ml-2 text-blue-500"><Check className="inline size-3" /> Card sent</span>}</p>
                    <p className={cn('text-[11px] font-bold', p.institution ? 'text-gray-500' : 'text-rose-400')}>{p.institution || 'No institution'}{' · '}<span className={p.position ? 'text-gray-500' : 'text-rose-400'}>{p.position || 'No position'}</span></p>
                </div>
            </div>
            <div className="flex items-center gap-3">
                <button type="button" onClick={() => setEditing(true)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-brand hover:text-action"><Pencil className="size-3" />Edit</button>
                <a href={route('sessions.checkin.card', [sessionId, p.id])} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-brand hover:text-action"><IdCard className="size-3.5" />Card</a>
            </div>
        </div>
    );
}

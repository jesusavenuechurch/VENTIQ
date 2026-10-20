import { useForm } from '@inertiajs/react';
import { Award, CalendarDays, CircleCheck, LoaderCircle, MapPin } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { authButton, authInput } from '@/Layouts/AuthLayout';

interface Props {
    programme: { name: string; organization: string | null; venue: string | null; open: boolean; days: string[]; rule: string | null; token: string };
    /** The name of whoever just enrolled, from the redirect. */
    enrolled: string | null;
}

/** A programme's enrolment link: what it is, its days, and enrolling once for all of them. */
export default function ProgrammeEnrol({ programme, enrolled }: Props) {
    const form = useForm({ full_name: '', email: '', phone: '', institution: '', position: '' });
    const firstError = Object.values(form.errors)[0];

    return (
        <GuestLayout label="Enrol" context={programme.organization ?? undefined}>
            <div className="mx-auto max-w-sm py-8">
                <div className="rounded-[2rem] bg-white p-8 shadow-xl">
                    {enrolled ? (
                        <div className="py-6 text-center">
                            <CircleCheck className="mx-auto mb-4 size-14 text-emerald-500" />
                            <h1 className="text-lg font-black uppercase">You're enrolled, {enrolled}</h1>
                            <p className="mt-2 text-sm font-bold text-gray-600">{programme.name}</p>
                            <p className="mt-6 text-[12px] leading-relaxed text-gray-500">On each day, check in when you arrive with the QR code or code shown in the room. That's how your attendance is counted.</p>
                            {programme.rule && <p className="mt-3 text-[12px] leading-relaxed text-gray-500">Your certificate comes by email once you've attended {programme.rule}.</p>}
                            <p className="mt-6 text-[11px] text-gray-400">We've emailed you the days.</p>
                        </div>
                    ) : (
                        <>
                            <h1 className="text-lg font-black uppercase tracking-tight">{programme.name}</h1>
                            {programme.venue && <p className="mt-2 flex items-center gap-1.5 text-[12px] font-medium text-gray-500"><MapPin className="size-3.5 shrink-0" />{programme.venue}</p>}
                            {programme.days.length > 0 && (
                                <div className="mt-4 rounded-2xl bg-gray-50 p-4">
                                    <p className="mb-2 flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400"><CalendarDays className="size-3.5" />{programme.days.length === 1 ? 'One day' : `${programme.days.length} days`}</p>
                                    <ul className="space-y-1">{programme.days.map((d, i) => <li key={i} className="text-[12px] font-bold text-brand">{d}</li>)}</ul>
                                </div>
                            )}
                            {programme.rule && <p className="mt-3 flex items-start gap-1.5 text-[12px] font-medium text-mint-ink"><Award className="mt-0.5 size-3.5 shrink-0" />A certificate for those who attend {programme.rule}.</p>}

                            {!programme.open ? (
                                <p className="mt-6 rounded-xl bg-amber-50 p-4 text-[12px] font-bold text-amber-800">Enrolment is closed. Ask the organizer if you should be on it.</p>
                            ) : (
                                <>
                                    <p className="mb-4 mt-6 text-[12px] font-medium text-gray-500">Enrol once for every day. Enrolled already? The same email changes your details.</p>
                                    {firstError && <div role="alert" className="mb-4 rounded-xl bg-rose-50 p-3 text-[12px] font-bold text-rose-600">{firstError}</div>}
                                    <form
                                        className="space-y-3"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            form.post(route('public.programme-enrol.submit', programme.token), { preserveScroll: true });
                                        }}
                                    >
                                        <input aria-label="Full name" required autoFocus autoComplete="name" value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} placeholder="Full name" className={authInput} />
                                        <input aria-label="Email" required type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder="Email: your certificate comes here" className={authInput} />
                                        <input aria-label="Phone" type="tel" autoComplete="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="Phone (optional)" className={authInput} />
                                        <input aria-label="Organization or institution" value={form.data.institution} onChange={(e) => form.setData('institution', e.target.value)} placeholder="Organization or institution" className={authInput} />
                                        <input aria-label="Position or role" value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} placeholder="Position or role" className={authInput} />
                                        <button disabled={form.processing} className={authButton}>{form.processing ? <LoaderCircle className="size-5 animate-spin" /> : 'Enrol'}</button>
                                    </form>
                                </>
                            )}
                        </>
                    )}
                </div>
            </div>
        </GuestLayout>
    );
}

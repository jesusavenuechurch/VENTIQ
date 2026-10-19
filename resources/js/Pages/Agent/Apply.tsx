import { Head, usePage } from '@inertiajs/react';
import { BadgeCheck, CircleAlert, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import AuthLayout, { authButton, authInput } from '@/Layouts/AuthLayout';
import { PhoneInput, formatLocal } from '@/components/phone-input';
import { NativeSelect } from '@/components/ui/native-select';
import { json } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

const REACH = ['Churches', 'Schools', 'Businesses', 'Event Planners'];
const back = 'w-1/3 rounded-2xl bg-gray-100 py-5 text-[10px] font-black uppercase text-gray-400';

/** Apply to be a VENTIQ sales agent: who you are, who you can reach, why. VENTIQ reviews it. */
export default function Apply() {
    const { site } = usePage<SharedProps>().props;
    const [step, setStep] = useState(1);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [done, setDone] = useState(false);
    const [d, setD] = useState({ name: '', phone: '', email: '', district: '', reach: [] as string[], motivation: '' });

    async function submit() {
        setLoading(true);
        setError('');
        const { ok, data } = await json<{ errors?: Record<string, string[]> }>(route('agent.submit'), {
            method: 'POST',
            body: { name: d.name, phone: `+266 ${formatLocal(d.phone)}`, email: d.email, city_district: d.district, access_types: d.reach, motivation: d.motivation },
        });
        setLoading(false);
        if (ok) setDone(true);
        else setError(data.errors ? Object.values(data.errors).flat().join(' ') : "We couldn't send your application. Please try again.");
    }

    return (
        <AuthLayout wide label="Become a VENTIQ agent">
            <Head title="Become an agent" />
            {!done && (
                <div className="-mx-8 -mt-8 mb-8 h-1.5 overflow-hidden rounded-t-[2.5rem] bg-gray-50 md:-mx-12 md:-mt-12">
                    <div className="h-full bg-gradient-to-r from-brand to-action transition-all duration-500" style={{ width: `${(step / 3) * 100}%` }} />
                </div>
            )}
            {error && <div role="alert" className="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700"><CircleAlert className="mr-1 inline size-4" />{error}</div>}

            {done ? (
                <div className="py-10 text-center">
                    <BadgeCheck className="mx-auto size-12 text-mint-ink" />
                    <h2 className="mt-4 text-2xl font-black text-brand">Application sent</h2>
                    <p className="mt-2 text-[13px] text-gray-500">Thank you, {d.name.split(' ')[0]}. VENTIQ reviews every application and will be in touch on WhatsApp or email. Once you're approved, you'll get an email to set your password.</p>
                </div>
            ) : step === 1 ? (
                <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); if (d.name && d.phone.length === 8 && /\S+@\S+\.\S+/.test(d.email)) setStep(2); else setError('Enter your name, an 8-digit WhatsApp number and your email.'); }}>
                    <h2 className="text-xl font-black uppercase text-brand">1. You</h2>
                    <input aria-label="Full name" autoComplete="name" placeholder="Full name" value={d.name} onChange={(e) => setD({ ...d, name: e.target.value })} className={authInput} />
                    <PhoneInput id="phone" required value={d.phone} onChange={(v) => setD({ ...d, phone: v })} />
                    <input aria-label="Email address" type="email" autoComplete="email" placeholder="Email address" value={d.email} onChange={(e) => setD({ ...d, email: e.target.value })} className={authInput} />
                    <button className={authButton}>Next: who you can reach</button>
                </form>
            ) : step === 2 ? (
                <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); if (d.district && d.reach.length) { setError(''); setStep(3); } else setError('Choose your district and at least one kind of organization.'); }}>
                    <h2 className="text-xl font-black uppercase text-brand">2. Where and who</h2>
                    <NativeSelect aria-label="District" value={d.district} onChange={(e) => setD({ ...d, district: e.target.value })} className="py-4">
                        <option value="">Your district…</option>
                        {site.districts.map((dist) => <option key={dist} value={dist}>{dist}</option>)}
                    </NativeSelect>
                    <div>
                        <p className="mb-2 ml-1 text-[10px] font-black uppercase tracking-widest text-gray-400">Organizations you can easily reach</p>
                        <div className="grid grid-cols-2 gap-3">
                            {REACH.map((type) => {
                                const on = d.reach.includes(type);
                                return (
                                    <label key={type} className={cn('flex cursor-pointer items-center gap-3 rounded-2xl border-2 p-4 transition-all', on ? 'border-action/40 bg-white shadow-sm' : 'border-transparent bg-gray-50')}>
                                        <input type="checkbox" checked={on} onChange={() => setD({ ...d, reach: on ? d.reach.filter((r) => r !== type) : [...d.reach, type] })} className="accent-action" />
                                        <span className={cn('text-[11px] font-black uppercase', on ? 'text-action' : 'text-gray-500')}>{type}</span>
                                    </label>
                                );
                            })}
                        </div>
                    </div>
                    <div className="flex gap-3">
                        <button type="button" onClick={() => setStep(1)} className={back}>Back</button>
                        <button className={cn(authButton, 'w-2/3')}>Next: why</button>
                    </div>
                </form>
            ) : (
                <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                    <h2 className="text-xl font-black uppercase text-brand">3. Why</h2>
                    <p className="rounded-2xl bg-slate-50 p-4 text-[12px] italic text-gray-600">VENTIQ agents help organizations move their events online. Tell us how you would bring in your first few organizations.</p>
                    <textarea aria-label="Why you want to be an agent" rows={5} maxLength={500} placeholder="How you'll bring in your first 5 organizations…" value={d.motivation} onChange={(e) => setD({ ...d, motivation: e.target.value })} className={cn(authInput, 'resize-none text-sm')} />
                    <p className={cn('-mt-3 ml-1 text-[11px] font-bold', d.motivation.length >= 50 ? 'text-mint-ink' : 'text-gray-400')}>{d.motivation.length} / at least 50 characters</p>
                    <div className="flex gap-3">
                        <button type="button" onClick={() => setStep(2)} className={back}>Back</button>
                        <button disabled={loading || d.motivation.length < 50} className={cn(authButton, 'w-2/3')}>{loading ? <LoaderCircle className="size-5 animate-spin" /> : 'Send my application'}</button>
                    </div>
                </form>
            )}
        </AuthLayout>
    );
}

import { Head, usePage } from '@inertiajs/react';
import { BadgeCheck, ChevronRight, CircleAlert, LoaderCircle, Rocket, Sparkles } from 'lucide-react';
import { useState } from 'react';
import AuthLayout, { authButton, authInput } from '@/Layouts/AuthLayout';
import { NativeSelect } from '@/components/ui/native-select';
import { PhoneInput, formatLocal } from '@/components/phone-input';
import { json } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

const validEmail = (v: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.toLowerCase());
const back = 'w-1/3 rounded-2xl bg-gray-100 py-5 text-[10px] font-black uppercase text-gray-400';

/** A new organization and its first admin, in three short steps; through an agent's link, it's theirs. */
export default function Register({ agent, submitUrl }: { agent: { name: string } | null; submitUrl: string }) {
    const { site } = usePage<SharedProps>().props;
    const [step, setStep] = useState(1);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [done, setDone] = useState(false);
    const [isOrg, setIsOrg] = useState(true);
    const [more, setMore] = useState(false);
    const [d, setD] = useState({ name: '', email: '', password: '', confirm: '', orgName: '', district: 'Maseru', phone: '', tagline: '', description: '', orgEmail: '', contactEmail: '' });
    const set = (key: keyof typeof d) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => setD((x) => ({ ...x, [key]: e.target.value }));
    const go = (next: number) => {
        setError('');
        setStep(next);
    };

    async function submit() {
        setLoading(true);
        setError('');
        const { ok, data } = await json<{ redirect?: string; message?: string; errors?: Record<string, string[]> }>(submitUrl, {
            method: 'POST',
            body: {
                user_name: d.name,
                user_email: d.email,
                user_password: d.password,
                user_password_confirmation: d.confirm,
                org_name: isOrg ? d.orgName : d.name,
                org_phone: `+266 ${formatLocal(d.phone)}`,
                org_district: d.district,
                tagline: d.tagline,
                description: d.description,
                email: d.orgEmail || d.email,
                contact_email: d.contactEmail || d.email,
            },
        });
        if (ok) {
            setDone(true);
            // Signed in now: a full page load from here.
            window.setTimeout(() => window.location.replace(data.redirect || route('verification.notice')), 1500);
            return;
        }
        setError((data.errors && Object.values(data.errors)[0]?.[0]) || data.message || 'Something went wrong. Please try again.');
        setLoading(false);
    }

    return (
        <AuthLayout
            wide
            label={agent ? <><BadgeCheck className="mr-1 inline size-3" />Invited by {agent.name}</> : 'No monthly fee'}
            footer={<p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">Already have an account? <a href={route('login')} className="text-action hover:underline">Sign in</a></p>}
        >
            <Head title="Create an account" />
            {!done && (
                <div className="-mx-8 -mt-8 mb-8 h-1.5 overflow-hidden rounded-t-[2.5rem] bg-gray-50 md:-mx-12 md:-mt-12">
                    <div className="h-full bg-gradient-to-r from-brand to-action transition-all duration-500" style={{ width: `${(step / 3) * 100}%` }} />
                </div>
            )}

            {error && (
                <div role="alert" className="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700">
                    <CircleAlert className="mr-1 inline size-4" />{error}
                </div>
            )}

            {done ? (
                <div className="py-12 text-center">
                    <Rocket className="mx-auto size-10 text-brand" />
                    <h3 className="mt-6 text-2xl font-black uppercase italic text-brand">You're in</h3>
                    <p className="mt-2 text-[12px] font-bold text-gray-500">Setting up your account…</p>
                </div>
            ) : (
                <>
                    {step === 1 && (
                        <>
                            <a href={route('auth.google.redirect')} className="mb-6 flex w-full items-center justify-center gap-3 rounded-2xl border border-gray-200 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-brand transition-all hover:border-brand">
                                Continue with Google
                            </a>
                            <div className="mb-6 flex items-center gap-3"><div className="h-px flex-1 bg-gray-100" /><span className="text-[9px] font-bold uppercase tracking-widest text-gray-300">or</span><div className="h-px flex-1 bg-gray-100" /></div>
                            <form className="space-y-6" onSubmit={(e) => { e.preventDefault(); if (d.name && validEmail(d.email)) go(2); else setError('Enter your name and a valid email address.'); }}>
                                <h2 className="text-xl font-black uppercase text-brand">1. You</h2>
                                <div className="space-y-4">
                                    <input aria-label="Full name" autoComplete="name" value={d.name} onChange={set('name')} placeholder="Full name" className={authInput} />
                                    <input aria-label="Email address" type="email" autoComplete="email" value={d.email} onChange={set('email')} placeholder="Email address" className={authInput} />
                                </div>
                                <button className={authButton}>Next: password</button>
                            </form>
                        </>
                    )}

                    {step === 2 && (
                        <form className="space-y-6" onSubmit={(e) => {
                            e.preventDefault();
                            if (d.password.length < 8) setError('Use at least 8 characters.');
                            else if (d.password !== d.confirm) setError("The passwords don't match.");
                            else go(3);
                        }}>
                            <h2 className="text-xl font-black uppercase text-brand">2. Password</h2>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <input aria-label="Password" type="password" autoComplete="new-password" value={d.password} onChange={set('password')} placeholder="Password" className={authInput} />
                                <input aria-label="The same again" type="password" autoComplete="new-password" value={d.confirm} onChange={set('confirm')} placeholder="The same again" className={authInput} />
                            </div>
                            <p className="-mt-3 text-[11px] text-gray-400">At least 8 characters.</p>
                            <div className="flex gap-3">
                                <button type="button" onClick={() => go(1)} className={back}>Back</button>
                                <button className={cn(authButton, 'w-2/3')}>Next: who's hosting</button>
                            </div>
                        </form>
                    )}

                    {step === 3 && (
                        <form className="space-y-6" onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                            <h2 className="text-xl font-black uppercase text-brand">3. Who's hosting</h2>
                            <div className="flex rounded-[1.5rem] bg-gray-100 p-1.5">
                                {([[true, 'An organization'], [false, 'Just me']] as const).map(([value, label]) => (
                                    <button key={label} type="button" onClick={() => setIsOrg(value)} className={cn('flex-1 rounded-2xl py-3 text-[10px] font-black uppercase transition-all', isOrg === value ? 'bg-white text-brand shadow-sm' : 'text-gray-400')}>{label}</button>
                                ))}
                            </div>
                            {isOrg ? (
                                <div className="space-y-3">
                                    <input aria-label="Organization name" required value={d.orgName} onChange={set('orgName')} placeholder="Organization name" className={authInput} />
                                    <button type="button" onClick={() => setMore(!more)} className={cn('flex w-full items-center justify-between rounded-2xl border p-4 text-left transition-all', more ? 'border-mint-ink bg-mint-ink text-white' : 'border-mint bg-mint/40 text-mint-ink')}>
                                        <span className="flex items-center gap-3"><Sparkles className="size-4" /><span><span className="block text-[10px] font-black uppercase tracking-wider">Add a few details</span><span className="block text-[10px] font-bold opacity-70">Tagline, description and contact emails, or later in Settings</span></span></span>
                                        <ChevronRight className={cn('size-4 transition-transform', more && 'rotate-90')} />
                                    </button>
                                    {more && (
                                        <div className="space-y-3 rounded-[2rem] border border-gray-100 bg-gray-50 p-4">
                                            <input aria-label="Tagline" value={d.tagline} onChange={set('tagline')} placeholder="Tagline, e.g. Bringing Maseru together" className={cn(authInput, 'bg-white text-sm')} />
                                            <textarea aria-label="Description" value={d.description} onChange={set('description')} placeholder="About your organization" rows={3} className={cn(authInput, 'resize-none bg-white text-sm')} />
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <input aria-label="Public email" type="email" value={d.orgEmail} onChange={set('orgEmail')} placeholder="Public email" className={cn(authInput, 'bg-white text-sm')} />
                                                <input aria-label="Contact email for attendees" type="email" value={d.contactEmail} onChange={set('contactEmail')} placeholder="Contact email for attendees" className={cn(authInput, 'bg-white text-sm')} />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <p className="rounded-2xl bg-brand p-4 text-[11px] font-bold italic text-white">Hosting as {d.name}</p>
                            )}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <PhoneInput id="phone" required value={d.phone} onChange={(v) => setD((x) => ({ ...x, phone: v }))} />
                                <NativeSelect aria-label="District" value={d.district} onChange={set('district')} className="py-4">
                                    {site.districts.map((dist) => <option key={dist} value={dist}>{dist}</option>)}
                                </NativeSelect>
                            </div>
                            <div className="flex gap-3 pt-2">
                                <button type="button" onClick={() => go(2)} className={back}>Back</button>
                                <button disabled={loading || d.phone.length !== 8} className={cn(authButton, 'w-2/3')}>{loading ? <LoaderCircle className="size-5 animate-spin" /> : 'Create my account'}</button>
                            </div>
                        </form>
                    )}
                </>
            )}
        </AuthLayout>
    );
}

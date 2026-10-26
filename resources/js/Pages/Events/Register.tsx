import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Check, GraduationCap, Mail, MapPin, MessageCircle, Tag, UserPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Field } from '@/components/field';
import { PhoneInput } from '@/components/phone-input';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { EventData, TierData } from '@/types/event';
import { cn } from '@/lib/utils';
import { type Delivery, type RememberedPerson, forget, recall, remember } from '@/lib/remembered-attendee';

/** Registering for one ticket type: who's coming, and how to send the ticket. */
export default function Register({
    event,
    tier,
    districts,
    submitUrl,
    eventUrl,
}: {
    event: EventData;
    tier: TierData;
    districts: Record<string, string> | null;
    submitUrl: string;
    eventUrl: string;
}) {
    const form = useForm({
        tier_id: tier.id,
        full_name: '',
        email: '',
        phone: '',
        preferred_delivery: 'whatsapp' as Delivery,
        has_whatsapp: true,
        position: '',
        institution: '',
        district: '',
        terms: false,
    });
    const paid = tier.price > 0;
    const workshop = event.type === 'workshop' && districts;
    const action = paid ? 'Continue to Payment' : 'Complete Registration';
    const errors = Object.values(form.errors);

    // What this phone registered with before (kept in the browser only).
    const [saved, setSaved] = useState(false);
    const [people, setPeople] = useState<RememberedPerson[]>([]);
    const [typing, setTyping] = useState(false);
    useEffect(() => {
        const before = recall();
        if (!before) return;
        setSaved(true);
        setPeople(before.people);
        form.setData((d) => ({ ...d, phone: before.phone, email: before.email, preferred_delivery: before.delivery, has_whatsapp: before.delivery !== 'email' }));
        if (before.people[0]) choosePerson(before.people[0]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const wantsWhatsapp = form.data.preferred_delivery !== 'email';
    const wantsEmail = form.data.preferred_delivery !== 'whatsapp';

    /** At least one way: turning off the only one turns on the other. */
    function setDelivery(whatsapp: boolean, email: boolean) {
        const delivery: Delivery = whatsapp && email ? 'both' : email ? 'email' : whatsapp ? 'whatsapp' : wantsWhatsapp ? 'email' : 'whatsapp';
        form.setData((d) => ({ ...d, preferred_delivery: delivery, has_whatsapp: delivery !== 'email' }));
    }

    function choosePerson(p: RememberedPerson) {
        setTyping(false);
        form.setData((d) => ({ ...d, full_name: p.name, position: p.position ?? d.position, institution: p.institution ?? d.institution, district: p.district ?? d.district }));
    }

    function someoneElse() {
        setTyping(true);
        form.setData((d) => ({ ...d, full_name: '', position: '', institution: '', district: '' }));
    }

    function forgetMe() {
        forget();
        setSaved(false);
        setPeople([]);
        setTyping(false);
        form.setData((d) => ({ ...d, full_name: '', email: '', phone: '', position: '', institution: '', district: '' }));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(submitUrl, {
            preserveScroll: true,
            // Remembered once it's accepted, so a mistyped number isn't kept.
            onSuccess: () => remember(
                { phone: form.data.phone, email: wantsEmail ? form.data.email : '', delivery: form.data.preferred_delivery },
                { name: form.data.full_name.trim(), ...(workshop ? { position: form.data.position, institution: form.data.institution, district: form.data.district } : {}) },
            ),
        });
    }

    const select = 'w-full cursor-pointer appearance-none rounded-2xl border-2 border-slate-50 bg-slate-50 px-6 py-4 font-bold text-gray-900 outline-none transition-all focus:border-action focus:bg-white';

    return (
        <GuestLayout wide context={event.organization.name} label="Registration Protocol">
            <Head title={`Register for ${event.name}`} />

            <Link href={eventUrl} className="group mb-8 inline-flex items-center text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 transition-all hover:text-action">
                <ArrowLeft className="mr-2 size-3.5 transition-transform group-hover:-translate-x-1" />
                Back to Event Details
            </Link>

            {errors.length > 0 && (
                <Alert variant="destructive" className="mb-8">
                    <h4 className="mb-2 text-[10px] font-black uppercase tracking-widest text-rose-900">Registration Errors</h4>
                    <ul className="list-inside list-disc text-xs">{errors.map((error) => <li key={error}>{error}</li>)}</ul>
                </Alert>
            )}

            <div className="grid grid-cols-1 items-start gap-10 pb-32 lg:grid-cols-12 lg:pb-0">
                <div className="overflow-hidden rounded-[2.5rem] border border-gray-100 bg-white shadow-2xl shadow-gray-200/50 lg:col-span-7">
                    <div className="border-b border-gray-50 p-8 sm:p-10">
                        <h2 className="mb-2 text-4xl font-black uppercase italic leading-none tracking-tighter text-gray-900">Register</h2>
                        <p className="font-medium text-gray-500">Securing your spot for <span className="font-bold text-action">{event.name}</span></p>
                    </div>

                    <form id="regForm" onSubmit={submit} className="space-y-10 p-8 sm:p-10">
                        {saved && (
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50 px-5 py-4">
                                <p className="text-[13px] font-bold text-gray-700">Welcome back. We've filled in your details from last time.</p>
                                <button type="button" onClick={forgetMe} className="text-[11px] font-black uppercase tracking-widest text-gray-400 underline-offset-4 hover:text-action hover:underline">
                                    Forget me on this phone
                                </button>
                            </div>
                        )}

                        {/* 1. How the ticket reaches them: email is asked for only when it's needed. */}
                        <section className="space-y-4" aria-labelledby="how">
                            <Step n={1} id="how">How would you like to receive your ticket?</Step>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <Choice checked={wantsWhatsapp} onChange={(on) => setDelivery(on, wantsEmail)} icon={<MessageCircle className="size-5 text-emerald-600" />} title="WhatsApp" text="On your phone, straight away" />
                                <Choice checked={wantsEmail} onChange={(on) => setDelivery(wantsWhatsapp, on)} icon={<Mail className="size-5 text-brand" />} title="Email" text="With the ticket attached" />
                            </div>
                            {form.errors.preferred_delivery && <p className="ml-1 text-[11px] font-bold text-rose-500">{form.errors.preferred_delivery}</p>}
                            <p className="ml-1 text-[11px] font-medium text-gray-400">Either way, you can open your ticket on the next page and find it again with your phone number.</p>
                        </section>

                        {/* 2. Their contact details. */}
                        <section className="space-y-4" aria-labelledby="contact">
                            <Step n={2} id="contact">Your contact details</Step>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <Field
                                    label={<>Phone Number <span className="text-rose-500">*</span></>}
                                    htmlFor="phone"
                                    error={form.errors.phone}
                                    hint={wantsWhatsapp ? 'Your WhatsApp number' : 'To find your ticket again'}
                                >
                                    <PhoneInput id="phone" required value={form.data.phone} onChange={(v) => form.setData('phone', v)} invalid={!!form.errors.phone} />
                                </Field>
                                {wantsEmail && (
                                    <Field label={<>Email Address <span className="text-rose-500">*</span></>} htmlFor="email" error={form.errors.email}>
                                        <Input id="email" type="email" required autoComplete="email" placeholder="lerato@example.com" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                    </Field>
                                )}
                            </div>
                        </section>

                        {/* 3. Whose ticket: names used on this phone before, or someone new. */}
                        <section className="space-y-4" aria-labelledby="who">
                            <Step n={3} id="who">Who's this ticket for?</Step>
                            {people.length > 0 && (
                                <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Names used before">
                                    {people.map((p) => (
                                        <button
                                            key={p.name}
                                            type="button"
                                            role="radio"
                                            aria-checked={!typing && form.data.full_name === p.name}
                                            onClick={() => choosePerson(p)}
                                            className={cn(
                                                'rounded-full border-2 px-4 py-2.5 text-[13px] font-bold transition-all',
                                                !typing && form.data.full_name === p.name ? 'border-action bg-action-soft text-action-ink' : 'border-slate-100 bg-slate-50 text-gray-700 hover:border-gray-300',
                                            )}
                                        >
                                            {p.name}
                                        </button>
                                    ))}
                                    <button
                                        type="button"
                                        role="radio"
                                        aria-checked={typing}
                                        onClick={someoneElse}
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-full border-2 border-dashed px-4 py-2.5 text-[13px] font-bold transition-all',
                                            typing ? 'border-action text-action-ink' : 'border-slate-200 text-gray-500 hover:border-gray-400',
                                        )}
                                    >
                                        <UserPlus className="size-4" />Someone else
                                    </button>
                                </div>
                            )}
                            {(typing || people.length === 0) && (
                                <Field label={<>Full Name <span className="text-rose-500">*</span></>} htmlFor="full_name" error={form.errors.full_name} hint="The name on the ticket">
                                    <Input id="full_name" required autoComplete="name" placeholder="e.g. Lerato Molapo" value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} />
                                </Field>
                            )}
                            {!typing && people.length > 0 && form.errors.full_name && <p className="ml-1 text-[11px] font-bold text-rose-500">{form.errors.full_name}</p>}
                        </section>

                        {workshop && (
                            <div className="space-y-6 border-t border-gray-50 pt-6">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-8 items-center justify-center rounded-lg bg-brand/10"><GraduationCap className="size-4 text-brand" /></div>
                                    <div>
                                        <h3 className="text-xs font-black uppercase tracking-[0.3em] text-gray-400">Workshop Details</h3>
                                        <p className="mt-1 text-[10px] font-bold uppercase text-action">Required for workshop registration</p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <Field label={<>Position / Title <span className="text-rose-500">*</span></>} htmlFor="position" error={form.errors.position}>
                                        <Input id="position" required placeholder="e.g. Teacher, Principal, Officer" value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} />
                                    </Field>
                                    <Field label={<>Institution <span className="text-rose-500">*</span></>} htmlFor="institution" error={form.errors.institution}>
                                        <Input id="institution" required placeholder="e.g. Maseru High School" value={form.data.institution} onChange={(e) => form.setData('institution', e.target.value)} />
                                    </Field>
                                </div>
                                <Field label={<>District <span className="text-rose-500">*</span></>} htmlFor="district" error={form.errors.district}>
                                    <select id="district" required value={form.data.district} onChange={(e) => form.setData('district', e.target.value)} className={select}>
                                        <option value="" disabled>Select your district...</option>
                                        {Object.entries(districts).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                                    </select>
                                </Field>
                            </div>
                        )}

                        {/* One purchase is one ticket: the same QR admits every person in the group. */}
                        {tier.admits > 1 && (
                            <div className="border-t border-gray-50 pt-6">
                                <div className="flex items-start gap-3 rounded-2xl border border-amber-100 bg-amber-50 p-4">
                                    <Users className="mt-0.5 size-4 text-amber-500" />
                                    <p className="text-[11px] font-black uppercase leading-relaxed tracking-tight text-amber-800">
                                        Group ticket for {tier.admits} people. You'll get one QR code — each person in your group is scanned with the same code.
                                    </p>
                                </div>
                            </div>
                        )}

                        {!paid && (
                            <div className="rounded-[2rem] border-2 border-emerald-100 bg-gradient-to-br from-emerald-50 to-teal-50 p-8 text-center shadow-sm animate-in fade-in zoom-in duration-500">
                                <div className="mb-4 text-4xl">🎉</div>
                                <h3 className="mb-2 text-xl font-black uppercase tracking-tight text-emerald-900">This is a Free Ticket!</h3>
                                <p className="text-sm font-bold uppercase tracking-widest text-emerald-700 opacity-80">No payment required. Just complete your registration.</p>
                            </div>
                        )}

                        <div className="space-y-6 border-t border-gray-50 pt-10">
                            <label className="flex cursor-pointer items-start">
                                <input type="checkbox" required checked={form.data.terms} onChange={(e) => form.setData('terms', e.target.checked)} className="mt-1 size-5 rounded accent-action" />
                                <span className="ml-4 text-[11px] font-bold uppercase tracking-wide text-gray-500">
                                    I agree to the <a href={route('terms')} target="_blank" rel="noopener" className="text-brand underline hover:text-action">ticket terms</a>.
                                </span>
                            </label>
                            <Button type="submit" size="lg" className="hidden w-full lg:flex" disabled={form.processing}>{action}</Button>
                        </div>
                    </form>
                </div>

                <div className="sticky top-20 hidden lg:col-span-5 lg:block">
                    <div className="overflow-hidden rounded-[2.5rem] bg-gray-900 shadow-2xl">
                        <div className="border-b border-white/5 p-8 text-white">
                            <span className="text-[9px] font-black uppercase tracking-[0.4em] text-action">Confirmed Selection</span>
                            <h2 className="mt-1 text-2xl font-black uppercase italic tracking-tighter">{tier.name}</h2>
                        </div>
                        <div className="space-y-8 p-8">
                            <div className="flex items-end justify-between border-b border-white/5 pb-6">
                                <span className="text-[10px] font-black uppercase tracking-widest text-gray-400">Investment</span>
                                <span className="text-3xl font-black tracking-tighter text-white">{tier.priceLabel}</span>
                            </div>
                            <div className="space-y-4 text-white/60">
                                <Detail icon={<CalendarDays className="size-3.5 text-action" />}>{event.shortDate}</Detail>
                                {event.venue && <Detail icon={<MapPin className="size-3.5 text-action" />}>{event.venue}</Detail>}
                                <Detail icon={<Tag className="size-3.5 text-action" />}>{event.organization.name}</Detail>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Phones: the price and the button stay at the bottom of the screen. */}
            <div className="fixed inset-x-0 bottom-0 z-50 border-t border-gray-100 bg-white px-6 py-5 shadow-[0_-15px_40px_rgba(0,0,0,0.08)] lg:hidden">
                <div className="flex items-center justify-between gap-6">
                    <div className="flex flex-col">
                        <span className="mb-1 text-[9px] font-black uppercase leading-none tracking-widest text-gray-400">Payable</span>
                        <span className="text-3xl font-black leading-none tracking-tighter text-gray-900">{tier.priceLabel}</span>
                    </div>
                    <Button type="submit" form="regForm" className="flex-1 text-[11px] tracking-[0.3em]" disabled={form.processing}>{paid ? 'Continue to Payment' : 'Register'}</Button>
                </div>
            </div>
        </GuestLayout>
    );
}

function Step({ n, id, children }: { n: number; id: string; children: React.ReactNode }) {
    return (
        <h3 id={id} className="flex items-center gap-3 text-[15px] font-black text-gray-900">
            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-action-soft text-[12px] font-black text-action-ink">{n}</span>
            {children}
        </h3>
    );
}

/** A way to get the ticket: on or off, more than one allowed. */
function Choice({ checked, onChange, icon, title, text }: { checked: boolean; onChange: (on: boolean) => void; icon: React.ReactNode; title: string; text: string }) {
    return (
        <label className={cn('flex cursor-pointer items-center gap-4 rounded-2xl border-2 p-4 transition-all has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-action/40', checked ? 'border-action bg-white shadow-sm' : 'border-slate-100 bg-slate-50 hover:border-gray-300')}>
            <input type="checkbox" className="sr-only" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm">{icon}</span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-black text-gray-900">{title}</span>
                <span className="block text-[11px] font-medium text-gray-500">{text}</span>
            </span>
            <span className={cn('flex size-6 shrink-0 items-center justify-center rounded-full border-2', checked ? 'border-action bg-action text-white' : 'border-gray-300')}>
                {checked && <Check className="size-3.5" />}
            </span>
        </label>
    );
}

function Detail({ icon, children }: { icon: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="flex items-center gap-3">
            {icon}
            <span className="truncate text-[10px] font-black uppercase tracking-widest">{children}</span>
        </div>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CircleCheck, GraduationCap, Mail, MapPin, MessageCircle, Tag, Users } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Field } from '@/components/field';
import { PhoneInput, formatLocal } from '@/components/phone-input';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { EventData, TierData } from '@/types/event';

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
        has_whatsapp: false,
        position: '',
        institution: '',
        district: '',
        terms: false,
    });
    const paid = tier.price > 0;
    const workshop = event.type === 'workshop' && districts;
    const action = paid ? 'Continue to Payment' : 'Complete Registration';
    const errors = Object.values(form.errors);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(submitUrl, { preserveScroll: true });
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

                    <form id="regForm" onSubmit={submit} className="space-y-8 p-8 sm:p-10">
                        <div className="space-y-6">
                            <Field label={<>Full Name <span className="text-rose-500">*</span></>} htmlFor="full_name" error={form.errors.full_name}>
                                <Input id="full_name" required placeholder="e.g. Lerato Molapo" value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} />
                            </Field>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <Field
                                    label="Email Address"
                                    htmlFor="email"
                                    error={form.errors.email}
                                    hint={<span className="font-bold uppercase tracking-wider text-action/60"><Mail className="mr-1 inline size-3" />Ticket sent here</span>}
                                >
                                    <Input id="email" type="email" placeholder="lerato@example.com" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                </Field>
                                <Field label={<>Phone Number <span className="text-rose-500">*</span></>} htmlFor="phone" error={form.errors.phone}>
                                    <PhoneInput id="phone" required value={form.data.phone} onChange={(v) => form.setData('phone', v)} invalid={!!form.errors.phone} />
                                </Field>
                            </div>

                            <div className="flex items-start gap-4 rounded-2xl border border-brand/10 bg-brand/5 p-5">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand"><Mail className="size-4 text-white" /></div>
                                <div>
                                    <p className="mb-1 text-xs font-black uppercase tracking-wider text-brand">Getting Your Ticket</p>
                                    <p className="text-xs font-medium leading-relaxed text-gray-500">
                                        If you provide an email above, your ticket will be sent there once your registration is confirmed.
                                        Either way, you can download it directly from the ticket page right after you register.
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-[2rem] border border-emerald-100 bg-gradient-to-br from-emerald-50 to-teal-50 p-6 sm:p-8">
                                <div className="mb-6 flex items-center">
                                    <div className="mr-4 flex size-12 items-center justify-center rounded-2xl bg-white shadow-sm"><MessageCircle className="size-6 text-emerald-500" /></div>
                                    <div>
                                        <h4 className="text-lg font-black text-emerald-900">WhatsApp Delivery</h4>
                                        <p className="text-xs font-medium text-emerald-700">Instant ticket access on your phone</p>
                                    </div>
                                </div>
                                <label className="group flex cursor-pointer items-start rounded-2xl border-2 border-emerald-200 bg-white/60 p-5 transition-all hover:border-emerald-400 hover:bg-white">
                                    <input type="checkbox" checked={form.data.has_whatsapp} onChange={(e) => form.setData('has_whatsapp', e.target.checked)} className="mt-1 size-5 rounded-lg accent-emerald-600" />
                                    <div className="ml-4">
                                        <span className="text-sm font-black uppercase text-emerald-900">Send via WhatsApp</span>
                                        <p className="mt-1 text-[11px] font-bold uppercase tracking-tight text-emerald-600">Instant delivery & easy access</p>
                                    </div>
                                </label>
                                {form.data.has_whatsapp && (
                                    <div className="mt-4 flex items-center rounded-xl bg-emerald-600 p-3 px-5 text-white shadow-lg shadow-emerald-200">
                                        <CircleCheck className="mr-3 size-4" />
                                        <p className="text-[10px] font-black uppercase tracking-widest">WhatsApp Enabled for +266 {formatLocal(form.data.phone)}</p>
                                    </div>
                                )}
                            </div>
                        </div>

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

function Detail({ icon, children }: { icon: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="flex items-center gap-3">
            {icon}
            <span className="truncate text-[10px] font-black uppercase tracking-widest">{children}</span>
        </div>
    );
}

import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Gift, ImageIcon, Plus, Sparkles, Trash2, Upload, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { EventWriter } from '@/components/organizer/event-writer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { cn, money } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Tier {
    id: number | null;
    tier_name: string;
    price: number | string | null;
    quantity_available: number | string | null;
    description: string;
    quantity_per_purchase: number | string;
    color: string;
    is_active: boolean;
    sold: number;
    taken: number;
}

interface EventData {
    id: number | null;
    name: string;
    tagline: string;
    description: string;
    category: string;
    city: string;
    isPublic: boolean;
    date: string;
    time: string;
    deadlineDate: string;
    deadlineTime: string;
    venue: string;
    location: string;
    paymentMode: 'free' | 'paid';
    allowInstallments: boolean;
    depositPercent: number;
    installmentInstructions: string;
    paymentWindowHours: number | null;
    status: string;
    feesSponsored: boolean;
    banner: string | null;
    hasTickets: boolean;
}

interface Account {
    id: number;
    label: string;
    number: string | null;
    isDefault: boolean;
}

interface Props {
    event: EventData;
    accounts: Account[];
    selectedAccounts: number[];
    onlineDrivers: string[];
    onlineCatalog: Record<string, string>;
    onlineMethods: string[];
    modeLocked: boolean;
    tiers: Tier[];
    expected: number | null;
    freeTaken: number;
    statuses: Record<string, string>;
    categories: { value: string; label: string }[];
    colours: Record<string, string>;
    fees: { percent: number; perPerson: number };
    windowDefault: number | null;
    canDelete: boolean;
}

const card = 'space-y-5 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8';
const heading = 'text-[13px] font-black uppercase tracking-widest text-brand';
const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';
const smallLabel = 'mb-2 ml-1 block text-[10px] font-black uppercase tracking-widest text-gray-400';
const optional = <span className="normal-case text-gray-300">(optional)</span>;

/** Creating and editing an event: the basics, when and where, tickets and payment, publishing. */
export default function EventForm(props: Props) {
    const { event, accounts, onlineDrivers, onlineCatalog, modeLocked, freeTaken, statuses, categories, colours, fees, windowDefault, canDelete } = props;
    const { site } = usePage<SharedProps>().props;
    const editing = event.id !== null;
    const direct = event.feesSponsored; // paying the organizer directly is only for events VENTIQ sponsors

    const form = useForm({
        name: event.name,
        tagline: event.tagline,
        description: event.description,
        category: event.category,
        city: event.city,
        banner: null as File | null,
        is_public: event.isPublic,
        event_date: event.date,
        event_time: event.time,
        registration_deadline_date: event.deadlineDate,
        registration_deadline_time: event.deadlineTime,
        venue: event.venue,
        location: event.location,
        payment_mode: event.paymentMode,
        expected_attendance: (props.expected ?? '') as number | string,
        tiers: props.tiers,
        online_methods: props.onlineMethods,
        account_ids: props.selectedAccounts,
        allow_installments: event.allowInstallments,
        minimum_deposit_percentage: event.depositPercent as number | string,
        installment_instructions: event.installmentInstructions,
        payment_window_hours: (event.paymentWindowHours ?? '') as number | string,
        status: event.status,
    });
    const d = form.data;
    const errors = form.errors as Record<string, string>;
    const [banner, setBanner] = useState(event.banner);
    const [writing, setWriting] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const setTier = (i: number, patch: Partial<Tier>) => form.setData('tiers', d.tiers.map((t, j) => (j === i ? { ...t, ...patch } : t)));
    function addTier() {
        const used = d.tiers.map((t) => t.color.toUpperCase());
        const color = Object.keys(colours).find((c) => !used.includes(c)) ?? Object.keys(colours)[0];
        form.setData('tiers', [...d.tiers, { id: null, tier_name: '', price: null, quantity_available: null, description: '', quantity_per_purchase: 1, color, is_active: true, sold: 0, taken: 0 }]);
    }

    // If everything sells: the people, the sales, VENTIQ's fees, and what the organizer keeps.
    const order = useMemo(() => {
        const rows = d.payment_mode === 'free'
            ? [{ people: +d.expected_attendance || 0, sales: 0 }]
            : d.tiers.filter((t) => t.is_active).map((t) => {
                const n = +(t.quantity_available ?? 0) || 0;
                return { people: n * Math.max(1, +t.quantity_per_purchase || 1), sales: n * (+(t.price ?? 0) || 0) };
            });
        const people = rows.reduce((a, r) => a + r.people, 0);
        const sales = rows.reduce((a, r) => a + r.sales, 0);
        const fee = event.feesSponsored ? 0 : rows.reduce((a, r) => a + Math.round(r.sales * fees.percent * 100) / 100 + r.people * fees.perPerson, 0);
        return { people, sales, fees: fee, keep: sales - fee };
    }, [d.payment_mode, d.expected_attendance, d.tiers, event.feesSponsored, fees]);

    const online = d.online_methods.length > 0;
    const chosenAccounts = direct ? accounts.filter((a) => d.account_ids.includes(a.id)) : [];

    function submit(e: React.FormEvent) {
        e.preventDefault();
        // Send only what applies, as the old form did by disabling fields.
        form.transform((data) => {
            const out: Record<string, unknown> = { ...data, tiers: data.tiers.map(({ sold: _s, taken: _t, ...t }) => t) };
            if (data.payment_mode === 'free') delete out.tiers;
            else delete out.expected_attendance;
            if (!direct) delete out.account_ids;
            if (modeLocked) delete out.payment_mode;
            if (!data.banner) delete out.banner;
            return editing ? { ...out, _method: 'put' } : out;
        });
        form.post(editing ? route('organizer.events.update', event.id!) : route('organizer.events.store'), {
            forceFormData: true,
            // Saved: start again from what the server has (new ticket types now have ids).
            preserveState: 'errors',
            preserveScroll: 'errors',
        });
    }

    const servicePct = `${+(fees.percent * 100).toFixed(2)}%`;
    const perPerson = money(fees.perPerson);
    const errorList = Object.values(errors);

    return (
        <OrganizerLayout
            title={editing ? 'Edit event' : 'Create an event'}
            subtitle={editing ? event.name : 'Fill in the details, choose how people pay, and publish when ready.'}
            crumbs={editing ? [['Events', route('organizer.home')], [event.name, route('organizer.events.attendees', event.id!)], ['Edit']] : [['Events', route('organizer.home')], ['New event']]}
            draftEventId={editing && event.status === 'draft' ? event.id : null}
        >
            <Head title={editing ? `Edit ${event.name}` : 'Create event'} />
            <div className="max-w-3xl">
                {errorList.length > 0 && (
                    <div className="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700">
                        <p className="mb-1">Please fix the following:</p>
                        <ul className="ml-5 list-disc font-medium">{errorList.map((m, i) => <li key={i}>{m}</li>)}</ul>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6">
                    {/* 1. The basics */}
                    <section className={card}>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className={heading}>1 · The basics</h2>
                            <button type="button" onClick={() => setWriting(true)} className="inline-flex items-center gap-1.5 rounded-full bg-lilac px-4 py-2 text-[10px] font-black uppercase tracking-widest text-lilac-ink hover:bg-lilac-ink hover:text-white">
                                <Sparkles className="size-3.5" />Write it with Ventiq Assist
                            </button>
                        </div>
                        <Field label="Event name" htmlFor="name" error={errors.name}>
                            <Input id="name" required maxLength={255} placeholder="e.g. Maseru Youth Summit" className={field} value={d.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label={<>Tagline {optional}</>} htmlFor="tagline" error={errors.tagline}>
                            <Input id="tagline" maxLength={255} placeholder="One line people see under the name" className={field} value={d.tagline} onChange={(e) => form.setData('tagline', e.target.value)} />
                        </Field>
                        <Field label={<>Description {optional}</>} htmlFor="description" error={errors.description}>
                            <Textarea id="description" rows={5} value={d.description} onChange={(e) => form.setData('description', e.target.value)} />
                        </Field>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Category" htmlFor="category" error={errors.category}>
                                <NativeSelect id="category" required value={d.category} onChange={(e) => form.setData('category', e.target.value)}>
                                    <option value="">Choose…</option>
                                    {categories.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                                </NativeSelect>
                            </Field>
                            <Field label="District" htmlFor="city" error={errors.city}>
                                <NativeSelect id="city" required value={d.city} onChange={(e) => form.setData('city', e.target.value)}>
                                    {site.districts.map((c) => <option key={c} value={c}>{c}</option>)}
                                </NativeSelect>
                            </Field>
                        </div>
                        <div>
                            <span className={smallLabel}>Poster or flyer <span className="normal-case text-gray-300">(optional, up to 10 MB)</span></span>
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex h-32 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-slate-200 bg-slate-50">
                                    {banner ? <img src={banner} alt="Poster" className="size-full object-cover" /> : <ImageIcon className="size-7 text-slate-300" />}
                                </div>
                                <div className="min-w-0 space-y-1.5">
                                    <label htmlFor="banner" className="inline-flex cursor-pointer items-center gap-2 rounded-full bg-action px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action-ink">
                                        <Upload className="size-3.5" />{banner ? 'Change poster' : 'Upload poster'}
                                    </label>
                                    <p className="truncate text-[11px] text-gray-400">{d.banner?.name || 'PNG, JPG or WebP'}</p>
                                </div>
                            </div>
                            <input
                                id="banner"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] ?? null;
                                    form.setData('banner', file);
                                    setBanner(file ? URL.createObjectURL(file) : event.banner);
                                }}
                            />
                            {errors.banner && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-500">{errors.banner}</p>}
                        </div>
                        <label className="flex items-center gap-3 text-[13px] font-bold text-gray-700">
                            <input type="checkbox" checked={d.is_public} onChange={(e) => form.setData('is_public', e.target.checked)} className="size-5 rounded accent-action" />
                            Show this event publicly on Ventiq
                        </label>
                    </section>

                    {/* 2. When and where */}
                    <section className={card}>
                        <h2 className={heading}>2 · When and where</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Date" htmlFor="event_date" error={errors.event_date}>
                                <Input id="event_date" type="date" required className={field} value={d.event_date} onChange={(e) => form.setData('event_date', e.target.value)} />
                            </Field>
                            <Field label="Start time" htmlFor="event_time" error={errors.event_time}>
                                <Input id="event_time" type="time" required className={field} value={d.event_time} onChange={(e) => form.setData('event_time', e.target.value)} />
                            </Field>
                            <Field label={<>Registration closes {optional}</>} htmlFor="registration_deadline_date" error={errors.registration_deadline_date}>
                                <Input id="registration_deadline_date" type="date" className={field} value={d.registration_deadline_date} onChange={(e) => form.setData('registration_deadline_date', e.target.value)} />
                            </Field>
                            <Field label="at" htmlFor="registration_deadline_time" error={errors.registration_deadline_time}>
                                <Input id="registration_deadline_time" type="time" className={field} value={d.registration_deadline_time} onChange={(e) => form.setData('registration_deadline_time', e.target.value)} />
                            </Field>
                            <Field label={<>Venue {optional}</>} htmlFor="venue" error={errors.venue}>
                                <Input id="venue" maxLength={255} placeholder="e.g. Maseru Convention Centre" className={field} value={d.venue} onChange={(e) => form.setData('venue', e.target.value)} />
                            </Field>
                        </div>
                        <Field label={<>Address {optional}</>} htmlFor="location" error={errors.location}>
                            <Textarea id="location" rows={2} value={d.location} onChange={(e) => form.setData('location', e.target.value)} />
                        </Field>
                    </section>

                    {/* 3. Tickets and payment */}
                    <section className={cn(card, 'space-y-6')}>
                        <h2 className={heading}>3 · Tickets and payment</h2>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {([['free', 'Free', 'People register at no cost and get their ticket straight away.'], ['paid', 'Paid', 'People pay for a ticket. You set the prices below.']] as const).map(([value, title, hint]) => (
                                <button
                                    key={value}
                                    type="button"
                                    disabled={modeLocked}
                                    onClick={() => form.setData('payment_mode', value)}
                                    className={cn('h-full rounded-2xl border-2 p-5 text-left transition-all disabled:cursor-not-allowed', d.payment_mode === value ? 'border-action bg-white' : 'border-slate-100 bg-slate-50', modeLocked && 'opacity-60')}
                                >
                                    <p className="text-[14px] font-black text-gray-900">{title}</p>
                                    <p className="mt-1 text-[12px] font-medium text-gray-500">{hint}</p>
                                </button>
                            ))}
                        </div>
                        {modeLocked && <p className="text-[11px] font-medium text-gray-400">People have already registered, so this can no longer switch between free and paid.</p>}

                        <div className="rounded-2xl bg-lilac p-4 text-[12px] font-medium text-lilac-ink">
                            {event.feesSponsored ? (
                                <p className="font-black"><Gift className="mr-1 inline size-4" />VENTIQ is sponsoring this event's fees: nothing will be charged.</p>
                            ) : (
                                <>
                                    <p><strong>VENTIQ fees:</strong> a {servicePct} service fee on each ticket price and a {perPerson} operational fee per person, on every ticket once it's active.</p>
                                    <p className="mt-1">
                                        {d.payment_mode === 'paid'
                                            ? "Fees come off your weekly payout; on tickets you sell for cash yourself, they're invoiced."
                                            : `On a free event that's ${perPerson} per person, invoiced to you.`}
                                    </p>
                                </>
                            )}
                        </div>

                        {d.payment_mode === 'free' ? (
                            <div className="space-y-4">
                                <Field label="How many people are you expecting?" htmlFor="expected_attendance" error={errors.expected_attendance} hint={freeTaken ? `${freeTaken} registered so far.` : undefined}>
                                    <Input id="expected_attendance" type="number" required min={Math.max(1, freeTaken)} placeholder="e.g. 150" className={cn(field, 'sm:max-w-xs')} value={d.expected_attendance} onChange={(e) => form.setData('expected_attendance', e.target.value)} />
                                </Field>
                                <OrderSummary order={order} paid={false} sponsored={event.feesSponsored} />
                            </div>
                        ) : (
                            <div className="space-y-6">
                                <div>
                                    <p className={smallLabel}>Ticket types</p>
                                    {errors.tiers && <p className="mb-2 ml-1 text-[11px] font-bold text-rose-500">{errors.tiers}</p>}
                                    <div className="space-y-3">
                                        {d.tiers.map((tier, i) => (
                                            <TierRow
                                                key={tier.id ?? `new-${i}`}
                                                tier={tier}
                                                index={i}
                                                colours={colours}
                                                errors={errors}
                                                removable={d.tiers.length > 1}
                                                onChange={(patch) => setTier(i, patch)}
                                                onRemove={() => form.setData('tiers', d.tiers.filter((_, j) => j !== i))}
                                            />
                                        ))}
                                    </div>
                                    <button type="button" onClick={addTier} className="mt-3 inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-brand hover:border-brand">
                                        <Plus className="size-3.5" />Add ticket type
                                    </button>
                                </div>

                                <OrderSummary order={order} paid sponsored={event.feesSponsored} />

                                <div className="space-y-3">
                                    <p className={smallLabel}>How people pay</p>
                                    <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <p className="text-[14px] font-black text-gray-900">
                                            Pay online through VENTIQ <span className="ml-1 rounded-full bg-lilac px-2 py-0.5 text-[10px] font-bold uppercase text-lilac-ink">Recommended</span>
                                        </p>
                                        <p className="mb-3 text-[12px] font-medium text-gray-500">VENTIQ collects the payment and the ticket activates automatically; VENTIQ's fees come off your payout. Choose which ways to offer.</p>
                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                            {Object.entries(onlineCatalog).map(([method, label]) => {
                                                const live = onlineDrivers.includes(method);
                                                const on = live && d.online_methods.includes(method);
                                                return (
                                                    <label key={method} className={cn('flex items-center gap-3 rounded-xl border-2 p-3 transition-all', live ? 'cursor-pointer bg-white' : 'cursor-not-allowed border-transparent bg-slate-100 opacity-60', live && (on ? 'border-action' : 'border-slate-100'))}>
                                                        <input
                                                            type="checkbox"
                                                            disabled={!live}
                                                            checked={on}
                                                            onChange={(e) => form.setData('online_methods', e.target.checked ? [...d.online_methods, method] : d.online_methods.filter((m) => m !== method))}
                                                            className="size-4 accent-action"
                                                        />
                                                        <span className="min-w-0">
                                                            <span className="block text-[13px] font-black text-gray-900">{label}</span>
                                                            {!live && <span className="block text-[10px] font-black uppercase tracking-widest text-gray-400">Coming soon</span>}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {direct ? (
                                        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <p className="text-[14px] font-black text-gray-900">Pay directly to you</p>
                                            <p className="mb-3 text-[12px] font-medium text-gray-500">Attendees pay into your account and submit their reference; you confirm it, then the ticket activates.</p>
                                            {accounts.length ? accounts.map((a) => (
                                                <label key={a.id} className="flex items-center gap-3 py-1.5 text-[13px] font-bold text-gray-700">
                                                    <input
                                                        type="checkbox"
                                                        checked={d.account_ids.includes(a.id)}
                                                        onChange={(e) => form.setData('account_ids', e.target.checked ? [...d.account_ids, a.id] : d.account_ids.filter((id) => id !== a.id))}
                                                        className="size-4 accent-action"
                                                    />
                                                    {a.label}
                                                    {a.number && <span className="font-mono font-medium text-gray-400">{a.number}</span>}
                                                    {a.isDefault && <span className="text-[9px] font-black uppercase text-action">Default</span>}
                                                </label>
                                            )) : <p className="text-[12px] font-medium text-gray-500">You haven't added any accounts yet.</p>}
                                            {errors.account_ids && <p className="mt-1 text-[11px] font-bold text-rose-500">{errors.account_ids}</p>}
                                            <a href={route('organizer.accounts.index')} target="_blank" rel="noopener" className="mt-2 inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-brand hover:text-action">
                                                <Wallet className="size-3.5" />Manage payment accounts
                                            </a>
                                        </div>
                                    ) : (
                                        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                            <p className="text-[14px] font-black text-gray-900">Payments come to VENTIQ</p>
                                            <p className="text-[12px] font-medium text-gray-500">Attendees pay online, or with EcoCash to VENTIQ's merchant code, and get their ticket automatically. VENTIQ pays you out every week, with its fees taken off, so you never have to confirm payments yourself. Paying into your own accounts is only for events VENTIQ sponsors.</p>
                                        </div>
                                    )}

                                    <div className="rounded-2xl border-2 border-dashed border-slate-200 p-4 text-[13px]">
                                        <p className="mb-2 font-black text-gray-800">Your attendees will be offered:</p>
                                        {direct && !online && chosenAccounts.length === 0 && <p className="font-bold text-rose-600">Nothing yet. Choose at least one way to pay.</p>}
                                        {online && (
                                            <div className="mb-2">
                                                <p className="font-bold text-gray-700">Pay online</p>
                                                <ul className="ml-5 list-disc text-gray-600">{d.online_methods.map((m) => <li key={m}>{onlineCatalog[m]} via VENTIQ</li>)}</ul>
                                            </div>
                                        )}
                                        {!direct && (
                                            <div className="mb-2">
                                                <p className="font-bold text-gray-700">Pay with EcoCash</p>
                                                <ul className="ml-5 list-disc text-gray-600"><li>To VENTIQ's merchant code, from their EcoCash menu</li></ul>
                                            </div>
                                        )}
                                        {chosenAccounts.length > 0 && (
                                            <div>
                                                <p className="font-bold text-gray-700">Pay directly to you</p>
                                                <ul className="ml-5 list-disc text-gray-600">{chosenAccounts.map((a) => <li key={a.id}>{a.number ? `${a.label} — ${a.number}` : a.label}</li>)}</ul>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="flex items-center gap-3 text-[13px] font-bold text-gray-700 sm:col-span-2">
                                        <input type="checkbox" checked={d.allow_installments} onChange={(e) => form.setData('allow_installments', e.target.checked)} className="size-5 accent-action" />
                                        Let people pay a deposit first and the rest later
                                    </label>
                                    {d.allow_installments && (
                                        <>
                                            <Field label="Minimum deposit (%)" htmlFor="minimum_deposit_percentage" error={errors.minimum_deposit_percentage}>
                                                <Input id="minimum_deposit_percentage" type="number" min={1} max={100} className={field} value={d.minimum_deposit_percentage} onChange={(e) => form.setData('minimum_deposit_percentage', e.target.value)} />
                                            </Field>
                                            <Field label="Instructions for the balance" htmlFor="installment_instructions" error={errors.installment_instructions}>
                                                <Input id="installment_instructions" className={field} value={d.installment_instructions} onChange={(e) => form.setData('installment_instructions', e.target.value)} />
                                            </Field>
                                        </>
                                    )}
                                    <Field label="Hold unpaid tickets for (hours)" htmlFor="payment_window_hours" error={errors.payment_window_hours} hint="Unpaid tickets release their place after this. Leave empty for the default.">
                                        <Input id="payment_window_hours" type="number" min={1} max={720} placeholder={windowDefault ? `Ventiq default: ${windowDefault}` : 'No limit'} className={field} value={d.payment_window_hours} onChange={(e) => form.setData('payment_window_hours', e.target.value)} />
                                    </Field>
                                </div>
                            </div>
                        )}
                    </section>

                    {/* 4. Publish */}
                    <section className={cn(card, 'space-y-4')}>
                        <h2 className={heading}>4 · Publish</h2>
                        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Status">
                            {Object.entries(statuses).map(([value, text]) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="radio"
                                    aria-checked={d.status === value}
                                    onClick={() => form.setData('status', value)}
                                    className={cn('rounded-full border-2 px-4 py-2 text-[11px] font-black uppercase tracking-widest', d.status === value ? 'border-action bg-white text-action' : 'border-slate-100 bg-slate-50 text-gray-600')}
                                >
                                    {text}
                                </button>
                            ))}
                        </div>
                        <p className="text-[12px] font-medium text-gray-500">Drafts aren't visible to the public. Publishing makes registration open straight away.</p>
                    </section>

                    <div className="flex items-center justify-end gap-3">
                        <a href={route('organizer.home')} className="px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">Cancel</a>
                        <Button type="submit" variant="brand" className="px-6 py-4 text-[11px]" disabled={form.processing}>{editing ? 'Save changes' : 'Create event'}</Button>
                    </div>
                </form>

                {editing && canDelete && (
                    <section className="mt-10 rounded-[1.5rem] border border-rose-100 bg-white p-6">
                        <h2 className="text-[13px] font-black uppercase tracking-widest text-rose-700">Delete event</h2>
                        {event.hasTickets ? (
                            <p className="mt-2 text-[12px] font-medium text-gray-500">
                                People have registered, so this event can't be deleted: their tickets and payments would go with it. To call it off, choose <strong>Cancelled</strong> under Publish and save. Registration closes and everything is kept.
                            </p>
                        ) : (
                            <>
                                <p className="mt-2 text-[12px] font-medium text-gray-500">Nobody has registered yet, so the event can be deleted for good.</p>
                                <Button type="button" variant="brand" className="mt-4 bg-rose-600 text-[10px] hover:bg-rose-700" onClick={() => setDeleting(true)}>
                                    <Trash2 className="size-3.5" />Delete event
                                </Button>
                            </>
                        )}
                    </section>
                )}
            </div>

            <EventWriter
                open={writing}
                onOpenChange={setWriting}
                prefill={{ name: d.name, venue: d.venue, date: d.event_date }}
                onFill={(w) => form.setData((data) => ({ ...data, name: w.name || data.name, tagline: w.tagline || data.tagline, description: w.description || data.description }))}
            />

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title={`Delete ${event.name} for good?`}
                confirmLabel="Delete"
                onConfirm={() => router.delete(route('organizer.events.destroy', event.id!), { onFinish: () => setDeleting(false) })}
            >
                This can't be undone.
            </ConfirmDialog>
        </OrganizerLayout>
    );
}

/** One ticket type: name, price, how many, what's included, people per ticket, QR colour, on sale. */
function TierRow({ tier, index: i, colours, errors, removable, onChange, onRemove }: {
    tier: Tier;
    index: number;
    colours: Record<string, string>;
    errors: Record<string, string>;
    removable: boolean;
    onChange: (patch: Partial<Tier>) => void;
    onRemove: () => void;
}) {
    const qty = +(tier.quantity_available ?? 0) || 0;
    const per = +tier.quantity_per_purchase || 1;
    const err = (key: string) => errors[`tiers.${i}.${key}`];

    return (
        <div className="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-6">
                <Field className="sm:col-span-3" label="Ticket name" htmlFor={`tier-name-${i}`} error={err('tier_name')}>
                    <Input id={`tier-name-${i}`} required placeholder="e.g. VIP" className={cn(field, 'bg-white')} value={tier.tier_name} onChange={(e) => onChange({ tier_name: e.target.value })} />
                </Field>
                <Field className="sm:col-span-1" label="Price (M)" htmlFor={`tier-price-${i}`} error={err('price')}>
                    <Input id={`tier-price-${i}`} type="number" required min={0} step="0.01" placeholder="100" className={cn(field, 'bg-white')} value={tier.price ?? ''} onChange={(e) => onChange({ price: e.target.value })} />
                </Field>
                <Field
                    className="sm:col-span-2"
                    label="Number of tickets"
                    htmlFor={`tier-qty-${i}`}
                    error={err('quantity_available')}
                    hint={tier.taken ? `${tier.taken} taken${qty && tier.taken >= qty ? ' · sold out' : ''}` : undefined}
                >
                    <Input id={`tier-qty-${i}`} type="number" required min={Math.max(1, tier.taken)} placeholder="e.g. 80" className={cn(field, 'bg-white')} value={tier.quantity_available ?? ''} onChange={(e) => onChange({ quantity_available: e.target.value })} />
                </Field>
            </div>
            <Textarea rows={1} placeholder="What's included (optional)" aria-label="What's included" className="bg-white" value={tier.description} onChange={(e) => onChange({ description: e.target.value })} />
            <div className="flex flex-wrap items-center gap-4 text-[12px] font-bold text-gray-600">
                <label className="flex items-center gap-2">
                    People per ticket
                    <input type="number" min={1} max={100} value={tier.quantity_per_purchase} onChange={(e) => onChange({ quantity_per_purchase: e.target.value })} className="w-20 rounded-xl border border-slate-200 bg-white px-3 py-2" />
                </label>
                <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                    QR colour
                    <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label="QR colour">
                        {Object.entries(colours).map(([hex, label]) => (
                            <button
                                key={hex}
                                type="button"
                                role="radio"
                                aria-checked={tier.color === hex}
                                aria-label={label}
                                title={label}
                                onClick={() => onChange({ color: hex })}
                                className={cn('size-7 rounded-full border-2 transition-all', tier.color === hex ? 'border-action ring-2 ring-action ring-offset-1' : 'border-white shadow')}
                                style={{ background: hex }}
                            />
                        ))}
                        {/* A colour chosen before the palette existed stays until changed. */}
                        {!colours[tier.color] && <span className="size-7 rounded-full border-2 border-action" style={{ background: tier.color }} title="Current colour" />}
                    </div>
                    <span className="text-[11px] font-medium text-gray-400">{colours[tier.color] ?? 'Current colour'}</span>
                </div>
                <label className="flex items-center gap-2">
                    <input type="checkbox" checked={tier.is_active} onChange={(e) => onChange({ is_active: e.target.checked })} className="size-4 accent-action" /> On sale
                </label>
                {removable && (
                    <button type="button" onClick={onRemove} title={tier.sold ? 'Already sold, so it will be switched off rather than removed' : undefined} className="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600">
                        Remove
                    </button>
                )}
            </div>
            {per > 1 && (
                <p className="text-[11px] font-medium text-gray-500">
                    One ticket and one QR for {per} people; the price is for the whole group.{qty ? ` ${qty} tickets is ${qty * per} people.` : ''}
                </p>
            )}
        </div>
    );
}

/** If every ticket sells: the people, the sales, VENTIQ's fees, and what's left. */
function OrderSummary({ order, paid, sponsored }: { order: { people: number; sales: number; fees: number; keep: number }; paid: boolean; sponsored: boolean }) {
    if (order.people <= 0) return null;
    return (
        <div className="rounded-2xl border border-gray-100 bg-white p-5">
            <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400">If you sell all of them</p>
            <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt className="text-[11px] font-bold text-gray-500">Expected attendance</dt>
                    <dd className="text-lg font-black text-brand">{order.people.toLocaleString('en-US')} {order.people === 1 ? 'person' : 'people'}</dd>
                </div>
                {paid && (
                    <div>
                        <dt className="text-[11px] font-bold text-gray-500">Ticket sales</dt>
                        <dd className="text-lg font-black text-brand">{money(order.sales)}</dd>
                    </div>
                )}
                <div>
                    <dt className="text-[11px] font-bold text-gray-500">VENTIQ fees</dt>
                    <dd className="text-lg font-black text-brand">{sponsored ? 'Sponsored' : money(order.fees)}</dd>
                </div>
                {paid && (
                    <div>
                        <dt className="text-[11px] font-bold text-gray-500">You keep</dt>
                        <dd className="text-lg font-black text-mint-ink">{money(order.keep)}</dd>
                    </div>
                )}
            </dl>
            <p className="mt-3 text-[12px] font-medium text-gray-500">
                Fees are charged on the tickets actually issued, so you pay for what you sell. We'll let you know if a ticket type sells out, and you can allow more tickets any time.
            </p>
        </div>
    );
}

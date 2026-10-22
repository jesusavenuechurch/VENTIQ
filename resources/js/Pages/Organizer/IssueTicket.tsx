import { Head, useForm } from '@inertiajs/react';
import { Gift, Info, Wallet } from 'lucide-react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { AppLink } from '@/components/app-link';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { cn, money } from '@/lib/utils';

interface Tier {
    id: number;
    name: string;
    price: number;
    admits: number;
    full: boolean;
}

const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';
const smallLabel = 'mb-2 ml-1 block text-[10px] font-black uppercase tracking-widest text-gray-400';

/** A ticket for someone who paid the organizer in person, or a guest who comes free. */
export default function IssueTicket({ event, tiers, kind, methods, fees }: {
    event: { id: number; name: string; feesSponsored: boolean; status: string };
    tiers: Tier[];
    kind: 'sold' | 'comp';
    methods: Record<string, string>;
    fees: { percent: number; perPerson: number };
}) {
    const form = useForm({
        kind,
        event_tier_id: tiers.find((t) => !t.full)?.id ?? null,
        full_name: '',
        phone: '',
        email: '',
        reason: '',
        method: 'cash',
        reference: '',
        send_whatsapp: true,
    });
    const d = form.data;
    const tier = tiers.find((t) => t.id === d.event_tier_id);
    const sold = d.kind === 'sold';
    const fee = sold ? fees.percent * (tier?.price ?? 0) + fees.perPerson * (tier?.admits ?? 1) : fees.perPerson * (tier?.admits ?? 1);
    const errors = Object.values(form.errors);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(route('organizer.events.comp.store', event.id));
    }

    return (
        <OrganizerLayout
            title="Issue a ticket"
            subtitle={`${event.name} · for someone who paid you in person, or a guest who comes free. It's active straight away.`}
            crumbs={[['Events', route('organizer.home')], [event.name, route('organizer.events.attendees', event.id)], ['Issue a ticket']]}
            draftEventId={event.status === 'draft' ? event.id : null}
        >
            <Head title={`Issue a ticket · ${event.name}`} />
            <div className="max-w-3xl">
                {errors.length > 0 && (
                    <div className="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700">
                        <ul className="ml-5 list-disc font-medium">{errors.map((m, i) => <li key={i}>{m}</li>)}</ul>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
                    <div className="grid grid-cols-2 gap-2">
                        {([
                            ['sold', <Wallet key="i" className="mr-1 inline size-3.5" />, 'Sold', "They paid you: cash at your office or a reseller, or a transfer you've checked."],
                            ['comp', <Gift key="i" className="mr-1 inline size-3.5" />, 'Complimentary', 'Free entry for a speaker, sponsor or guest.'],
                        ] as const).map(([value, icon, title, hint]) => (
                            <button key={value} type="button" onClick={() => form.setData('kind', value)} className={cn('rounded-2xl border-2 p-4 text-left transition-all', d.kind === value ? 'border-action bg-action-soft' : 'border-slate-50 bg-slate-50')}>
                                <span className="block text-[13px] font-black text-brand">{icon}{title}</span>
                                <span className="block text-[11px] font-medium text-gray-500">{hint}</span>
                            </button>
                        ))}
                    </div>

                    <div>
                        <span className={smallLabel}>Ticket type</span>
                        <div className="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Ticket type">
                            {tiers.map((t) => (
                                <label key={t.id} className={cn('flex items-center gap-3 rounded-2xl border-2 p-4 transition-all', t.full ? 'cursor-not-allowed opacity-50' : 'cursor-pointer', d.event_tier_id === t.id ? 'border-action bg-action-soft' : 'border-slate-50 bg-slate-50')}>
                                    <input type="radio" name="event_tier_id" disabled={t.full} checked={d.event_tier_id === t.id} onChange={() => form.setData('event_tier_id', t.id)} className="accent-action" />
                                    <span className="min-w-0">
                                        <span className="block text-[13px] font-black text-brand">{t.name}</span>
                                        <span className="block text-[11px] font-medium text-gray-500">
                                            {t.admits > 1 && `Admits ${t.admits} · `}
                                            {t.full ? 'Full' : money(t.price)}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field className="sm:col-span-2" label="Guest's full name" htmlFor="full_name" error={form.errors.full_name}>
                            <Input id="full_name" required className={field} value={d.full_name} onChange={(e) => form.setData('full_name', e.target.value)} />
                        </Field>
                        <Field label="WhatsApp number" htmlFor="phone" error={form.errors.phone} hint="Lesotho numbers need just the 8 digits. Others: start with +.">
                            <Input id="phone" required inputMode="tel" placeholder="5949 4756" className={field} value={d.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field label={<>Email <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="email" error={form.errors.email}>
                            <Input id="email" type="email" className={field} value={d.email} onChange={(e) => form.setData('email', e.target.value)} />
                        </Field>
                        {sold ? (
                            <>
                                <Field label="Paid with" htmlFor="method" error={form.errors.method}>
                                    <NativeSelect id="method" value={d.method} onChange={(e) => form.setData('method', e.target.value)}>
                                        {Object.entries(methods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                    </NativeSelect>
                                </Field>
                                <Field label={<>Receipt or reference <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="reference" error={form.errors.reference}>
                                    <Input id="reference" placeholder="e.g. Receipt 0042" className={field} value={d.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                                </Field>
                            </>
                        ) : (
                            <Field className="sm:col-span-2" label="Reason (for your records)" htmlFor="reason" error={form.errors.reason}>
                                <Input id="reason" placeholder="e.g. Keynote speaker" className={field} value={d.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                            </Field>
                        )}
                    </div>

                    <label className="flex items-center gap-3 text-[13px] font-bold text-brand">
                        <input type="checkbox" checked={d.send_whatsapp} onChange={(e) => form.setData('send_whatsapp', e.target.checked)} className="size-5 rounded accent-action" />
                        Send the ticket to their WhatsApp now
                    </label>

                    <p className="rounded-2xl bg-lilac p-4 text-[12px] font-medium text-lilac-ink">
                        {event.feesSponsored ? (
                            <strong><Gift className="mr-1 inline size-4" />VENTIQ is sponsoring this event's fees, so this ticket costs you nothing.</strong>
                        ) : sold ? (
                            <><Info className="mr-1 inline size-4" />You keep the money. VENTIQ's fee on this ticket (<strong>{money(fee)}</strong>) is added to your fee invoice, like any ticket paid to you.</>
                        ) : (
                            <><Info className="mr-1 inline size-4" />Complimentary tickets carry VENTIQ's operational fee of {money(fees.perPerson)} per person (<strong>{money(fee)}</strong> for this ticket), added to your fee invoice.</>
                        )}
                    </p>

                    <div className="flex flex-wrap items-center gap-3 pt-2">
                        <Button type="submit" variant="brand" className="px-6 py-4 text-[11px]" disabled={form.processing || !tier}>
                            {sold ? <><Wallet className="size-4" />Sell ticket · {money(tier?.price ?? 0)}</> : <><Gift className="size-4" />Issue free ticket</>}
                        </Button>
                        <AppLink to="organizer.events.attendees" params={event.id} className="text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">Cancel</AppLink>
                    </div>
                </form>
            </div>
        </OrganizerLayout>
    );
}

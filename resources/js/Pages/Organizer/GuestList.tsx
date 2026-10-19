import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Check, Download, X } from 'lucide-react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { cn, money } from '@/lib/utils';

interface Preview {
    token: string;
    ok: number;
    problems: number;
    rows: { row: number; full_name: string | null; phone: string | null; problem: string | null }[];
    tier: { name: string; price: number };
    mode: 'complimentary' | 'paid_direct';
    sendWhatsapp: boolean;
}

interface Props {
    event: { id: number; name: string; status: string; feesSponsored: boolean };
    tiers: { id: number; name: string; price: number; admits: number }[];
    methods: Record<string, string>;
    perPerson: number;
    preview: Preview | null;
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8';
const smallLabel = 'mb-2 ml-1 block text-[10px] font-black uppercase tracking-widest text-gray-400';
const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** Tickets for many people at once, from a spreadsheet: upload, see every row checked, confirm. */
export default function GuestList({ event, tiers, methods, perPerson, preview }: Props) {
    const form = useForm({
        file: null as File | null,
        event_tier_id: tiers[0]?.id ?? '',
        mode: 'complimentary' as 'complimentary' | 'paid_direct',
        reason: '',
        method: 'cash',
        send_whatsapp: true,
    });
    const d = form.data;
    const errors = Object.values(form.errors);

    return (
        <OrganizerLayout
            title="Import a guest list"
            subtitle={`${event.name} · tickets for many people at once, from a spreadsheet.`}
            crumbs={[['Events', route('organizer.home')], [event.name, route('organizer.events.attendees', event.id)], ['Import a guest list']]}
            draftEventId={event.status === 'draft' ? event.id : null}
        >
            <Head title={`Import guest list · ${event.name}`} />
            <div className="max-w-3xl">
                {errors.length > 0 && (
                    <div className="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700">
                        {errors.map((m, i) => <p key={i}>{m}</p>)}
                    </div>
                )}

                {preview ? (
                    <Checked event={event} preview={preview} perPerson={perPerson} />
                ) : (
                    <form
                        className={cn(card, 'space-y-6')}
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('organizer.events.guests.check', event.id), { forceFormData: true });
                        }}
                    >
                        <div>
                            <span className={smallLabel}>1 · Your spreadsheet</span>
                            <input type="file" required accept=".csv,.xlsx,.xls" aria-label="Spreadsheet" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="text-[12px] font-semibold text-gray-600 file:mr-3 file:rounded-full file:border-0 file:bg-action file:px-4 file:py-2 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:text-white" />
                            <p className="mt-2 text-[11px] text-gray-500">
                                CSV or Excel with a header row: <span className="font-mono">full_name</span>, <span className="font-mono">phone</span>, and optionally <span className="font-mono">email</span>. Up to 500 people.{' '}
                                <a href={route('organizer.guests.template')} className="font-bold text-action-ink hover:underline"><Download className="mr-0.5 inline size-3" />Download a template</a>
                            </p>
                        </div>

                        <Field label="2 · Ticket type for everyone on the list" htmlFor="tier" error={form.errors.event_tier_id}>
                            <NativeSelect id="tier" required value={d.event_tier_id} onChange={(e) => form.setData('event_tier_id', Number(e.target.value))}>
                                {tiers.map((t) => <option key={t.id} value={t.id}>{t.name} · {money(t.price)}{t.admits > 1 ? ` · admits ${t.admits}` : ''}</option>)}
                            </NativeSelect>
                        </Field>

                        <div>
                            <span className={smallLabel}>3 · These tickets are</span>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {([['complimentary', 'Complimentary', 'Free: speakers, sponsors, invited guests.'], ['paid_direct', 'Already paid to you', 'People who paid you in cash, by EcoCash, M-Pesa or bank.']] as const).map(([value, title, hint]) => (
                                    <label key={value} className={cn('flex cursor-pointer gap-3 rounded-2xl border-2 p-4 transition-all', d.mode === value ? 'border-action bg-action-soft' : 'border-slate-50 bg-slate-50')}>
                                        <input type="radio" name="mode" checked={d.mode === value} onChange={() => form.setData('mode', value)} className="mt-0.5 accent-action" />
                                        <span><span className="block text-[13px] font-black text-brand">{title}</span><span className="block text-[11px] text-gray-500">{hint}</span></span>
                                    </label>
                                ))}
                            </div>
                            <div className="mt-3">
                                {d.mode === 'complimentary' ? (
                                    <Input aria-label="Reason" placeholder="Reason, for your records (e.g. Invited partners)" className={field} value={d.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                                ) : (
                                    <Field label="How they paid" htmlFor="method">
                                        <NativeSelect id="method" value={d.method} onChange={(e) => form.setData('method', e.target.value)}>
                                            {Object.entries(methods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                        </NativeSelect>
                                    </Field>
                                )}
                            </div>
                        </div>

                        <label className="flex items-center gap-3 text-[13px] font-bold text-brand">
                            <input type="checkbox" checked={d.send_whatsapp} onChange={(e) => form.setData('send_whatsapp', e.target.checked)} className="size-5 rounded accent-action" />
                            Send each person their ticket on WhatsApp
                        </label>

                        <Button type="submit" variant="brand" className="w-full px-6 py-4 text-[11px] sm:w-auto" disabled={form.processing || tiers.length === 0}>
                            Check the list <ArrowRight className="size-4" />
                        </Button>
                        <p className="text-[11px] text-gray-400">Nothing is created yet: you'll see every row checked first.</p>
                    </form>
                )}
            </div>
        </OrganizerLayout>
    );
}

function Checked({ event, preview: p, perPerson }: { event: Props['event']; preview: Preview; perPerson: number }) {
    const form = useForm({ token: p.token });

    return (
        <>
            <div className={cn(card, 'mb-4')}>
                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-mint px-3 py-1 text-[11px] font-black text-mint-ink">{p.ok} ready</span>
                    {p.problems > 0 && (
                        <span className="rounded-full bg-rose-50 px-3 py-1 text-[11px] font-black text-rose-700"><AlertTriangle className="mr-1 inline size-3" />{p.problems} with a problem, will be skipped</span>
                    )}
                </div>
                <p className="mt-3 text-[13px] text-gray-600">
                    <strong className="text-brand">{p.tier.name}</strong> tickets,{' '}
                    {p.mode === 'complimentary' ? 'complimentary' : `recorded as paid to you (${money(p.tier.price)} each)`}
                    {p.sendWhatsapp ? ', sent on WhatsApp.' : ', not sent yet.'}
                </p>
                {!event.feesSponsored && (
                    <p className="mt-2 text-[11px] font-medium text-lilac-ink">
                        VENTIQ fees apply to each ticket as usual{p.mode === 'complimentary' ? ` (${money(perPerson)} per person)` : ''}, added to your fee invoice.
                    </p>
                )}
            </div>

            <div className="mb-6 overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white shadow-sm">
                <div className="max-h-[50vh] overflow-auto">
                    <table className="w-full text-left text-[12px]">
                        <thead className="sticky top-0 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                            <tr><th className="px-4 py-2">Row</th><th className="px-4 py-2">Name</th><th className="px-4 py-2">Phone</th><th className="px-4 py-2">Status</th></tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {p.rows.map((r) => (
                                <tr key={r.row} className={r.problem ? 'bg-rose-50/40' : undefined}>
                                    <td className="px-4 py-2 text-gray-400">{r.row}</td>
                                    <td className="px-4 py-2 font-bold text-brand">{r.full_name || '—'}</td>
                                    <td className="px-4 py-2 font-mono text-gray-600">{r.phone || '—'}</td>
                                    <td className="px-4 py-2">
                                        {r.problem ? <span className="font-bold text-rose-700"><X className="mr-1 inline size-3" />{r.problem}</span> : <span className="font-bold text-mint-ink"><Check className="mr-1 inline size-3" />Ready</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                {p.ok > 0 && (
                    <Button type="button" variant="brand" className="px-6 py-4 text-[11px]" disabled={form.processing} onClick={() => form.post(route('organizer.events.guests.store', event.id))}>
                        Create {p.ok} {p.ok === 1 ? 'ticket' : 'tickets'}
                    </Button>
                )}
                <button type="button" onClick={() => router.get(route('organizer.events.guests.create', event.id))} className="text-[11px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">
                    Fix the file and upload again
                </button>
            </div>
        </>
    );
}

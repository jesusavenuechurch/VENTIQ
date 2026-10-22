import { router, useForm } from '@inertiajs/react';
import { Gift } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { money } from '@/lib/utils';

/** App\Services\Reports\EventFinance::summary(). */
export interface Finance {
    tickets: number;
    people: number;
    people_admitted: number;
    tickets_unpaid: number;
    released_tickets: number;
    collected: number;
    collected_ventiq: number;
    collected_direct: number;
    unattributed: number;
    outstanding: number;
    awaiting_confirmation: number;
    ventiq_fee: number;
    payout_total: number;
    payout_settled: number;
    payout_due: number;
    fees_service: number;
    fees_operational: number;
    fees_from_payout: number;
    fees_to_invoice: number;
    fees_invoiced: number;
    fees_sponsored: number;
    fees_sponsored_event: boolean;
}

export interface Khoebo {
    order: string | null;
    invoice: string | null;
    invoiceId: string | number | null;
    prepaid: boolean;
    invoiceTotal: number;
    balanceInvoice: string | null;
    balanceTotal: number;
    owed: { invoice: number; balance: number };
    methods: Record<string, string>;
    today: string;
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm';
const label = 'mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400';
const chip = 'rounded-full border border-gray-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-brand';

/** One event's headcount and money, split by who collected it, and VENTIQ's fees on it. */
export function EventMoney({ eventId, finance: f, fees, superAdmin, khoebo }: { eventId: number; finance: Finance; fees: { percent: number; perPerson: number }; superAdmin: boolean; khoebo: Khoebo | null }) {
    const [confirm, setConfirm] = useState<null | 'sponsor' | 'invoice'>(null);
    const [paying, setPaying] = useState<null | 'invoice' | 'balance'>(null);
    const sponsored = f.fees_sponsored_event;

    return (
        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className={card}>
                <p className={label}>People</p>
                <div className="grid grid-cols-3 gap-3">
                    <div><p className="text-2xl font-black text-brand">{f.people}</p><p className="text-[11px] font-bold text-gray-500">registered</p></div>
                    <div><p className="text-2xl font-black text-mint-ink">{f.people_admitted}</p><p className="text-[11px] font-bold text-gray-500">arrived</p></div>
                    <div><p className="text-2xl font-black text-slate-400">{f.tickets_unpaid}</p><p className="text-[11px] font-bold text-gray-500">unpaid tickets</p></div>
                </div>
                <p className="mt-3 text-[11px] font-medium text-gray-400">
                    {f.tickets} {f.tickets === 1 ? 'ticket' : 'tickets'}; a group ticket counts every person in it.
                    {f.released_tickets > 0 && ` ${f.released_tickets} expired or cancelled not counted.`}
                </p>
            </div>

            <div className={card}>
                <p className={label}>Money</p>
                <dl className="space-y-1.5 text-[13px]">
                    <Row label={<><Dot className="bg-lilac-ink" />Collected by VENTIQ</>} value={money(f.collected_ventiq)} />
                    <Row label={<><Dot className="bg-mint-ink" />Collected by you (direct)</>} value={money(f.collected_direct)} />
                    {f.unattributed > 0 && <Row muted label="Older payments, source not recorded" value={money(f.unattributed)} />}
                    <div className="flex justify-between border-t border-gray-100 pt-1.5"><dt className="font-black text-brand">Total collected</dt><dd className="font-black text-mint-ink">{money(f.collected)}</dd></div>
                    {f.awaiting_confirmation > 0 && (
                        <div className="flex justify-between"><dt className="font-bold text-action-ink">Waiting for you to confirm</dt><dd className="font-black text-action-ink">{money(f.awaiting_confirmation)}</dd></div>
                    )}
                    <Row muted label="Still to be paid by attendees" value={money(f.outstanding)} />
                </dl>
                {f.collected_ventiq > 0 && (
                    <p className="mt-4 border-t border-gray-100 pt-3 text-[11px] font-medium text-gray-500">
                        From payments to VENTIQ we owe you <strong className="text-gray-800">{money(f.payout_total)}</strong> after our {money(f.ventiq_fee)} fee:{' '}
                        {money(f.payout_settled)} paid out, <strong className="text-gray-800">{money(f.payout_due)}</strong> still to come.
                    </p>
                )}
            </div>

            <div className="rounded-[1.5rem] border border-lilac bg-lilac p-6 md:col-span-2">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p className="text-[10px] font-black uppercase tracking-widest text-lilac-ink">VENTIQ fees</p>
                    <div className="flex items-center gap-2">
                        {sponsored && <span className="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase tracking-widest text-lilac-ink"><Gift className="mr-1 inline size-3" />Sponsored by VENTIQ</span>}
                        {superAdmin && <button type="button" className={chip} onClick={() => setConfirm('sponsor')}>{sponsored ? 'Stop sponsoring' : 'Sponsor fees'}</button>}
                    </div>
                </div>
                <dl className="grid grid-cols-2 gap-4 text-[13px] sm:grid-cols-4">
                    <div><dt className="font-bold text-gray-500">Service fee</dt><dd className="font-black text-gray-900">{money(f.fees_service)}</dd></div>
                    <div><dt className="font-bold text-gray-500">Operational fee</dt><dd className="font-black text-gray-900">{money(f.fees_operational)}</dd></div>
                    <div><dt className="font-bold text-gray-500">Taken from payout</dt><dd className="font-black text-gray-900">{money(f.fees_from_payout)}</dd></div>
                    <div><dt className="font-bold text-gray-500">To be invoiced</dt><dd className="font-black text-lilac-ink">{money(f.fees_to_invoice + f.fees_invoiced)}</dd></div>
                </dl>
                <p className="mt-3 text-[11px] font-medium text-lilac-ink/80">
                    {+(fees.percent * 100).toFixed(2)}% of each ticket price plus {money(fees.perPerson)} per person, on every active ticket. Fees on payments to VENTIQ come off your payout; fees on tickets paid directly to you, free and complimentary tickets are invoiced.
                    {f.fees_sponsored > 0 && ` ${money(f.fees_sponsored)} of fees on this event are sponsored by VENTIQ and won't be charged.`}
                </p>

                {khoebo && (
                    <div className="mt-4 space-y-3 border-t border-white/70 pt-4">
                        <p className="text-[10px] font-black uppercase tracking-widest text-lilac-ink">In Khoebo</p>
                        <p className="text-[12px] font-medium text-gray-700">
                            Order <strong>{khoebo.order ?? 'not made yet'}</strong>
                            {khoebo.invoice ? (
                                <> · {khoebo.prepaid ? 'Prepaid invoice' : 'Invoice'} <strong>{khoebo.invoice}</strong> {money(khoebo.invoiceTotal)}, {khoebo.owed.invoice > 0 ? `${money(khoebo.owed.invoice)} owed` : 'paid'}</>
                            ) : ' · invoiced the day after the event'}
                            {khoebo.balanceInvoice && (
                                <> · Balance <strong>{khoebo.balanceInvoice}</strong> {money(khoebo.balanceTotal)}, {khoebo.owed.balance > 0 ? `${money(khoebo.owed.balance)} owed` : 'paid'}</>
                            )}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {!khoebo.invoiceId && <button type="button" className={chip} onClick={() => setConfirm('invoice')}>Invoice now (paying ahead)</button>}
                            {(['invoice', 'balance'] as const).map((which) => khoebo.owed[which] > 0 && (
                                <button key={which} type="button" onClick={() => setPaying(which)} className="rounded-full bg-mint-ink px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white">
                                    Record payment{which === 'balance' ? ' on balance' : ''}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={confirm !== null}
                onOpenChange={(o) => !o && setConfirm(null)}
                destructive={false}
                title={confirm === 'invoice' ? 'Invoice this event now, for its full order?' : sponsored ? 'Charge VENTIQ fees on this event again?' : 'Sponsor this event: VENTIQ will not charge its fees?'}
                confirmLabel={confirm === 'invoice' ? 'Invoice now' : sponsored ? 'Charge fees' : 'Sponsor fees'}
                onConfirm={() => router.post(route(confirm === 'invoice' ? 'organizer.events.khoebo.invoice-now' : 'organizer.events.fee-sponsorship', eventId), {}, { preserveScroll: true, onFinish: () => setConfirm(null) })}
            >
                {confirm === 'invoice' && 'Attendance beyond it is billed the day after the event.'}
            </ConfirmDialog>

            {khoebo && paying && <KhoeboPayment eventId={eventId} khoebo={khoebo} which={paying} onClose={() => setPaying(null)} />}
        </div>
    );
}

function KhoeboPayment({ eventId, khoebo, which, onClose }: { eventId: number; khoebo: Khoebo; which: 'invoice' | 'balance'; onClose: () => void }) {
    const owed = khoebo.owed[which];
    const form = useForm({ amount: owed.toFixed(2), date: khoebo.today, method: Object.keys(khoebo.methods)[0], reference: '', balance: which === 'balance' });

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="rounded-[2rem]">
                <form
                    className="space-y-4 p-7"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('organizer.events.khoebo.payment', eventId), { preserveScroll: true, onSuccess: onClose });
                    }}
                >
                    <DialogTitle className="text-lg normal-case not-italic tracking-normal">Record a payment{which === 'balance' ? ' on the balance' : ''}</DialogTitle>
                    <Field label="Amount (M)" htmlFor="k-amount" error={form.errors.amount}>
                        <Input id="k-amount" type="number" step="0.01" min="0.01" max={owed} required value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} className="rounded-2xl px-4 py-3" />
                    </Field>
                    <Field label="Paid on" htmlFor="k-date" error={form.errors.date}>
                        <Input id="k-date" type="date" max={khoebo.today} required value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} className="rounded-2xl px-4 py-3" />
                    </Field>
                    <Field label="Paid with" htmlFor="k-method" error={form.errors.method}>
                        <NativeSelect id="k-method" value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                            {Object.entries(khoebo.methods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </NativeSelect>
                    </Field>
                    <Field label="Bank or transaction reference" htmlFor="k-ref" error={form.errors.reference}>
                        <Input id="k-ref" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} className="rounded-2xl px-4 py-3" />
                    </Field>
                    <Button type="submit" variant="brand" className="w-full bg-mint-ink py-3 text-[10px]" disabled={form.processing}>Record in Khoebo</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Row({ label, value, muted = false }: { label: React.ReactNode; value: string; muted?: boolean }) {
    return (
        <div className="flex justify-between gap-3">
            <dt className={muted ? 'font-medium text-gray-400' : 'font-bold text-gray-600'}>{label}</dt>
            <dd className={muted ? 'font-bold text-gray-500' : 'font-black text-gray-900'}>{value}</dd>
        </div>
    );
}

function Dot({ className }: { className: string }) {
    return <span className={`mr-2 inline-block size-2 rounded-full ${className}`} />;
}

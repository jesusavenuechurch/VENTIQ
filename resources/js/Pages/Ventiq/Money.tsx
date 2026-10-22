import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CircleAlert, Download, HandCoins, MessageSquareText, RotateCcw, SearchCheck, Tablet } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { cn, money } from '@/lib/utils';

interface PayoutTo {
    summary: string | null;
    changed: string | null;
    phone: string | null;
}

interface TicketLine {
    holder: string | null;
    phone: string | null;
    event: string | null;
    organization: string | null;
    code: string | null;
}

interface Props {
    summary: Record<'fees_from_payouts' | 'fees_invoiced_paid' | 'fees_invoiced_unpaid' | 'fees_to_invoice' | 'fees_sponsored' | 'held_for_organizers' | 'in_payout_batches' | 'paid_out' | 'gateway_costs', number>;
    gatewayRate: number;
    invoiceFrom: string;
    methods: Record<string, string>;
    payoutsDue: { organizationId: number; organization: string; lines: number; owed: number; fees: number; payoutTo: PayoutTo }[];
    batches: { id: number; organization: string | null; amount: number; created: string; payoutTo: PayoutTo }[];
    toInvoice: { organizationId: number; organization: string | null; tickets: number; people: number; total: number; upTo: number }[];
    awaiting: { organizationId: number; organization: string | null; reference: string; tickets: number; total: number; invoiced: string }[];
    toCheck: {
        id: number; amount: number; method: string; at: string; reference: string | null; transaction: string | null; ticket: TicketLine | null;
        byHand: { from: string | null; reference: string | null; proofUrl: string | null } | null; pushTo: string | null; noAnswer: boolean;
    }[];
    sms: {
        setUp: boolean; lastHeard: string | null; quiet: boolean;
        toCheck: { id: number; amount: number; payer: string; payerPhone: string; at: string; transaction: string; reference: string | null; balance: number; note: string | null; held: boolean; canAccept: boolean; ticket: { code: string; holder: string; event: string | null } | null }[];
    };
    paidTwice: { id: number; amount: number; method: string; at: string; transaction: string | null; phone: string | null; ticket: TicketLine | null }[];
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white shadow-sm';
const input = 'rounded-full bg-slate-50 px-3 py-1.5 text-[12px] font-semibold text-brand focus:bg-white focus:outline-action';
const button = 'rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-widest disabled:opacity-50';
const heading = 'text-[13px] font-black uppercase tracking-widest';

/** VENTIQ's own money: what it has earned, what it holds for organizers and must pay out, and the fees to invoice. */
export default function Money(props: Props) {
    const { summary: s, gatewayRate, invoiceFrom, payoutsDue, batches, toInvoice, awaiting, toCheck, sms, paidTwice, methods } = props;
    const [confirm, setConfirm] = useState<null | { title: string; label: string; destructive?: boolean; go: () => void }>(null);
    const earned = s.fees_from_payouts + s.fees_invoiced_paid;
    const post = (url: string, data: Record<string, unknown> = {}) => router.post(url, data as never, { preserveScroll: true, onFinish: () => setConfirm(null) });

    const figures: [string, number, string, string][] = [
        ['Fees taken from payouts', s.fees_from_payouts, 'Tickets paid to VENTIQ', 'text-lilac-ink'],
        ['Fees paid on invoices', s.fees_invoiced_paid, 'Direct, free and comp tickets', 'text-lilac-ink'],
        ['Invoiced, not paid yet', s.fees_invoiced_unpaid, 'Waiting on organizers', 'text-action-ink'],
        ['Fees to invoice', s.fees_to_invoice, `Events from ${invoiceFrom}`, 'text-action-ink'],
        ['Held for organizers', s.held_for_organizers, 'Not yet in a payout batch', 'text-brand'],
        ['In payout batches', s.in_payout_batches, 'Batched, not paid yet', 'text-action-ink'],
        ['Paid out to organizers', s.paid_out, 'All time', 'text-mint-ink'],
        ['Fees sponsored', s.fees_sponsored, 'Given away by VENTIQ', 'text-gray-500'],
    ];

    return (
        <AdminLayout title="VENTIQ money" subtitle="What VENTIQ has earned, what it holds for organizers, and what to invoice.">
            <Head title="VENTIQ money" />
            <div className="space-y-8">
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {figures.map(([label, value, hint, tone]) => <Figure key={label} label={label} value={money(value)} hint={hint} tone={tone} />)}
                    </div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <Figure label="Fees collected" value={money(earned)} hint="From payouts and paid invoices" tone="text-lilac-ink" />
                        <Figure label="Gateway costs" value={`−${money(s.gateway_costs)}`} hint={`${+(gatewayRate * 100).toFixed(2)}% of online payments, absorbed by VENTIQ`} tone="text-gray-500" />
                        <Figure label="Net earned" value={money(earned - s.gateway_costs)} hint="Fees collected less gateway costs" tone="text-mint-ink" />
                    </div>
                </div>

                {sms.setUp && (
                    <p className={cn('text-[12px] font-bold', sms.quiet ? 'text-rose-600' : 'text-gray-500')}>
                        <Tablet className="mr-1 inline size-4" />
                        {sms.lastHeard ? `The EcoCash tablet last forwarded a message ${sms.lastHeard}.` : "The EcoCash tablet hasn't forwarded anything yet."}
                        {sms.quiet && " Check it's on, charged and online."}
                    </p>
                )}

                {sms.toCheck.length > 0 && (
                    <section className="space-y-3">
                        <h2 className={cn(heading, 'text-action-ink')}><MessageSquareText className="mr-1 inline size-4" />EcoCash payments to check</h2>
                        <p className="text-[12px] text-gray-500">Money came into the merchant wallet but couldn't be matched on its own, or its balance didn't follow the last message. Match each to a ticket by its entry code, or set it aside.</p>
                        <div className={cn(card, 'divide-y divide-gray-50')}>
                            {sms.toCheck.map((m) => (
                                <div key={m.id} className="space-y-3 p-5">
                                    <div className="min-w-0">
                                        <p className="text-[14px] font-black text-brand">{money(m.amount)} · from {m.payer} ({m.payerPhone})</p>
                                        <p className="font-mono text-[11px] text-gray-400">{m.at} · {m.transaction} · ref "{m.reference}" · balance {money(m.balance)}</p>
                                        {m.note && <p className={cn('mt-1 text-[11px] font-bold', m.held ? 'text-rose-600' : 'text-action-ink')}>{m.note}</p>}
                                        {m.ticket && <p className="mt-1 text-[11px] text-gray-500">Looks like {m.ticket.code}: {m.ticket.holder}, {m.ticket.event}</p>}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <InlineForm url={route('ventiq.money.sms.decide', m.id)} fields={{ decision: 'match' }} input={{ name: 'entry_code', value: m.ticket?.code ?? '', placeholder: 'Entry code, e.g. VQ-X82L', required: true, className: 'w-44' }} label="Match" tone="bg-mint-ink text-white" />
                                        {m.canAccept && (
                                            <button type="button" className={cn(button, 'bg-brand text-white')} onClick={() => setConfirm({ title: 'Is this payment on the EcoCash statement?', label: "It's on the statement", destructive: false, go: () => post(route('ventiq.money.sms.decide', m.id), { decision: 'accept' }) })}>
                                                It's on the statement
                                            </button>
                                        )}
                                        <button type="button" className={cn(button, 'border border-gray-200 bg-white text-gray-500 hover:text-rose-600')} onClick={() => setConfirm({ title: 'Set this payment aside as not for a ticket?', label: 'Set aside', go: () => post(route('ventiq.money.sms.decide', m.id), { decision: 'dismiss' }) })}>
                                            Not for a ticket
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {toCheck.length > 0 && (
                    <section className="space-y-3">
                        <h2 className={cn(heading, 'text-action-ink')}><SearchCheck className="mr-1 inline size-4" />Online payments to check</h2>
                        <p className="text-[12px] text-gray-500">EcoCash gave no answer for these, or the attendee paid the merchant by hand, and no SMS has matched them yet. Most clear on their own once the payment's SMS arrives; otherwise look for each in the EcoCash merchant statement (amount, number, time), then mark it.</p>
                        <div className={cn(card, 'divide-y divide-gray-50')}>
                            {toCheck.map((p) => (
                                <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 p-5">
                                    <div className="min-w-0">
                                        <p className="text-[14px] font-black text-brand">{money(p.amount)} · {p.method}</p>
                                        <TicketText ticket={p.ticket} />
                                        <p className="font-mono text-[11px] text-gray-400">{p.at} · ref {p.reference}{p.transaction && ` · ${p.transaction}`}</p>
                                        {p.byHand ? (
                                            <p className="mt-1 text-[11px] font-bold text-action-ink">
                                                <HandCoins className="mr-1 inline size-3.5" />Paid the merchant by hand from {p.byHand.from ?? '?'}{p.byHand.reference && ` · ref ${p.byHand.reference}`}
                                                {p.byHand.proofUrl && <> · <a href={p.byHand.proofUrl} target="_blank" rel="noopener" className="underline">screenshot</a></>}
                                            </p>
                                        ) : p.pushTo && (
                                            <p className="mt-1 text-[11px] text-gray-400">Push to {p.pushTo}{p.noAnswer && ' · EcoCash gave no answer'}</p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <InlineForm url={route('ventiq.money.online.decide', p.id)} fields={{ decision: 'received' }} input={{ name: 'reference', placeholder: 'Merchant reference (optional)', className: 'w-48' }} label="Received" tone="bg-mint-ink text-white" />
                                        <button type="button" className={cn(button, 'border border-gray-200 bg-white text-gray-500 hover:text-rose-600')} onClick={() => setConfirm({ title: 'Mark this payment as not received?', label: 'Not received', go: () => post(route('ventiq.money.online.decide', p.id), { decision: 'not_received' }) })}>
                                            Not received
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {paidTwice.length > 0 && (
                    <section className="space-y-3">
                        <h2 className={cn(heading, 'text-rose-600')}><RotateCcw className="mr-1 inline size-4" />Paid twice: refund these</h2>
                        <p className="text-[12px] text-gray-500">These came in for tickets that were already paid. Send the money back, then mark it.</p>
                        <div className={cn(card, 'divide-y divide-gray-50')}>
                            {paidTwice.map((p) => (
                                <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 p-5">
                                    <div className="min-w-0">
                                        <p className="text-[14px] font-black text-brand">{money(p.amount)} · {p.method}</p>
                                        <p className="text-[11px] text-gray-500">{p.ticket?.holder ?? 'Unknown'} · {p.phone ?? p.ticket?.phone} · {p.ticket?.event}</p>
                                        <p className="font-mono text-[11px] text-gray-400">{p.at} · {p.transaction}</p>
                                    </div>
                                    <InlineForm url={route('ventiq.money.online.refunded', p.id)} input={{ name: 'reference', placeholder: 'Refund reference', className: 'w-44' }} label="Refunded" tone="bg-brand text-white" />
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <section className="space-y-3">
                    <h2 className={cn(heading, 'text-brand')}>Payouts to organizers</h2>
                    <div className={cn(card, 'divide-y divide-gray-50')}>
                        {payoutsDue.length === 0 ? <p className="p-6 text-[12px] text-gray-400">No money waiting to be paid out.</p> : payoutsDue.map((row) => (
                            <div key={row.organizationId} className="flex flex-wrap items-center justify-between gap-3 p-5">
                                <div>
                                    <p className="text-[14px] font-black text-brand">{row.organization}</p>
                                    <p className="text-[11px] text-gray-500">{row.lines} {row.lines === 1 ? 'payment' : 'payments'} · VENTIQ fees {money(row.fees)} taken off</p>
                                    <PayoutToText to={row.payoutTo} />
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-lg font-black text-brand">{money(row.owed)}</span>
                                    <button type="button" className={cn(button, 'bg-brand text-white hover:bg-action')} onClick={() => post(route('ventiq.money.payouts.create', row.organizationId))}>Create payout batch</button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {batches.length > 0 && (
                        <>
                            <p className="ml-1 pt-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Batches to pay</p>
                            <div className={cn(card, 'divide-y divide-gray-50')}>
                                {batches.map((b) => (
                                    <div key={b.id} className="flex flex-wrap items-center justify-between gap-3 p-5">
                                        <div>
                                            <p className="text-[14px] font-black text-brand">{b.organization} · {money(b.amount)}</p>
                                            <p className="text-[11px] text-gray-500">Batch #{b.id} · created {b.created}</p>
                                            <PayoutToText to={b.payoutTo} />
                                        </div>
                                        <PayoutPaid id={b.id} methods={methods} />
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className={cn(heading, 'text-brand')}>Fees to invoice</h2>
                    <p className="text-[12px] text-gray-500">Tickets paid directly to organizers, free and complimentary tickets. Download the lines for the invoicing system, then record the invoice number here.</p>
                    <div className={cn(card, 'divide-y divide-gray-50')}>
                        {toInvoice.length === 0 ? <p className="p-6 text-[12px] text-gray-400">Nothing to invoice.</p> : toInvoice.map((row) => (
                            <div key={row.organizationId} className="flex flex-wrap items-center justify-between gap-3 p-5">
                                <div>
                                    <p className="text-[14px] font-black text-brand">{row.organization}</p>
                                    <p className="text-[11px] text-gray-500">{row.tickets} {row.tickets === 1 ? 'ticket' : 'tickets'} · {row.people} {row.people === 1 ? 'person' : 'people'}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="mr-2 text-lg font-black text-lilac-ink">{money(row.total)}</span>
                                    <a href={route('ventiq.money.fees.csv', { organization: row.organizationId, up_to: row.upTo })} className={cn(button, 'border border-gray-200 bg-white text-brand')}><Download className="mr-1 inline size-3" />CSV</a>
                                    <InlineForm url={route('ventiq.money.fees.invoiced', row.organizationId)} fields={{ up_to: row.upTo }} input={{ name: 'reference', placeholder: 'Invoice number', required: true, className: 'w-36' }} label="Mark invoiced" tone="bg-brand text-white hover:bg-action" />
                                </div>
                            </div>
                        ))}
                    </div>

                    {awaiting.length > 0 && (
                        <>
                            <p className="ml-1 pt-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Invoices waiting for payment</p>
                            <div className={cn(card, 'divide-y divide-gray-50')}>
                                {awaiting.map((row) => (
                                    <div key={`${row.organizationId}-${row.reference}`} className="flex flex-wrap items-center justify-between gap-3 p-5">
                                        <div>
                                            <p className="text-[14px] font-black text-brand">{row.organization} · {row.reference}</p>
                                            <p className="text-[11px] text-gray-500">{row.tickets} {row.tickets === 1 ? 'ticket' : 'tickets'} · invoiced {row.invoiced}</p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="text-lg font-black text-action-ink">{money(row.total)}</span>
                                            <button type="button" className={cn(button, 'bg-mint-ink text-white')} onClick={() => setConfirm({ title: `Mark invoice ${row.reference} from ${row.organization} as paid?`, label: 'Mark paid', destructive: false, go: () => post(route('ventiq.money.fees.paid', row.organizationId), { reference: row.reference }) })}>
                                                Mark paid
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </section>
            </div>

            <ConfirmDialog open={confirm !== null} onOpenChange={(o) => !o && setConfirm(null)} title={confirm?.title} confirmLabel={confirm?.label ?? ''} destructive={confirm?.destructive ?? true} onConfirm={() => confirm?.go()} />
        </AdminLayout>
    );
}

function Figure({ label, value, hint, tone }: { label: string; value: string; hint: string; tone: string }) {
    return (
        <div className={cn(card, 'p-5')}>
            <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">{label}</p>
            <p className={cn('mt-1 text-xl font-black', tone)}>{value}</p>
            <p className="mt-0.5 text-[11px] text-gray-400">{hint}</p>
        </div>
    );
}

function TicketText({ ticket }: { ticket: TicketLine | null }) {
    return <p className="text-[11px] text-gray-500">{ticket?.holder ?? 'Unknown'} · {ticket?.phone} · {ticket?.event} ({ticket?.organization})</p>;
}

/** Where an organization's payout goes, and a warning when it's missing or just changed. */
function PayoutToText({ to }: { to: PayoutTo }) {
    if (!to.summary) return <p className="mt-1 text-[11px] font-black text-action-ink"><CircleAlert className="mr-1 inline size-3" />No payout account on file: ask them to add one in Settings.</p>;
    return (
        <>
            <p className="mt-1 text-[11px] font-bold text-brand">{to.summary}</p>
            {to.changed && <p className="mt-0.5 text-[11px] font-black text-rose-600"><AlertTriangle className="mr-1 inline size-3" />Changed {to.changed}: confirm with the organizer by phone ({to.phone}) before paying.</p>}
        </>
    );
}

/** A one-field form posted in place: an entry code, a reference. */
function InlineForm({ url, fields = {}, input: field, label, tone }: {
    url: string;
    fields?: Record<string, string | number>;
    input: { name: string; value?: string; placeholder: string; required?: boolean; className?: string };
    label: string;
    tone: string;
}) {
    const form = useForm<Record<string, string | number>>({ ...fields, [field.name]: field.value ?? '' });
    return (
        <form className="flex items-center gap-2" onSubmit={(e) => { e.preventDefault(); form.post(url, { preserveScroll: true }); }}>
            <input
                aria-label={field.placeholder}
                placeholder={field.placeholder}
                required={field.required}
                value={form.data[field.name]}
                onChange={(e) => form.setData(field.name, e.target.value)}
                className={cn(input, field.className)}
            />
            <button disabled={form.processing} className={cn(button, tone)}>{label}</button>
            {form.errors[field.name] && <span className="text-[11px] font-bold text-rose-500">{form.errors[field.name]}</span>}
        </form>
    );
}

function PayoutPaid({ id, methods }: { id: number; methods: Record<string, string> }) {
    const form = useForm({ method: Object.keys(methods)[0], reference: '' });
    return (
        <form className="flex flex-wrap items-center gap-2" onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.money.payouts.paid', id), { preserveScroll: true }); }}>
            <NativeSelect aria-label="How it was paid" value={form.data.method} onChange={(e) => form.setData('method', e.target.value)} className="w-auto rounded-full py-1.5 pr-8 text-[12px]">
                {Object.entries(methods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </NativeSelect>
            <input aria-label="Transfer reference" placeholder="Transfer reference" required value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} className={cn(input, 'w-40')} />
            <button disabled={form.processing} className={cn(button, 'bg-mint-ink text-white')}>Mark paid</button>
        </form>
    );
}

import { Link } from '@inertiajs/react';
import { Building2, Check, Clock, Download, Mail, Plus } from 'lucide-react';
import { useState } from 'react';
import SessionsLayout, { field, primaryButton, quietButton } from '@/Layouts/SessionsLayout';
import { cn, money } from '@/lib/utils';

interface Props {
    plan: { tier: string; label: string; sessions: { used: number; included: number }; whatsapp: { used: number; included: number }; renews: string | null; payg: number };
    tiers: { key: string; label: string; price: number; description: string; current: boolean }[];
    payg: { quantity: number; price: number }[];
    history: { id: number; label: string; status: string; sessions: string; paid: number; date: string }[];
    support: string;
    /** A corporate rate agreed with VENTIQ: sessions invoiced monthly, no plan to buy. */
    corporate: { rate: number; free: number; since: string; month: { held: number; free: number; owed: number }; invoiced: string } | null;
    invoices: Invoice[];
    /** Paid by bank transfer, waiting for VENTIQ to see it. */
    transfers: { id: number; label: string; amount: number; reference: string; paidOn: string | null }[];
    /** VENTIQ's bank account, for paying invoices. */
    bank: { label: string; value: string }[];
}

function Transfers({ transfers }: { transfers: Props['transfers'] }) {
    if (transfers.length === 0) return null;

    return (
        <div className="mb-8 space-y-2">
            {transfers.map((t) => (
                <p key={t.id} className="flex items-start gap-3 rounded-2xl bg-amber-50 px-5 py-4 text-[12px] text-amber-800">
                    <Clock className="mt-0.5 size-4 shrink-0" />
                    <span><b>{t.label}, {money(t.amount)}</b>: you told us you paid by bank transfer{t.paidOn ? ` on ${t.paidOn}` : ''} (reference {t.reference}). It's added as soon as we see it in our account, usually within a working day.</span>
                </p>
            ))}
        </div>
    );
}

/** How to pay an invoice by transfer: VENTIQ's account, the invoice number as the reference. */
function PayInvoices({ bank, invoices }: { bank: Props['bank']; invoices: Invoice[] }) {
    if (bank.length === 0 || !invoices.some((i) => i.status === 'unpaid' || i.status === 'overdue')) return null;

    return (
        <div className="mb-10 rounded-2xl border border-gray-100 bg-white p-6">
            <p className="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-gray-400"><Building2 className="size-4" />Paying an invoice</p>
            <p className="mt-2 text-[12px] text-gray-500">Transfer to our account and use the <b className="text-brand">invoice number</b> as the reference. We mark it paid when it comes in.</p>
            <dl className="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                {bank.map((l) => (
                    <div key={l.label} className="flex justify-between gap-3 border-b border-dashed border-gray-100 pb-1.5 text-[12px]">
                        <dt className="font-bold text-gray-400">{l.label}</dt>
                        <dd className="text-right font-bold text-brand">{l.value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

interface Invoice {
    id: number;
    number: string;
    period: string;
    sessions: number;
    total: number;
    outstanding: number;
    status: 'unpaid' | 'paid' | 'overdue' | 'void';
    due: string;
    pdf: string;
}

const INVOICE_STATUS: Record<string, [string, string]> = {
    unpaid: ['To pay', 'bg-amber-50 text-amber-700'],
    overdue: ['Overdue', 'bg-rose-50 text-rose-600'],
    paid: ['Paid', 'bg-mint text-mint-ink'],
    void: ['Cancelled', 'bg-gray-100 text-gray-400'],
};

function Invoices({ invoices }: { invoices: Invoice[] }) {
    if (invoices.length === 0) return null;

    return (
        <>
            <h2 className="mb-3 text-[11px] font-black uppercase tracking-[0.2em] text-gray-400">Invoices</h2>
            <div className="mb-10 overflow-x-auto rounded-2xl border border-gray-100 bg-white">
                <table className="w-full min-w-[520px] text-left text-[12px]">
                    <thead className="bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                        <tr><th className="px-5 py-3">Invoice</th><th className="px-5 py-3">Month</th><th className="px-5 py-3">Sessions</th><th className="px-5 py-3">Due</th><th className="px-5 py-3">Status</th><th className="px-5 py-3 text-right">Amount</th><th className="px-5 py-3" /></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {invoices.map((i) => {
                            const [label, tone] = INVOICE_STATUS[i.status] ?? [i.status, 'bg-gray-100 text-gray-500'];
                            return (
                                <tr key={i.id}>
                                    <td className="px-5 py-3 font-bold text-brand">{i.number}</td>
                                    <td className="px-5 py-3 text-gray-600">{i.period}</td>
                                    <td className="px-5 py-3 text-gray-600">{i.sessions}</td>
                                    <td className="px-5 py-3 text-gray-600">{i.due}</td>
                                    <td className="px-5 py-3"><span className={cn('rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-widest', tone)}>{label}</span></td>
                                    <td className="px-5 py-3 text-right font-bold text-brand">{money(i.total)}{i.status !== 'paid' && i.outstanding < i.total && <span className="block text-[10px] font-medium text-gray-400">{money(i.outstanding)} left</span>}</td>
                                    <td className="px-5 py-3 text-right"><a href={i.pdf} className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-brand hover:text-action"><Download className="size-3" />PDF</a></td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </>
    );
}

/** This month's sessions, a bigger plan, or more sessions as you go. */
export default function Plan({ plan, tiers, payg, history, support, corporate, invoices, transfers, bank }: Props) {
    const [custom, setCustom] = useState('');
    const pct = (used: number, included: number) => (included > 0 ? Math.min(100, Math.round((used / included) * 100)) : 100);

    if (corporate) {
        return (
            <SessionsLayout title="Your Sessions plan" subtitle={`Corporate rate, agreed with VENTIQ since ${corporate.since}`} width="max-w-4xl">
                <div className="mb-10 grid gap-4 sm:grid-cols-3">
                    <div className="rounded-2xl border border-brand bg-white p-5">
                        <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">Your rate</p>
                        <p className="mt-2 text-2xl font-black text-brand">{money(corporate.rate)}<span className="text-[12px] text-gray-400"> a session</span></p>
                        <p className="mt-2 text-[11px] text-gray-400">{corporate.free > 0 ? `The first ${corporate.free === 1 ? 'session' : `${corporate.free} sessions`} each month free.` : 'Every session is billed.'} No limit on how many you hold.</p>
                    </div>
                    <div className="rounded-2xl border border-gray-100 bg-white p-5">
                        <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">Sessions this month</p>
                        <p className="mt-2 text-2xl font-black text-brand">{corporate.month.held}</p>
                        <p className="mt-2 text-[11px] text-gray-400">{corporate.month.free} free so far. A session counts when it starts.</p>
                    </div>
                    <div className="rounded-2xl border border-gray-100 bg-white p-5">
                        <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">Owed so far</p>
                        <p className="mt-2 text-2xl font-black text-brand">{money(corporate.month.owed)}</p>
                        <p className="mt-2 text-[11px] text-gray-400">Invoiced on {corporate.invoiced}, by email to your billing contact and Admins.</p>
                    </div>
                </div>
                <Invoices invoices={invoices} />
                <PayInvoices bank={bank} invoices={invoices} />
                <p className="text-[12px] text-gray-500">To change your rate or billing contact: <a href={`mailto:${support}`} className="font-bold text-brand underline"><Mail className="mr-0.5 inline size-3" />{support}</a></p>
            </SessionsLayout>
        );
    }

    return (
        <SessionsLayout title="Your Sessions plan" subtitle={plan.renews ? `${plan.label} plan · starts again ${plan.renews}` : `${plan.label} plan`} width="max-w-4xl">
            <Transfers transfers={transfers} />
            <div className="mb-10 grid gap-4 sm:grid-cols-3">
                {([['Sessions this month', plan.sessions], ['WhatsApp messages', plan.whatsapp]] as const).map(([title, q]) => (
                    <div key={title} className="rounded-2xl border border-gray-100 bg-white p-5">
                        <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">{title}</p>
                        <p className="mt-2 text-2xl font-black text-brand">{q.used}<span className="text-sm text-gray-300"> / {q.included}</span></p>
                        <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div className={cn('h-full rounded-full', q.used >= q.included ? 'bg-rose-500' : 'bg-action')} style={{ width: `${pct(q.used, q.included)}%` }} /></div>
                    </div>
                ))}
                <div className="rounded-2xl border border-gray-100 bg-white p-5">
                    <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">Pay as you go left</p>
                    <p className="mt-2 text-2xl font-black text-brand">{plan.payg}</p>
                    <p className="mt-2 text-[11px] text-gray-400">Used once this month's are gone. They don't expire.</p>
                </div>
            </div>

            <Invoices invoices={invoices} />
            <PayInvoices bank={bank} invoices={invoices} />

            <h2 className="mb-3 text-[11px] font-black uppercase tracking-[0.2em] text-gray-400">Plans</h2>
            <div className="mb-3 grid gap-4 sm:grid-cols-2">
                {tiers.map((t) => (
                    <div key={t.key} className={cn('flex flex-col rounded-2xl border bg-white p-6', t.current ? 'border-brand' : 'border-gray-100')}>
                        <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">{t.label}</p>
                        <p className="mt-1 text-2xl font-black text-brand">M{t.price.toLocaleString('en')}<span className="text-[12px] text-gray-400">/month</span></p>
                        <p className="mt-2 flex-1 text-[12px] leading-relaxed text-gray-500">{t.description}</p>
                        {t.current ? (
                            <span className="mt-5 inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-emerald-600"><Check className="size-4" />Your plan</span>
                        ) : (
                            <Link href={route('organization.session-plan.payment', { type: 'plan', tier: t.key })} className={cn(primaryButton, 'mt-5')}>Move to {t.label}</Link>
                        )}
                    </div>
                ))}
            </div>
            <p className="mb-10 text-[12px] text-gray-500">Need more than Business? A corporate rate per session, invoiced monthly, can be agreed with us: <a href={`mailto:${support}`} className="font-bold text-brand underline"><Mail className="mr-0.5 inline size-3" />{support}</a></p>

            <h2 className="mb-3 text-[11px] font-black uppercase tracking-[0.2em] text-gray-400">Add sessions as you go</h2>
            <div className="mb-10 rounded-2xl border border-gray-100 bg-white p-5">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {payg.map((p) => (
                        <Link key={p.quantity} href={route('organization.session-plan.payment', { type: 'payg', quantity: p.quantity })} className="rounded-2xl border border-gray-100 p-4 text-center transition-all hover:border-action">
                            <p className="text-xl font-black text-brand">{p.quantity}</p>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">{p.quantity === 1 ? 'session' : 'sessions'}</p>
                            <p className="mt-2 text-[13px] font-black text-action">{money(p.price).replace(/\.00$/, '')}</p>
                        </Link>
                    ))}
                </div>
                <form className="mt-4 flex gap-2" onSubmit={(e) => { e.preventDefault(); if (+custom > 0) window.location.href = route('organization.session-plan.payment', { type: 'payg', quantity: +custom }); }}>
                    <input aria-label="How many sessions" type="number" min={1} max={500} value={custom} onChange={(e) => setCustom(e.target.value)} placeholder="Another number" className={cn(field, 'py-3')} />
                    <button disabled={!(+custom > 0)} className={quietButton}><Plus className="size-3.5" />Add</button>
                </form>
            </div>

            {history.length > 0 && (
                <>
                    <h2 className="mb-3 text-[11px] font-black uppercase tracking-[0.2em] text-gray-400">History</h2>
                    <div className="overflow-x-auto rounded-2xl border border-gray-100 bg-white">
                        <table className="w-full min-w-[480px] text-left text-[12px]">
                            <thead className="bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                <tr><th className="px-5 py-3">Date</th><th className="px-5 py-3">What</th><th className="px-5 py-3">Sessions used</th><th className="px-5 py-3">Status</th><th className="px-5 py-3 text-right">Paid</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {history.map((h) => (
                                    <tr key={h.id}>
                                        <td className="px-5 py-3 text-gray-500">{h.date}</td>
                                        <td className="px-5 py-3 font-bold text-brand">{h.label}</td>
                                        <td className="px-5 py-3">{h.sessions}</td>
                                        <td className="px-5 py-3 capitalize text-gray-500">{h.status}</td>
                                        <td className="px-5 py-3 text-right font-bold">{money(h.paid)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </SessionsLayout>
    );
}

import { Head, router, useForm } from '@inertiajs/react';
import { Download, Handshake, Mail, Plus, RefreshCw, Wallet } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink } from '@/components/admin/cells';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { cn, money } from '@/lib/utils';

interface Agreement {
    id: number;
    organization: { id: number; name: string | null };
    rate: number;
    free_per_month: number;
    billing_email: string;
    notes: string;
    starts_on: string;
    startsLabel: string;
    ended: string | null;
    /** A pilot (M0): its last day. */
    pilotEnds: string | null;
    month: { held: number; free: number; owed: number } | null;
}

interface Invoice {
    id: number;
    number: string;
    organization: { id: number; name: string | null };
    period: string;
    sessions: number;
    free: number;
    total: number;
    outstanding: number;
    status: 'unpaid' | 'paid' | 'overdue' | 'void';
    due: string;
    khoebo: string | null;
    khoeboError: string | null;
    emailed: string | null;
    paid: string | null;
    pdf: string;
}

type Option = { value: number | string; label: string };

const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';
const STATUS: Record<string, [string, string]> = {
    unpaid: ['To pay', 'bg-amber-50 text-amber-700'],
    overdue: ['Overdue', 'bg-rose-50 text-rose-600'],
    paid: ['Paid', 'bg-mint text-mint-ink'],
    void: ['Cancelled', 'bg-gray-100 text-gray-400'],
};
const opts = { preserveScroll: true };

/** Corporate rates for Sessions: each organization's agreed price per session, and the monthly invoices. */
export default function CorporateRates({ agreements, invoices, organizations, methods, lastMonth }: {
    agreements: Agreement[];
    invoices: Invoice[];
    organizations: Option[];
    methods: Option[];
    lastMonth: string;
}) {
    const [editing, setEditing] = useState<Agreement | 'new' | null>(null);
    const [ending, setEnding] = useState<Agreement | null>(null);
    const [invoicing, setInvoicing] = useState(false);
    const [paying, setPaying] = useState<Invoice | null>(null);
    const owed = invoices.reduce((sum, i) => sum + (i.status === 'paid' || i.status === 'void' ? 0 : i.outstanding), 0);

    return (
        <AdminLayout
            title="Corporate rates"
            subtitle="A price per session agreed with an organization. Its sessions are never stopped by a plan; on the 1st, last month's go on one invoice, raised in Khoebo and emailed with the PDF."
            actions={
                <>
                    <Button type="button" variant="brand" className="px-5 py-3 text-[10px]" onClick={() => setEditing('new')}><Plus className="size-3.5" />Set a rate</Button>
                    <Button type="button" variant="outline" className="px-5 py-3 text-[10px]" onClick={() => setInvoicing(true)}><RefreshCw className="size-3.5" />Invoice a month now</Button>
                </>
            }
        >
            <Head title="Corporate rates" />

            <div className="mb-8 overflow-x-auto rounded-[1.5rem] border border-gray-100 bg-white shadow-sm">
                <table className="w-full min-w-[720px] text-left text-[12px]">
                    <thead className="bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                        <tr><th className="px-5 py-3">Organization</th><th className="px-5 py-3">Rate</th><th className="px-5 py-3">Free a month</th><th className="px-5 py-3">This month</th><th className="px-5 py-3">Invoices to</th><th className="px-5 py-3">Since</th><th className="px-5 py-3" /></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {agreements.map((a) => (
                            <tr key={a.id} className={cn(a.ended && 'opacity-50')}>
                                <td className="px-5 py-3"><OrgLink id={a.organization.id} name={a.organization.name} />{a.notes && <span className="block max-w-[220px] truncate text-[11px] text-gray-400" title={a.notes}>{a.notes}</span>}</td>
                                <td className="px-5 py-3 font-black text-brand">
                                    {a.pilotEnds ? <span className="rounded-full bg-lilac px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-lilac-ink">Pilot</span> : money(a.rate)}
                                </td>
                                <td className="px-5 py-3 text-gray-600">{a.free_per_month}</td>
                                <td className="px-5 py-3 text-gray-600">{a.month ? <>{a.month.held} held · <b className="text-brand">{money(a.month.owed)}</b></> : '—'}</td>
                                <td className="px-5 py-3 text-gray-500">{a.billing_email || 'Its Admins'}</td>
                                <td className="whitespace-nowrap px-5 py-3 text-gray-500">
                                    {a.ended ? `Ended ${a.ended}` : a.startsLabel}
                                    {a.pilotEnds && !a.ended && <span className="block text-[11px] font-bold text-lilac-ink">Ends {a.pilotEnds}</span>}
                                </td>
                                <td className="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" onClick={() => setEditing(a)} className="text-[10px] font-black uppercase tracking-widest text-brand hover:text-action">{a.ended ? 'Start again' : 'Change'}</button>
                                    {a.pilotEnds && (
                                        <button type="button" onClick={() => router.post(route('ventiq.corporate.renew', a.id), {}, opts)} className="ml-4 text-[10px] font-black uppercase tracking-widest text-lilac-ink hover:text-action">
                                            Renew 3 months
                                        </button>
                                    )}
                                    {!a.ended && <button type="button" onClick={() => setEnding(a)} className="ml-4 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600">End</button>}
                                </td>
                            </tr>
                        ))}
                        {agreements.length === 0 && <tr><td colSpan={7} className="px-5 py-10 text-center text-[12px] italic text-gray-400"><Handshake className="mx-auto mb-2 size-6 text-gray-300" />No corporate rates yet.</td></tr>}
                    </tbody>
                </table>
            </div>

            <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                <h2 className="text-[11px] font-black uppercase tracking-[0.2em] text-gray-400">Invoices</h2>
                {owed > 0 && <p className="text-[12px] font-bold text-gray-500">Owed to VENTIQ: <span className="text-brand">{money(owed)}</span></p>}
            </div>
            <div className="overflow-x-auto rounded-[1.5rem] border border-gray-100 bg-white shadow-sm">
                <table className="w-full min-w-[860px] text-left text-[12px]">
                    <thead className="bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                        <tr><th className="px-5 py-3">Invoice</th><th className="px-5 py-3">Organization</th><th className="px-5 py-3">Month</th><th className="px-5 py-3">Sessions</th><th className="px-5 py-3">Khoebo</th><th className="px-5 py-3">Status</th><th className="px-5 py-3 text-right">Amount</th><th className="px-5 py-3" /></tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {invoices.map((i) => {
                            const [label, tone] = STATUS[i.status] ?? [i.status, 'bg-gray-100 text-gray-500'];
                            return (
                                <tr key={i.id}>
                                    <td className="px-5 py-3"><span className="font-black text-brand">{i.number}</span><span className="block text-[11px] text-gray-400">{i.emailed ? `Emailed ${i.emailed}` : 'Not emailed'}</span></td>
                                    <td className="px-5 py-3"><OrgLink id={i.organization.id} name={i.organization.name} /></td>
                                    <td className="px-5 py-3 text-gray-600">{i.period}</td>
                                    <td className="px-5 py-3 text-gray-600">{i.sessions}{i.free > 0 && <span className="text-gray-400"> ({i.free} free)</span>}</td>
                                    <td className="px-5 py-3">
                                        {i.khoebo ? <span className="text-gray-600">{i.khoebo}</span> : (
                                            <button type="button" title={i.khoeboError ?? ''} onClick={() => router.post(route('ventiq.corporate.khoebo', i.id), {}, opts)} className="text-[10px] font-black uppercase tracking-widest text-rose-600 hover:underline">Not raised · try again</button>
                                        )}
                                    </td>
                                    <td className="px-5 py-3"><span className={cn('rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-widest', tone)}>{label}</span><span className="mt-1 block text-[11px] text-gray-400">{i.paid ? `Paid ${i.paid}` : `Due ${i.due}`}</span></td>
                                    <td className="px-5 py-3 text-right font-black text-brand">{money(i.total)}{i.status !== 'paid' && i.outstanding < i.total && <span className="block text-[10px] font-medium text-gray-400">{money(i.outstanding)} left</span>}</td>
                                    <td className="whitespace-nowrap px-5 py-3 text-right">
                                        <a href={i.pdf} aria-label={`Download ${i.number}`} className="inline-flex p-1.5 text-gray-400 hover:text-brand"><Download className="size-3.5" /></a>
                                        <button type="button" aria-label={`Email ${i.number} again`} onClick={() => router.post(route('ventiq.corporate.resend', i.id), {}, opts)} className="p-1.5 text-gray-400 hover:text-brand"><Mail className="size-3.5" /></button>
                                        {i.status !== 'paid' && <button type="button" onClick={() => setPaying(i)} className="ml-2 inline-flex items-center gap-1 rounded-full bg-mint px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-mint-ink"><Wallet className="size-3" />Paid</button>}
                                    </td>
                                </tr>
                            );
                        })}
                        {invoices.length === 0 && <tr><td colSpan={8} className="px-5 py-10 text-center text-[12px] italic text-gray-400">No invoices yet. The first go out on the 1st of next month.</td></tr>}
                    </tbody>
                </table>
            </div>

            <Dialog open={editing !== null} onOpenChange={(o) => !o && setEditing(null)}>
                <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto rounded-[2rem]">{editing && <RateForm agreement={editing === 'new' ? null : editing} organizations={organizations} onDone={() => setEditing(null)} />}</DialogContent>
            </Dialog>
            <Dialog open={invoicing} onOpenChange={setInvoicing}>
                <DialogContent className="max-w-md rounded-[2rem]"><InvoiceNowForm organizations={organizations.filter((o) => agreements.some((a) => a.organization.id === o.value))} lastMonth={lastMonth} onDone={() => setInvoicing(false)} /></DialogContent>
            </Dialog>
            <Dialog open={paying !== null} onOpenChange={(o) => !o && setPaying(null)}>
                <DialogContent className="max-w-md rounded-[2rem]">{paying && <PaymentForm invoice={paying} methods={methods} onDone={() => setPaying(null)} />}</DialogContent>
            </Dialog>
            <ConfirmDialog open={ending !== null} onOpenChange={(o) => !o && setEnding(null)} title={`End ${ending?.organization.name}'s corporate rate?`} confirmLabel="End it" onConfirm={() => ending && router.post(route('ventiq.corporate.end', ending.id), {}, { ...opts, onFinish: () => setEnding(null) })}>
                Sessions already held are still invoiced on the 1st. New sessions use its plan again, with its free session a month.
            </ConfirmDialog>
        </AdminLayout>
    );
}

function RateForm({ agreement, organizations, onDone }: { agreement: Agreement | null; organizations: Option[]; onDone: () => void }) {
    const form = useForm({
        organization_id: (agreement?.organization.id ?? '') as number | '',
        rate: agreement?.rate ?? 500,
        free_per_month: agreement?.free_per_month ?? 1,
        billing_email: agreement?.billing_email ?? '',
        notes: agreement?.notes ?? '',
        starts_on: agreement && !agreement.ended ? agreement.starts_on : new Date().toISOString().slice(0, 10),
    });

    return (
        <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.corporate.store'), { ...opts, onSuccess: onDone }); }}>
            <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">{agreement ? `${agreement.organization.name}'s rate` : 'Set a corporate rate'}</DialogTitle>
            <Field label="Organization" htmlFor="cr-org" error={form.errors.organization_id}>
                <NativeSelect id="cr-org" required disabled={!!agreement} value={form.data.organization_id} onChange={(e) => form.setData('organization_id', Number(e.target.value))}>
                    <option value="" disabled>Choose…</option>
                    {organizations.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                </NativeSelect>
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Per session (M)" htmlFor="cr-rate" error={form.errors.rate} hint={Number(form.data.rate) === 0 ? 'M0 is a pilot: three months, then it ends unless you renew it.' : undefined}>
                    <Input id="cr-rate" type="number" min={0} step="0.01" required className={field} value={form.data.rate} onChange={(e) => form.setData('rate', Number(e.target.value))} />
                </Field>
                <Field label="Free a month" htmlFor="cr-free" error={form.errors.free_per_month}>
                    <Input id="cr-free" type="number" min={0} max={100} required className={field} value={form.data.free_per_month} onChange={(e) => form.setData('free_per_month', Number(e.target.value))} />
                </Field>
            </div>
            <Field label={<>Invoices also to <span className="normal-case text-gray-300">(optional: their finance email; Admins always get it)</span></>} htmlFor="cr-email" error={form.errors.billing_email}>
                <Input id="cr-email" type="email" className={field} value={form.data.billing_email} onChange={(e) => form.setData('billing_email', e.target.value)} placeholder="accounts@example.org" />
            </Field>
            <Field label="From" htmlFor="cr-from" error={form.errors.starts_on}>
                <Input id="cr-from" type="date" required className={field} value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
            </Field>
            <Field label={<>Notes <span className="normal-case text-gray-300">(the deal, for VENTIQ only)</span></>} htmlFor="cr-notes">
                <Textarea id="cr-notes" rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </Field>
            <p className="text-[11px] text-gray-500">Applies to sessions started from now on. Sessions already held keep their price.</p>
            <Button type="submit" variant="brand" className="w-full py-3 text-[10px]" disabled={form.processing}>Save the rate</Button>
        </form>
    );
}

function InvoiceNowForm({ organizations, lastMonth, onDone }: { organizations: Option[]; lastMonth: string; onDone: () => void }) {
    const form = useForm({ organization_id: '' as number | '', month: lastMonth });

    return (
        <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.corporate.invoice'), { ...opts, onSuccess: onDone }); }}>
            <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">Invoice a month now</DialogTitle>
            <p className="text-[12px] text-gray-500">Rather than waiting for the 1st. A month is only ever invoiced once.</p>
            <Field label="Organization" htmlFor="in-org" error={form.errors.organization_id}>
                <NativeSelect id="in-org" required value={form.data.organization_id} onChange={(e) => form.setData('organization_id', Number(e.target.value))}>
                    <option value="" disabled>Choose…</option>
                    {organizations.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                </NativeSelect>
            </Field>
            <Field label="Month" htmlFor="in-month" error={form.errors.month}>
                <Input id="in-month" type="month" required max={lastMonth} className={field} value={form.data.month} onChange={(e) => form.setData('month', e.target.value)} />
            </Field>
            <Button type="submit" variant="brand" className="w-full py-3 text-[10px]" disabled={form.processing}>Make and email the invoice</Button>
        </form>
    );
}

function PaymentForm({ invoice, methods, onDone }: { invoice: Invoice; methods: Option[]; onDone: () => void }) {
    const form = useForm({ amount: invoice.outstanding, date: new Date().toISOString().slice(0, 10), method: String(methods[0]?.value ?? 'bank_transfer'), reference: '' });

    return (
        <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.corporate.pay', invoice.id), { ...opts, onSuccess: onDone }); }}>
            <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">Payment for {invoice.number}</DialogTitle>
            <p className="text-[12px] text-gray-500">{invoice.organization.name} · {money(invoice.outstanding)} owed.{invoice.khoebo && ' Recorded in Khoebo too.'}</p>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Amount (M)" htmlFor="pay-amount" error={form.errors.amount}>
                    <Input id="pay-amount" type="number" min={0.01} step="0.01" max={invoice.outstanding} required className={field} value={form.data.amount} onChange={(e) => form.setData('amount', Number(e.target.value))} />
                </Field>
                <Field label="Date" htmlFor="pay-date" error={form.errors.date}>
                    <Input id="pay-date" type="date" required className={field} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </Field>
            </div>
            <Field label="How" htmlFor="pay-method" error={form.errors.method}>
                <NativeSelect id="pay-method" value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                    {methods.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                </NativeSelect>
            </Field>
            <Field label={<>Reference <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="pay-ref">
                <Input id="pay-ref" className={field} value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} placeholder="Bank or mobile money reference" />
            </Field>
            <Button type="submit" variant="brand" className="w-full bg-mint-ink py-3 text-[10px]" disabled={form.processing}>Record the payment</Button>
        </form>
    );
}

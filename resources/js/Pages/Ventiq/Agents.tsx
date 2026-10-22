import { Head, router, useForm } from '@inertiajs/react';
import { BadgeCheck, Copy, Plus, Wallet } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink, Pill, smallButton } from '@/components/admin/cells';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import { Field } from '@/components/field';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { money } from '@/lib/utils';

interface Row {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    district: string | null;
    motivation: string | null;
    status: string;
    token: string;
    referralUrl: string;
    organizations: number;
    certified: { passed: boolean; percent: number; attempts: number } | null;
    owed: number;
    paid: number;
    applied: string | null;
}

interface Detail {
    id: number;
    name: string;
    organizations: { id: number; name: string; events: number; joined: string | null }[];
    earnings: { id: number; type: string; organization: string | null; amount: number; status: string; notes: string | null; date: string | null; paid: string | null }[];
    organizationOptions: { value: number; label: string }[];
}

// Open an agent's earnings and organizations without leaving the list (?agent=).
const open = (id: number | null) => {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('agent', String(id));
    else url.searchParams.delete('agent');
    router.get(url.pathname + url.search, {}, { only: ['agent'], preserveState: true, preserveScroll: true, replace: true });
};

/** VENTIQ's sales agents: applications, what they're owed, and paying them. */
export default function Agents({ rows, state, pending, methods, agent }: { rows: Paginated<Row>; state: TableState; pending: number; methods: Record<string, string>; agent: Detail | null }) {
    const [reviewing, setReviewing] = useState<Row | null>(null);
    const [paying, setPaying] = useState<Row | null>(null);
    const [declining, setDeclining] = useState<Row | null>(null);

    const columns: Column<Row>[] = [
        {
            id: 'name',
            header: 'Agent',
            meta: { sort: 'name' },
            cell: ({ row: { original: a } }) => (
                <button type="button" onClick={() => open(a.id)} className="block min-w-0 text-left">
                    <span className="block font-black text-brand hover:text-action">{a.name}</span>
                    <span className="block text-[11px] text-gray-400">{a.district ?? 'No district'} · {a.phone ?? a.email}</span>
                </button>
            ),
        },
        { id: 'status', header: 'Status', cell: ({ row }) => <Pill value={row.original.status === 'approved' ? 'active' : row.original.status === 'rejected' ? 'cancelled' : row.original.status} label={row.original.status} /> },
        {
            id: 'certified',
            header: 'Certified',
            cell: ({ row: { original: a } }) =>
                !a.certified ? <span className="text-gray-300">Not yet</span>
                : a.certified.passed ? <span className="font-bold text-mint-ink"><BadgeCheck className="mr-0.5 inline size-3.5" />{a.certified.percent}%</span>
                : <span className="text-rose-600">{a.certified.percent}% · {a.certified.attempts} {a.certified.attempts === 1 ? 'try' : 'tries'}</span>,
        },
        { id: 'organizations', header: 'Brought in', meta: { className: 'text-right' }, cell: ({ row }) => row.original.organizations },
        { id: 'owed', header: 'Owed', meta: { sort: 'owed', className: 'text-right' }, cell: ({ row }) => (row.original.owed > 0 ? <span className="font-black text-action-ink">{money(row.original.owed)}</span> : <span className="text-gray-300">—</span>) },
        { id: 'paid', header: 'Paid so far', meta: { className: 'text-right' }, cell: ({ row }) => <span className="text-gray-500">{money(row.original.paid)}</span> },
        {
            id: 'actions',
            header: '',
            meta: { className: 'text-right' },
            cell: ({ row: { original: a } }) => (
                <div className="flex justify-end gap-1.5">
                    {a.status === 'pending' && <button type="button" onClick={() => setReviewing(a)} className="rounded-full bg-mint-ink px-3 py-1 text-[10px] font-black uppercase tracking-widest text-white">Review</button>}
                    {a.owed > 0 && <button type="button" onClick={() => setPaying(a)} className={smallButton}><Wallet className="size-3" />Pay</button>}
                    {a.status === 'approved' && (
                        <button type="button" title="Copy referral link" onClick={() => void navigator.clipboard?.writeText(a.referralUrl)} className={smallButton}><Copy className="size-3" /></button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AdminLayout title="Agents" subtitle={pending ? `${pending} ${pending === 1 ? 'application' : 'applications'} waiting for review.` : "VENTIQ's sales agents: who they've brought in and what they're owed."}>
            <Head title="Agents" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Name, email, phone or referral token"
                filters={[
                    { key: 'status', label: 'Status', options: [{ value: 'pending', label: 'Waiting for review' }, { value: 'approved', label: 'Approved' }, { value: 'rejected', label: 'Declined' }] },
                    { key: 'owed', label: 'Owed', options: [{ value: '1', label: 'Owed money' }] },
                ]}
                empty="No agents match."
            />

            {/* Review an application */}
            <Dialog open={reviewing !== null} onOpenChange={(o) => !o && setReviewing(null)}>
                <DialogContent className="rounded-[2rem]">
                    {reviewing && (
                        <div className="space-y-4 p-7">
                            <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">{reviewing.name}</DialogTitle>
                            <dl className="space-y-1.5 text-[13px]">
                                {([['Email', reviewing.email], ['Phone', reviewing.phone], ['District', reviewing.district], ['Applied', reviewing.applied]] as const).map(([k, v]) => (
                                    <div key={k} className="flex justify-between gap-3"><dt className="text-gray-500">{k}</dt><dd className="font-bold text-brand">{v ?? '—'}</dd></div>
                                ))}
                            </dl>
                            {reviewing.motivation && <p className="rounded-2xl bg-slate-50 p-4 text-[13px] italic text-gray-600">"{reviewing.motivation}"</p>}
                            <p className="text-[11px] text-gray-500">Approving makes them an account with the sales agent role and emails them a link to set their password.</p>
                            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <Button type="button" variant="outline" onClick={() => { setDeclining(reviewing); setReviewing(null); }}>Decline</Button>
                                <Button type="button" variant="brand" className="bg-mint-ink" onClick={() => router.post(route('ventiq.agents.approve', reviewing.id), {}, { preserveScroll: true, onFinish: () => setReviewing(null) })}>
                                    <BadgeCheck className="size-4" />Approve
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog open={declining !== null} onOpenChange={(o) => !o && setDeclining(null)} title={`Decline ${declining?.name}'s application?`} confirmLabel="Decline" onConfirm={() => router.post(route('ventiq.agents.decline', declining!.id), {}, { preserveScroll: true, onFinish: () => setDeclining(null) })} />

            {paying && <PayDialog agent={paying} methods={methods} onClose={() => setPaying(null)} />}

            {/* An agent's organizations and earnings */}
            <Dialog open={agent !== null} onOpenChange={(o) => !o && open(null)}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto rounded-[2rem]">
                    {agent && <AgentDetail agent={agent} />}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

function PayDialog({ agent, methods, onClose }: { agent: Row; methods: Record<string, string>; onClose: () => void }) {
    const form = useForm({ method: Object.keys(methods)[0], reference: '' });
    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="rounded-[2rem]">
                <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.agents.payout', agent.id), { preserveScroll: true, onSuccess: onClose }); }}>
                    <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">Pay {agent.name} {money(agent.owed)}</DialogTitle>
                    <p className="text-[12px] text-gray-500">Send the money first, then record it here. Everything they're owed is marked paid and they get a receipt by email.</p>
                    <Field label="Paid with" htmlFor="pay-method" error={form.errors.method}>
                        <NativeSelect id="pay-method" value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                            {Object.entries(methods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </NativeSelect>
                    </Field>
                    <Field label="Transfer reference" htmlFor="pay-ref" error={form.errors.reference}>
                        <Input id="pay-ref" required className="rounded-2xl px-4 py-3" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                    </Field>
                    <Button type="submit" variant="brand" className="w-full bg-mint-ink py-3 text-[10px]" disabled={form.processing}>Record payment</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AgentDetail({ agent }: { agent: Detail }) {
    const form = useForm({ amount: '', type: 'commission', organization_id: '' as number | '', notes: '' });

    return (
        <div className="space-y-6 p-7">
            <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">{agent.name}</DialogTitle>

            <div>
                <p className="mb-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Organizations brought in</p>
                {agent.organizations.length === 0 ? <p className="text-[12px] text-gray-400">None yet.</p> : (
                    <ul className="space-y-1.5 text-[12px]">
                        {agent.organizations.map((o) => (
                            <li key={o.id} className="flex justify-between gap-3"><OrgLink id={o.id} name={o.name} /><span className="text-gray-400">{o.events} {o.events === 1 ? 'event' : 'events'} · joined {o.joined}</span></li>
                        ))}
                    </ul>
                )}
            </div>

            <div>
                <p className="mb-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Earnings</p>
                {agent.earnings.length === 0 ? <p className="text-[12px] text-gray-400">Nothing yet.</p> : (
                    <ul className="divide-y divide-gray-50 text-[12px]">
                        {agent.earnings.map((e) => (
                            <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span className="min-w-0">
                                    <span className="font-bold text-brand">{e.type}</span>{e.organization && ` · ${e.organization}`} <span className="text-gray-400">· {e.date}</span>
                                    {e.notes && <span className="block text-[11px] text-gray-400">{e.notes}</span>}
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="font-black">{money(e.amount)}</span>
                                    <Pill value={e.status === 'approved' ? 'pending' : e.status === 'paid' ? 'settled' : e.status} label={e.status === 'approved' ? 'owed' : e.status} />
                                </span>
                                {e.paid && <span className="w-full text-[10px] text-gray-400">Paid {e.paid}</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <form className="space-y-3 rounded-2xl bg-slate-50 p-4" onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.agents.earning', agent.id), { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                <p className="text-[13px] font-black text-brand"><Plus className="mr-1 inline size-3.5" />Record an earning</p>
                <p className="text-[11px] text-gray-500">For a commission or bonus agreed with VENTIQ. It's added to what they're owed, ready to pay.</p>
                <div className="grid gap-3 sm:grid-cols-3">
                    <Field label="Amount (M)" htmlFor="earn-amount" error={form.errors.amount}>
                        <Input id="earn-amount" type="number" min="0.01" step="0.01" required className="rounded-2xl bg-white px-4 py-3" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                    </Field>
                    <Field label="Type" htmlFor="earn-type">
                        <NativeSelect id="earn-type" className="bg-white" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            <option value="commission">Commission</option>
                            <option value="milestone_bonus">Bonus</option>
                        </NativeSelect>
                    </Field>
                    <Field label="For" htmlFor="earn-org">
                        <NativeSelect id="earn-org" className="bg-white" value={form.data.organization_id} onChange={(e) => form.setData('organization_id', e.target.value ? Number(e.target.value) : '')}>
                            <option value="">No organization</option>
                            {agent.organizationOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </NativeSelect>
                    </Field>
                </div>
                <Input aria-label="Note" placeholder="Note (optional), e.g. what it's for" className="rounded-2xl bg-white px-4 py-3" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                <Button type="submit" variant="brand" className="py-3 text-[10px]" disabled={form.processing}>Record</Button>
            </form>
        </div>
    );
}

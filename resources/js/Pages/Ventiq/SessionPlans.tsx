import { Head, router, useForm } from '@inertiajs/react';
import { Building2, Check, Plus, RefreshCw, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink, Pill } from '@/components/admin/cells';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import { Field } from '@/components/field';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { money } from '@/lib/utils';

interface Row {
    id: number;
    organization: { id: number; name: string | null };
    tier: string;
    tierLabel: string;
    status: string;
    sessions: string;
    whatsapp: string;
    ends: string | null;
    price: number;
    created: string | null;
}

interface Tier {
    value: string;
    label: string;
    description: string;
    price: number | null;
    sessions: number;
    whatsapp: number;
    sms: number;
}

type Option = { value: number; label: string };

interface Transfer {
    id: number;
    organization: { id: number; name: string | null };
    label: string;
    amount: number;
    reference: string;
    paidOn: string | null;
    by: string | null;
    note: string | null;
    claimed: string;
}

const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** Organizations' Sessions plans, and plans or pay-as-you-go sessions agreed outside the app. */
export default function SessionPlans({ rows, state, tiers, paygPresets, organizations, transfers }: {
    rows: Paginated<Row>;
    state: TableState;
    tiers: Tier[];
    paygPresets: { quantity: number; price: number }[];
    organizations: Option[];
    transfers: Transfer[];
}) {
    const [dialog, setDialog] = useState<null | 'plan' | 'payg'>(null);
    const [removing, setRemoving] = useState<Row | null>(null);

    const columns: Column<Row>[] = [
        { id: 'organization', header: 'Organization', cell: ({ row }) => <OrgLink id={row.original.organization.id} name={row.original.organization.name} /> },
        { id: 'tier', header: 'Plan', cell: ({ row }) => <span className="font-black text-brand">{row.original.tierLabel}</span> },
        { id: 'status', header: 'Status', cell: ({ row }) => <Pill value={row.original.status} /> },
        { id: 'sessions', header: 'Sessions used', cell: ({ row }) => row.original.sessions },
        { id: 'whatsapp', header: 'WhatsApp used', cell: ({ row }) => row.original.whatsapp },
        { id: 'ends', header: 'Renews / ends', meta: { sort: 'ends' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.ends ?? 'Never'}</span> },
        { id: 'price', header: 'Paid', meta: { className: 'text-right' }, cell: ({ row }) => <span className="font-bold">{money(row.original.price)}</span> },
        { id: 'remove', header: '', meta: { className: 'text-right' }, cell: ({ row }) => <button type="button" aria-label="Remove" onClick={() => setRemoving(row.original)} className="text-gray-300 hover:text-rose-600"><Trash2 className="size-3.5" /></button> },
    ];

    return (
        <AdminLayout
            title="Session plans"
            subtitle="What each organization has for Sessions. Organizations change their own plan and pay by mobile money or bank transfer; use these for a deal agreed with VENTIQ."
            actions={
                <>
                    <Button type="button" variant="brand" className="px-5 py-3 text-[10px]" onClick={() => setDialog('plan')}><RefreshCw className="size-3.5" />Change a plan</Button>
                    <Button type="button" variant="brand" className="bg-mint-ink px-5 py-3 text-[10px]" onClick={() => setDialog('payg')}><Plus className="size-3.5" />Add sessions</Button>
                </>
            }
        >
            <Head title="Session plans" />
            <Transfers transfers={transfers} />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Organization"
                filters={[
                    { key: 'tier', label: 'Plan', options: [...tiers.map((t) => ({ value: t.value, label: t.label })), { value: 'payg', label: 'Pay as you go' }] },
                    { key: 'status', label: 'Status', options: ['active', 'expired', 'cancelled'].map((v) => ({ value: v, label: v })) },
                ]}
                empty="No plans yet."
            />

            <Dialog open={dialog !== null} onOpenChange={(o) => !o && setDialog(null)}>
                <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto rounded-[2rem]">
                    {dialog === 'plan' && <PlanForm tiers={tiers} organizations={organizations} onDone={() => setDialog(null)} />}
                    {dialog === 'payg' && <PaygForm presets={paygPresets} organizations={organizations} onDone={() => setDialog(null)} />}
                </DialogContent>
            </Dialog>

            <ConfirmDialog open={removing !== null} onOpenChange={(o) => !o && setRemoving(null)} title={`Remove ${removing?.organization.name}'s ${removing?.tierLabel} plan?`} confirmLabel="Remove" onConfirm={() => router.delete(route('ventiq.session-plans.destroy', removing!.id), { preserveScroll: true, onFinish: () => setRemoving(null) })}>
                Use this only for a plan made by mistake.
            </ConfirmDialog>
        </AdminLayout>
    );
}

/** Bank transfers organizations say they've made: find each in the account, then confirm (the plan is applied) or say it isn't there. */
function Transfers({ transfers }: { transfers: Transfer[] }) {
    const [rejecting, setRejecting] = useState<Transfer | null>(null);
    const [reason, setReason] = useState('');
    const [refs, setRefs] = useState<Record<number, string>>({});
    if (transfers.length === 0) return null;

    return (
        <div className="mb-8 rounded-[2rem] border-2 border-amber-200 bg-amber-50/50 p-6">
            <p className="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-amber-700"><Building2 className="size-4" />Bank transfers to check · {transfers.length}</p>
            <p className="mt-1 text-[12px] text-gray-500">Find each in the account by its reference. Confirming adds the plan or sessions and emails the organization's Admins.</p>
            <div className="mt-4 space-y-3">
                {transfers.map((t) => (
                    <div key={t.id} className="flex flex-wrap items-center gap-4 rounded-2xl bg-white p-4">
                        <div className="min-w-[220px] flex-1">
                            <p className="text-[13px] font-black text-brand"><OrgLink id={t.organization.id} name={t.organization.name} /> · {money(t.amount)}</p>
                            <p className="text-[12px] text-gray-600">{t.label} · reference <b className="font-mono">{t.reference}</b></p>
                            <p className="text-[11px] text-gray-400">Paid {t.paidOn ?? '?'}, said by {t.by ?? 'them'} {t.claimed}{t.note ? ` · “${t.note}”` : ''}</p>
                        </div>
                        <Input aria-label="Bank reference (optional)" placeholder="Bank reference (optional)" className="w-48 rounded-xl px-3 py-2 text-[12px]" value={refs[t.id] ?? ''} onChange={(e) => setRefs({ ...refs, [t.id]: e.target.value })} />
                        <Button type="button" variant="brand" className="bg-mint-ink px-4 py-2.5 text-[10px]" onClick={() => router.post(route('ventiq.session-plans.transfers.confirm', t.id), { bank_reference: refs[t.id] ?? '' }, { preserveScroll: true })}><Check className="size-3.5" />It's in: confirm</Button>
                        <button type="button" onClick={() => { setRejecting(t); setReason(''); }} className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600"><X className="size-3.5" />Not found</button>
                    </div>
                ))}
            </div>
            <Dialog open={rejecting !== null} onOpenChange={(o) => !o && setRejecting(null)}>
                <DialogContent className="max-w-md rounded-[2rem]">
                    <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); router.post(route('ventiq.session-plans.transfers.reject', rejecting!.id), { reason }, { preserveScroll: true, onSuccess: () => setRejecting(null) }); }}>
                        <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">Not found in the account</DialogTitle>
                        <p className="text-[12px] text-gray-500">{rejecting?.organization.name}'s Admins are emailed this, and nothing is added. They can reply with proof of payment.</p>
                        <Field label="Why" htmlFor="tr-reason">
                            <Textarea id="tr-reason" rows={3} required value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. No transfer with this reference has reached us yet." />
                        </Field>
                        <Button type="submit" variant="brand" className="w-full py-3 text-[10px]" disabled={!reason.trim()}>Tell them</Button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function OrganizationField({ organizations, value, onChange, error }: { organizations: Option[]; value: number | ''; onChange: (v: number) => void; error?: string }) {
    return (
        <Field label="Organization" htmlFor="sp-org" error={error}>
            <NativeSelect id="sp-org" required value={value} onChange={(e) => onChange(Number(e.target.value))}>
                <option value="" disabled>Choose…</option>
                {organizations.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </NativeSelect>
        </Field>
    );
}

function PlanForm({ tiers, organizations, onDone }: { tiers: Tier[]; organizations: Option[]; onDone: () => void }) {
    const start = tiers.find((t) => t.value === 'team') ?? tiers[0];
    const form = useForm({ organization_id: '' as number | '', tier: start.value, sessions_included: start.sessions, whatsapp_included: start.whatsapp, sms_included: start.sms, price_paid: start.price ?? 0, notes: '' });
    const pick = (t: Tier) => form.setData((d) => ({ ...d, tier: t.value, sessions_included: t.sessions, whatsapp_included: t.whatsapp, sms_included: t.sms, price_paid: t.price ?? 0 }));

    return (
        <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.session-plans.plan'), { preserveScroll: true, onSuccess: onDone }); }}>
            <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">Change a plan</DialogTitle>
            <OrganizationField organizations={organizations} value={form.data.organization_id} onChange={(v) => form.setData('organization_id', v)} error={form.errors.organization_id} />
            <div className="space-y-2">
                {tiers.map((t) => (
                    <label key={t.value} className={`flex cursor-pointer gap-3 rounded-2xl border-2 p-3 ${form.data.tier === t.value ? 'border-action bg-action-soft' : 'border-slate-50 bg-slate-50'}`}>
                        <input type="radio" name="tier" checked={form.data.tier === t.value} onChange={() => pick(t)} className="mt-0.5 accent-action" />
                        <span>
                            <span className="block text-[13px] font-black text-brand">{t.label} · {t.price === null ? 'Custom' : t.price === 0 ? 'Free' : `${money(t.price)}/month`}</span>
                            <span className="block text-[11px] text-gray-500">{t.description}</span>
                        </span>
                    </label>
                ))}
            </div>
            <div className="grid grid-cols-3 gap-3">
                {([['sessions_included', 'Sessions'], ['whatsapp_included', 'WhatsApp'], ['sms_included', 'SMS']] as const).map(([key, label]) => (
                    <Field key={key} label={label} htmlFor={`sp-${key}`} error={form.errors[key]}>
                        <Input id={`sp-${key}`} type="number" min={0} required className={field} value={form.data[key]} onChange={(e) => form.setData(key, Number(e.target.value))} />
                    </Field>
                ))}
            </div>
            <Field label="Price paid (M)" htmlFor="sp-price" error={form.errors.price_paid}>
                <Input id="sp-price" type="number" min={0} step="0.01" required className={field} value={form.data.price_paid} onChange={(e) => form.setData('price_paid', Number(e.target.value))} />
            </Field>
            <Field label={<>Notes <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="sp-notes">
                <Textarea id="sp-notes" rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </Field>
            <Button type="submit" variant="brand" className="w-full py-3 text-[10px]" disabled={form.processing}>Change plan</Button>
        </form>
    );
}

function PaygForm({ presets, organizations, onDone }: { presets: { quantity: number; price: number }[]; organizations: Option[]; onDone: () => void }) {
    const form = useForm({ organization_id: '' as number | '', quantity: presets[0]?.quantity ?? 1, price_paid: presets[0]?.price ?? 0, notes: '' });

    return (
        <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.session-plans.payg'), { preserveScroll: true, onSuccess: onDone }); }}>
            <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">Add pay-as-you-go sessions</DialogTitle>
            <OrganizationField organizations={organizations} value={form.data.organization_id} onChange={(v) => form.setData('organization_id', v)} error={form.errors.organization_id} />
            <div className="flex flex-wrap gap-2">
                {presets.map((p) => (
                    <button key={p.quantity} type="button" onClick={() => form.setData((d) => ({ ...d, quantity: p.quantity, price_paid: p.price }))} className={`rounded-full border-2 px-4 py-2 text-[11px] font-black ${form.data.quantity === p.quantity ? 'border-action bg-action-soft text-action-ink' : 'border-slate-100 text-gray-600'}`}>
                        {p.quantity} · {money(p.price)}
                    </button>
                ))}
            </div>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Sessions" htmlFor="pg-qty" error={form.errors.quantity}>
                    <Input id="pg-qty" type="number" min={1} required className={field} value={form.data.quantity} onChange={(e) => form.setData('quantity', Number(e.target.value))} />
                </Field>
                <Field label="Price paid (M)" htmlFor="pg-price" error={form.errors.price_paid}>
                    <Input id="pg-price" type="number" min={0} step="0.01" className={field} value={form.data.price_paid} onChange={(e) => form.setData('price_paid', Number(e.target.value))} />
                </Field>
            </div>
            <Field label={<>Notes <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="pg-notes">
                <Textarea id="pg-notes" rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </Field>
            <Button type="submit" variant="brand" className="w-full bg-mint-ink py-3 text-[10px]" disabled={form.processing}>Add sessions</Button>
        </form>
    );
}

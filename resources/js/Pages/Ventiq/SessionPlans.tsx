import { Head, router, useForm } from '@inertiajs/react';
import { Plus, RefreshCw, Trash2 } from 'lucide-react';
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

const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** Organizations' Sessions plans, and plans or pay-as-you-go sessions agreed outside the app. */
export default function SessionPlans({ rows, state, tiers, paygPresets, organizations }: {
    rows: Paginated<Row>;
    state: TableState;
    tiers: Tier[];
    paygPresets: { quantity: number; price: number }[];
    organizations: Option[];
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
            subtitle="What each organization has for Sessions. Organizations change their own plan and pay online; use these for a deal agreed with VENTIQ."
            actions={
                <>
                    <Button type="button" variant="brand" className="px-5 py-3 text-[10px]" onClick={() => setDialog('plan')}><RefreshCw className="size-3.5" />Change a plan</Button>
                    <Button type="button" variant="brand" className="bg-mint-ink px-5 py-3 text-[10px]" onClick={() => setDialog('payg')}><Plus className="size-3.5" />Add sessions</Button>
                </>
            }
        >
            <Head title="Session plans" />
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

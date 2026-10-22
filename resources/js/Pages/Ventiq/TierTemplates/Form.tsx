import { Head, Link, router, useForm } from '@inertiajs/react';
import { Copy, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';

interface Tier {
    tier_name: string;
    description: string;
    price: number | string;
    quantity_available: number | string;
    color: string;
}

interface Template {
    id: number;
    name: string;
    description: string;
    organization_id: number | null;
    is_default: boolean;
    tiers: Tier[];
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8';
const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** A set of ticket types: create, change, copy, or add to an event. */
export default function TierTemplateForm({ template: t, events, organizations, colours }: {
    template: Template | null;
    events: { value: number; label: string }[];
    organizations: { value: number; label: string }[];
    colours: Record<string, string>;
}) {
    const first = Object.keys(colours)[0];
    const form = useForm({
        name: t?.name ?? '',
        description: t?.description ?? '',
        organization_id: (t?.organization_id ?? '') as number | '',
        is_default: t?.is_default ?? false,
        tiers: t?.tiers ?? [{ tier_name: 'General', description: '', price: '', quantity_available: '', color: first }],
    });
    const apply = useForm({ event_id: (events[0]?.value ?? '') as number | '' });
    const [deleting, setDeleting] = useState(false);
    const errors = form.errors as Record<string, string>;

    const setTier = (i: number, patch: Partial<Tier>) => form.setData('tiers', form.data.tiers.map((x, j) => (j === i ? { ...x, ...patch } : x)));
    const addTier = () => {
        const used = form.data.tiers.map((x) => x.color);
        form.setData('tiers', [...form.data.tiers, { tier_name: '', description: '', price: '', quantity_available: '', color: Object.keys(colours).find((c) => !used.includes(c)) ?? first }]);
    };

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (t) form.put(route('ventiq.tier-templates.update', t.id), { preserveScroll: true });
        else form.post(route('ventiq.tier-templates.store'));
    }

    return (
        <AdminLayout title={t ? t.name : 'New tier template'} subtitle="Prices and numbers here are a starting point; the organizer changes them on the event.">
            <Head title={t ? t.name : 'New tier template'} />
            <div className="grid gap-6 lg:grid-cols-5">
                <form onSubmit={submit} className={cn(card, 'space-y-5 lg:col-span-3')}>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Name" htmlFor="name" error={errors.name}>
                            <Input id="name" required className={field} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label="For" htmlFor="organization" error={errors.organization_id}>
                            <NativeSelect id="organization" value={form.data.organization_id} onChange={(e) => form.setData('organization_id', e.target.value ? Number(e.target.value) : '')}>
                                <option value="">Any organization</option>
                                {organizations.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </NativeSelect>
                        </Field>
                    </div>
                    <Field label={<>Description <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="description" error={errors.description}>
                        <Input id="description" className={field} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </Field>
                    <label className="flex items-center gap-3 text-[13px] font-bold text-gray-700">
                        <input type="checkbox" checked={form.data.is_default} onChange={(e) => form.setData('is_default', e.target.checked)} className="size-4 accent-action" />
                        The default template {form.data.organization_id ? 'for this organization' : 'for everyone'}
                    </label>

                    <div className="space-y-3">
                        <p className="ml-1 text-[10px] font-black uppercase tracking-widest text-gray-400">Ticket types</p>
                        {errors.tiers && <p className="ml-1 text-[11px] font-bold text-rose-500">{errors.tiers}</p>}
                        {form.data.tiers.map((tier, i) => (
                            <div key={i} className="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div className="grid gap-3 sm:grid-cols-6">
                                    <Field className="sm:col-span-3" label="Name" htmlFor={`t-name-${i}`} error={errors[`tiers.${i}.tier_name`]}>
                                        <Input id={`t-name-${i}`} required className={cn(field, 'bg-white')} value={tier.tier_name} onChange={(e) => setTier(i, { tier_name: e.target.value })} />
                                    </Field>
                                    <Field className="sm:col-span-1" label="Price (M)" htmlFor={`t-price-${i}`} error={errors[`tiers.${i}.price`]}>
                                        <Input id={`t-price-${i}`} type="number" min={0} step="0.01" required className={cn(field, 'bg-white')} value={tier.price} onChange={(e) => setTier(i, { price: e.target.value })} />
                                    </Field>
                                    <Field className="sm:col-span-2" label="How many" htmlFor={`t-qty-${i}`} error={errors[`tiers.${i}.quantity_available`]}>
                                        <Input id={`t-qty-${i}`} type="number" min={1} placeholder="No limit" className={cn(field, 'bg-white')} value={tier.quantity_available} onChange={(e) => setTier(i, { quantity_available: e.target.value })} />
                                    </Field>
                                </div>
                                <Input aria-label="What's included" placeholder="What's included (optional)" className="bg-white" value={tier.description} onChange={(e) => setTier(i, { description: e.target.value })} />
                                <div className="flex flex-wrap items-center gap-2 text-[12px] font-bold text-gray-600">
                                    QR colour
                                    {Object.entries(colours).map(([hex, label]) => (
                                        <button key={hex} type="button" title={label} aria-label={label} aria-pressed={tier.color === hex} onClick={() => setTier(i, { color: hex })} className={cn('size-6 rounded-full border-2', tier.color === hex ? 'border-action ring-2 ring-action ring-offset-1' : 'border-white shadow')} style={{ background: hex }} />
                                    ))}
                                    {form.data.tiers.length > 1 && (
                                        <button type="button" onClick={() => form.setData('tiers', form.data.tiers.filter((_, j) => j !== i))} className="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600">Remove</button>
                                    )}
                                </div>
                            </div>
                        ))}
                        <button type="button" onClick={addTier} className="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-brand hover:border-brand">
                            <Plus className="size-3.5" />Add ticket type
                        </button>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" variant="brand" className="px-6 py-4 text-[11px]" disabled={form.processing}>{t ? 'Save' : 'Create template'}</Button>
                        <Link href={route('ventiq.tier-templates.index')} className="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">Back to the list</Link>
                    </div>
                </form>

                {t && (
                    <div className="space-y-6 lg:col-span-2">
                        <form className={cn(card, 'space-y-4')} onSubmit={(e) => { e.preventDefault(); apply.post(route('ventiq.tier-templates.apply', t.id), { preserveScroll: true }); }}>
                            <p className="text-[15px] font-black text-brand">Add to an event</p>
                            <p className="text-[12px] text-gray-500">Adds these ticket types alongside the event's own. Nothing it has is changed.</p>
                            {events.length === 0 ? <p className="text-[12px] text-gray-400">No upcoming events to add them to.</p> : (
                                <>
                                    <Field label="Event" htmlFor="apply-event" error={apply.errors.event_id}>
                                        <NativeSelect id="apply-event" value={apply.data.event_id} onChange={(e) => apply.setData('event_id', Number(e.target.value))}>
                                            {events.map((ev) => <option key={ev.value} value={ev.value}>{ev.label}</option>)}
                                        </NativeSelect>
                                    </Field>
                                    <Button type="submit" variant="brand" className="w-full py-3 text-[10px]" disabled={apply.processing}>Add ticket types</Button>
                                </>
                            )}
                        </form>
                        <div className={cn(card, 'flex flex-wrap gap-3')}>
                            <button type="button" onClick={() => router.post(route('ventiq.tier-templates.duplicate', t.id))} className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-brand hover:text-action"><Copy className="size-3.5" />Make a copy</button>
                            <button type="button" onClick={() => setDeleting(true)} className="ml-auto inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600"><Trash2 className="size-3.5" />Delete</button>
                        </div>
                    </div>
                )}
            </div>

            {t && (
                <ConfirmDialog open={deleting} onOpenChange={setDeleting} title={`Delete ${t.name}?`} confirmLabel="Delete" onConfirm={() => router.delete(route('ventiq.tier-templates.destroy', t.id), { onFinish: () => setDeleting(false) })}>
                    Events it was added to keep their ticket types.
                </ConfirmDialog>
            )}
        </AdminLayout>
    );
}

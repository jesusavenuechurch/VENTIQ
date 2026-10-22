import { Head, Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { PhoneInput } from '@/components/phone-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface Organization {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    keep_account: boolean;
    workshop_enabled: boolean;
    events: number;
    members: { id: number; name: string; email: string; role: string }[];
    payout: string | null;
    eventsPage: string;
    created: string | null;
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8';
const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** A new organization, or the settings only VENTIQ changes on one. */
export default function OrganizationForm({ organization: o }: { organization: Organization | null }) {
    const form = useForm({
        name: o?.name ?? '',
        slug: o?.slug ?? '',
        email: o?.email ?? '',
        phone: o?.phone ? o.phone.slice(-8) : '',
        is_active: o?.is_active ?? true,
        keep_account: o?.keep_account ?? false,
        workshop_enabled: o?.workshop_enabled ?? false,
    });
    const [deleting, setDeleting] = useState(false);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (o) form.put(route('ventiq.organizations.update', o.id), { preserveScroll: true });
        else form.post(route('ventiq.organizations.store'));
    }

    const toggles: [keyof typeof form.data, string, string][] = [
        ['is_active', 'Active', 'Off stops its events being listed and its team from working in it.'],
        ['keep_account', 'Keep even if unused', "For an organization set up ahead of time. Otherwise unused accounts are removed after two months."],
        ['workshop_enabled', 'Workshop mode', 'Workshop events, signatures and attendance registers.'],
    ];

    return (
        <AdminLayout
            title={o ? o.name : 'New organization'}
            subtitle={o ? `Joined ${o.created}. Its profile, events and team are edited from its organizer view.` : 'Set it up here, then open its organizer view to add its details and first event.'}
            actions={o && (
                <a href={route('organizer.act-as.start', o.id)} className="rounded-2xl bg-brand px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action">
                    Open organizer view
                </a>
            )}
        >
            <Head title={o ? o.name : 'New organization'} />
            <div className="grid gap-6 lg:grid-cols-5">
                <form onSubmit={submit} className={`${card} space-y-5 lg:col-span-3`}>
                    <Field label="Name" htmlFor="name" error={form.errors.name}>
                        <Input id="name" required className={field} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Web address" htmlFor="slug" error={form.errors.slug} hint={o ? "Changing it breaks links and QR codes they've shared." : 'Leave empty to make one from the name.'}>
                        <Input id="slug" className={`${field} font-mono`} value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} />
                    </Field>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Account email" htmlFor="email" error={form.errors.email}>
                            <Input id="email" type="email" required className={field} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        </Field>
                        <Field label={<>Phone <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="phone" error={form.errors.phone}>
                            <PhoneInput id="phone" value={form.data.phone} onChange={(v) => form.setData('phone', v)} invalid={!!form.errors.phone} />
                        </Field>
                    </div>
                    <div className="space-y-3">
                        {toggles.map(([key, label, hint]) => (
                            <label key={key} className="flex cursor-pointer gap-3 rounded-2xl bg-slate-50 p-4">
                                <input type="checkbox" checked={!!form.data[key]} onChange={(e) => form.setData(key, e.target.checked as never)} className="mt-0.5 size-4 accent-action" />
                                <span>
                                    <span className="block text-[13px] font-black text-brand">{label}</span>
                                    <span className="block text-[11px] text-gray-500">{hint}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    <div className="flex items-center gap-3">
                        <Button type="submit" variant="brand" className="px-6 py-4 text-[11px]" disabled={form.processing}>{o ? 'Save' : 'Create organization'}</Button>
                        <Link href={route('ventiq.organizations.index')} className="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">Back to the list</Link>
                    </div>
                </form>

                {o && (
                    <div className="space-y-6 lg:col-span-2">
                        <div className={card}>
                            <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400">At a glance</p>
                            <dl className="space-y-2 text-[13px]">
                                <div className="flex justify-between gap-3"><dt className="text-gray-500">Events</dt><dd className="font-black text-brand">{o.events}</dd></div>
                                <div className="flex justify-between gap-3"><dt className="text-gray-500">Payout account</dt><dd className="text-right font-bold text-brand">{o.payout ?? <span className="text-action-ink">None yet</span>}</dd></div>
                            </dl>
                            <a href={o.eventsPage} target="_blank" rel="noopener" className="mt-3 inline-flex items-center gap-1 text-[11px] font-bold text-brand hover:underline"><ExternalLink className="size-3" />Their events page</a>
                        </div>
                        <div className={card}>
                            <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Team</p>
                            {o.members.length === 0 ? <p className="text-[12px] text-gray-400">Nobody yet.</p> : (
                                <ul className="space-y-2">
                                    {o.members.map((m) => (
                                        <li key={m.id} className="flex items-center justify-between gap-3 text-[12px]">
                                            <Link href={route('ventiq.users.edit', m.id)} className="min-w-0 truncate font-bold text-brand hover:underline">{m.name} <span className="font-medium text-gray-400">{m.email}</span></Link>
                                            <span className="shrink-0 text-[10px] font-black uppercase tracking-widest text-gray-400">{m.role}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        {o.events === 0 && (
                            <div className="rounded-[1.5rem] border border-rose-100 bg-white p-6">
                                <p className="text-[13px] font-black uppercase tracking-widest text-rose-700">Delete</p>
                                <p className="mt-1 text-[12px] text-gray-500">No events yet, so it can be deleted. Its team keep their accounts.</p>
                                <Button type="button" variant="brand" className="mt-3 bg-rose-600 text-[10px] hover:bg-rose-700" onClick={() => setDeleting(true)}><Trash2 className="size-3.5" />Delete organization</Button>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {o && (
                <ConfirmDialog open={deleting} onOpenChange={setDeleting} title={`Delete ${o.name} for good?`} confirmLabel="Delete" onConfirm={() => router.delete(route('ventiq.organizations.destroy', o.id), { onFinish: () => setDeleting(false) })}>
                    This can't be undone.
                </ConfirmDialog>
            )}
        </AdminLayout>
    );
}

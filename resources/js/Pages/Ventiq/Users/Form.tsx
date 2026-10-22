import { Head, Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import type { UserRow } from './Index';

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8';
const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** A new account, or one to fix: name, email, password, organization and role. */
export default function UserForm({ user: u, roles, organizations, organizationId }: {
    user: UserRow | null;
    roles: { value: string; label: string }[];
    organizations: { value: number; label: string }[];
    organizationId: number | null;
}) {
    const form = useForm({
        name: u?.name ?? '',
        email: u?.email ?? '',
        password: '',
        password_confirmation: '',
        organization_id: (organizationId ?? '') as number | '',
        role: u?.role ?? (organizationId ? 'staff' : ''),
    });
    const [deleting, setDeleting] = useState(false);
    const self = !!u?.isYou;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => form.reset('password', 'password_confirmation') };
        if (u) form.put(route('ventiq.users.update', u.id), options);
        else form.post(route('ventiq.users.store'), options);
    }

    return (
        <AdminLayout title={u ? u.name : 'New user'} subtitle={u ? `Joined ${u.created}${u.lastActive ? ` · last active ${u.lastActive}` : ''}` : 'An account that can sign in straight away with this email and password.'}>
            <Head title={u ? u.name : 'New user'} />
            <form onSubmit={submit} className={`${card} max-w-2xl space-y-5`}>
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label="Name" htmlFor="name" error={form.errors.name}>
                        <Input id="name" required className={field} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Email" htmlFor="email" error={form.errors.email}>
                        <Input id="email" type="email" required className={field} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    </Field>
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label="Organization" htmlFor="organization" error={form.errors.organization_id} hint={self ? "You can't change your own." : undefined}>
                        <NativeSelect id="organization" disabled={self} value={form.data.organization_id} onChange={(e) => form.setData('organization_id', e.target.value ? Number(e.target.value) : '')}>
                            <option value="">None (VENTIQ, agents)</option>
                            {organizations.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </NativeSelect>
                    </Field>
                    <Field label="Role" htmlFor="role" error={form.errors.role} hint={self ? "You can't change your own." : undefined}>
                        <NativeSelect id="role" disabled={self} value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                            <option value="">No role</option>
                            {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                        </NativeSelect>
                    </Field>
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={u ? <>New password <span className="normal-case text-gray-300">(leave empty to keep)</span></> : 'Password'} htmlFor="password" error={form.errors.password}>
                        <Input id="password" type="password" autoComplete="new-password" required={!u} className={field} value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                    </Field>
                    <Field label="Password again" htmlFor="password_confirmation">
                        <Input id="password_confirmation" type="password" autoComplete="new-password" required={!u || !!form.data.password} className={field} value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                    </Field>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" variant="brand" className="px-6 py-4 text-[11px]" disabled={form.processing}>{u ? 'Save' : 'Create account'}</Button>
                    <Link href={route('ventiq.users.index')} className="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">Back to the list</Link>
                    {u && !self && (
                        <button type="button" onClick={() => setDeleting(true)} className="ml-auto inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600">
                            <Trash2 className="size-3.5" />Delete account
                        </button>
                    )}
                </div>
            </form>

            {u && (
                <ConfirmDialog open={deleting} onOpenChange={setDeleting} title={`Delete ${u.name}'s account?`} confirmLabel="Delete" onConfirm={() => router.delete(route('ventiq.users.destroy', u.id), { onFinish: () => setDeleting(false) })}>
                    They can't sign in any more. Tickets and payments they recorded stay.
                </ConfirmDialog>
            )}
        </AdminLayout>
    );
}

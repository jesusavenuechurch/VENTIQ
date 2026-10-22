import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Plus } from 'lucide-react';
import { useState } from 'react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { Field } from '@/components/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';

interface Account {
    id: number;
    label: string;
    method: string;
    name: string | null;
    number: string | null;
    instructions: string | null;
    isActive: boolean;
    isDefault: boolean;
    hasPayments: boolean;
}

const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/**
 * The organization's own accounts attendees can pay into: several per
 * method, one default each for new events.
 */
export default function Accounts({ accounts, methods, can }: { accounts: Account[]; methods: { value: string; label: string }[]; can: { create: boolean; edit: boolean; delete: boolean } }) {
    const [adding, setAdding] = useState(accounts.length === 0);
    const form = useForm({ payment_method: methods[0]?.value ?? 'ecocash', account_name: '', account_number: '', instructions: '' });

    function add(e: React.FormEvent) {
        e.preventDefault();
        form.post(route('organizer.accounts.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <OrganizerLayout
            title="Payment accounts"
            subtitle="Where attendees can pay you directly, on events VENTIQ sponsors. Everywhere else attendees pay VENTIQ, and VENTIQ pays you out."
        >
            <Head title="Payment accounts" />
            <div className="max-w-3xl">
                {can.create && (
                    <div className="mb-6 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm">
                        <button type="button" onClick={() => setAdding((a) => !a)} className="text-[11px] font-black uppercase tracking-widest text-brand">
                            <Plus className="mr-1 inline size-3.5" />Add an account
                        </button>
                        {adding && (
                            <form onSubmit={add} className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Method" htmlFor="payment_method" error={form.errors.payment_method}>
                                    <NativeSelect id="payment_method" required value={form.data.payment_method} onChange={(e) => form.setData('payment_method', e.target.value)}>
                                        {methods.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                                    </NativeSelect>
                                </Field>
                                <Field label="Name for this account" htmlFor="account_name" error={form.errors.account_name}>
                                    <Input id="account_name" maxLength={255} placeholder="e.g. Events Account" className={field} value={form.data.account_name} onChange={(e) => form.setData('account_name', e.target.value)} />
                                </Field>
                                <Field className="sm:col-span-2" label={<>Number attendees pay to <span className="normal-case text-gray-300">(not needed for cash)</span></>} htmlFor="account_number" error={form.errors.account_number}>
                                    <Input id="account_number" maxLength={255} placeholder="e.g. 6255 2155 or bank account number" className={field} value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} />
                                </Field>
                                <Field className="sm:col-span-2" label={<>Instructions <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="instructions" error={form.errors.instructions}>
                                    <Input id="instructions" maxLength={1000} placeholder="e.g. Use your ticket number as the reference" className={field} value={form.data.instructions} onChange={(e) => form.setData('instructions', e.target.value)} />
                                </Field>
                                <div className="flex justify-end sm:col-span-2">
                                    <Button type="submit" variant="brand" size="sm" className="rounded-2xl px-6 py-3" disabled={form.processing}>Add account</Button>
                                </div>
                            </form>
                        )}
                    </div>
                )}

                <div className="space-y-3">
                    {accounts.length === 0 ? (
                        <div className="rounded-[1.5rem] border border-dashed border-gray-200 bg-white p-10 text-center text-[13px] font-bold text-gray-500">
                            No accounts yet. Add one so attendees can pay you directly, or use online payment through VENTIQ.
                        </div>
                    ) : (
                        accounts.map((a) => <AccountCard key={a.id} account={a} can={can} />)
                    )}
                </div>
            </div>
        </OrganizerLayout>
    );
}

function AccountCard({ account, can }: { account: Account; can: { edit: boolean; delete: boolean } }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ account_name: account.name ?? '', account_number: account.number ?? '', instructions: account.instructions ?? '' });
    const pill = 'rounded-full border border-slate-100 bg-slate-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest';
    const post = (name: string) => router.post(route(name, account.id), {}, { preserveScroll: true });

    function save(e: React.FormEvent) {
        e.preventDefault();
        form.put(route('organizer.accounts.update', account.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
    }

    return (
        <div className={cn('rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm', !account.isActive && 'opacity-60')}>
            {!editing ? (
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-[15px] font-black text-brand">
                            {account.label}
                            {account.isDefault && <Badge variant="brand" className="ml-2 align-middle">Default</Badge>}
                            {!account.isActive && <Badge className="ml-2 align-middle">Off</Badge>}
                        </p>
                        {account.number && <p className="mt-1 font-mono text-[13px] text-gray-600">{account.number}</p>}
                        {account.instructions && <p className="mt-1 text-[12px] text-gray-500">{account.instructions}</p>}
                        {account.hasPayments && <p className="mt-2 text-[11px] text-gray-400"><Lock className="mr-1 inline size-3" />Has received payments, so its number is locked.</p>}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.edit && (
                            <>
                                {account.isActive && !account.isDefault && <button type="button" onClick={() => post('organizer.accounts.default')} className={cn(pill, 'text-brand')}>Make default</button>}
                                <button type="button" onClick={() => setEditing(true)} className={cn(pill, 'text-gray-500')}>Edit</button>
                                <button type="button" onClick={() => post('organizer.accounts.toggle')} className={cn(pill, 'text-gray-500')}>{account.isActive ? 'Switch off' : 'Switch on'}</button>
                            </>
                        )}
                        {can.delete && !account.hasPayments && (
                            <button
                                type="button"
                                onClick={() => confirm('Remove this account?') && router.delete(route('organizer.accounts.destroy', account.id), { preserveScroll: true })}
                                className="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600"
                            >
                                Remove
                            </button>
                        )}
                    </div>
                </div>
            ) : (
                <form onSubmit={save} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Name for this account" htmlFor={`name_${account.id}`} error={form.errors.account_name}>
                        <Input id={`name_${account.id}`} maxLength={255} className={field} value={form.data.account_name} onChange={(e) => form.setData('account_name', e.target.value)} />
                    </Field>
                    <Field label="Number" htmlFor={`number_${account.id}`} error={form.errors.account_number}>
                        <Input id={`number_${account.id}`} maxLength={255} className={field} disabled={account.hasPayments} value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} />
                    </Field>
                    <Field className="sm:col-span-2" label="Instructions" htmlFor={`instructions_${account.id}`} error={form.errors.instructions}>
                        <Input id={`instructions_${account.id}`} maxLength={1000} className={field} value={form.data.instructions} onChange={(e) => form.setData('instructions', e.target.value)} />
                    </Field>
                    <div className="flex justify-end gap-2 sm:col-span-2">
                        <Button type="button" variant="ghost" size="sm" className="rounded-2xl px-5 py-3" onClick={() => setEditing(false)}>Cancel</Button>
                        <Button type="submit" variant="brand" size="sm" className="rounded-2xl px-5 py-3" disabled={form.processing}>Save</Button>
                    </div>
                </form>
            )}
        </div>
    );
}

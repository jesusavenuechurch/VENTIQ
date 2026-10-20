import { Head, useForm } from '@inertiajs/react';
import { CircleAlert, ShieldCheck } from 'lucide-react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { AppLink } from '@/components/app-link';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface Details {
    payout_method: string;
    payout_account_name: string;
    payout_account_number: string;
    payout_bank_name: string;
}

const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** Where VENTIQ pays the organization. Admins only; every change emails all admins. */
export default function Payout({
    canEdit,
    methods,
    onFile,
    details,
}: {
    canEdit: boolean;
    methods: { value: string; label: string }[];
    onFile: { summary: string; changedAt: string | null; changedBy: string | null } | null;
    details: Details | null;
}) {
    const form = useForm<Details>(details ?? { payout_method: 'ecocash', payout_account_name: '', payout_account_number: '', payout_bank_name: '' });
    const bank = form.data.payout_method === 'bank_transfer';

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.put(route('organizer.payout.update'), { preserveScroll: true });
    }

    return (
        <SettingsLayout subtitle="For Events: where VENTIQ pays you the money from tickets paid to VENTIQ, after our fees.">
            <Head title="Payouts" />
            <div className="max-w-3xl">
                <div className={cn('mb-6 rounded-[1.5rem] border p-5', onFile ? 'border-mint bg-mint/40' : 'border-action-soft bg-action-soft')}>
                    {onFile ? (
                        <>
                            <p className="text-[11px] font-black uppercase tracking-widest text-mint-ink">We pay you to</p>
                            <p className="mt-1 text-[15px] font-black text-brand">{onFile.summary}</p>
                            <p className="mt-1 text-[11px] text-gray-500">Last changed {onFile.changedAt}{onFile.changedBy && ` by ${onFile.changedBy}`}.</p>
                        </>
                    ) : (
                        <>
                            <p className="text-[13px] font-black text-action-ink"><CircleAlert className="mr-1 inline size-4" />No payout account yet</p>
                            <p className="mt-1 text-[12px] text-action-ink/80">Add one so VENTIQ can pay you for tickets paid to VENTIQ. Payments made directly to you don't need it.</p>
                        </>
                    )}
                </div>

                {canEdit ? (
                    <form onSubmit={submit} className="space-y-5 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
                        <div>
                            <span className="mb-2 ml-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Pay me by</span>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                {methods.map((m) => (
                                    <button
                                        key={m.value}
                                        type="button"
                                        onClick={() => form.setData('payout_method', m.value)}
                                        className={cn('rounded-2xl border-2 p-4 text-center text-[13px] font-black text-gray-700', form.data.payout_method === m.value ? 'border-action bg-white' : 'border-slate-100 bg-slate-50')}
                                    >
                                        {m.label}
                                    </button>
                                ))}
                            </div>
                            {form.errors.payout_method && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-500">{form.errors.payout_method}</p>}
                        </div>

                        {bank && (
                            <Field label="Bank" htmlFor="payout_bank_name" error={form.errors.payout_bank_name}>
                                <Input id="payout_bank_name" maxLength={255} placeholder="e.g. Standard Lesotho Bank, FNB, Nedbank" className={field} value={form.data.payout_bank_name} onChange={(e) => form.setData('payout_bank_name', e.target.value)} />
                            </Field>
                        )}

                        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <Field label="Account name" htmlFor="payout_account_name" error={form.errors.payout_account_name}>
                                <Input id="payout_account_name" required maxLength={255} placeholder="Name the account is registered to" className={field} value={form.data.payout_account_name} onChange={(e) => form.setData('payout_account_name', e.target.value)} />
                            </Field>
                            <Field label={bank ? 'Account number' : 'Mobile number'} htmlFor="payout_account_number" error={form.errors.payout_account_number}>
                                <Input id="payout_account_number" required maxLength={60} inputMode="numeric" className={cn(field, 'font-mono')} value={form.data.payout_account_number} onChange={(e) => form.setData('payout_account_number', e.target.value)} />
                            </Field>
                        </div>

                        <p className="text-[12px] text-gray-500">
                            <ShieldCheck className="mr-1 inline size-4 text-brand" />For your safety, every change is emailed to all admins on your team, and VENTIQ double-checks a new account with you before the first payout into it.
                        </p>

                        <Button type="submit" variant="brand" className="w-full py-4 text-[11px] sm:w-auto sm:px-8" disabled={form.processing}>Save payout account</Button>
                    </form>
                ) : (
                    <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-[12px] font-medium text-gray-500">Only an admin on your team can change where VENTIQ pays you.</div>
                )}

                <div className="mt-6 space-y-1 rounded-[1.5rem] border border-gray-100 bg-white p-5 text-[12px] text-gray-500">
                    <p className="font-black text-gray-600">How payouts work</p>
                    <p>Tickets paid to VENTIQ, online or with EcoCash to our merchant code, come to us. We take our fee off each one and pay you the rest into this account every week. Each payout and every ticket in it shows in your events' money summaries.</p>
                    <p>
                        The accounts attendees pay you into directly are separate: they're under{' '}
                        <AppLink to="organizer.accounts.index" className="font-bold text-brand underline">Accounts</AppLink>.
                    </p>
                </div>
            </div>
        </SettingsLayout>
    );
}

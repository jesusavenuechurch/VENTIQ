import { useForm } from '@inertiajs/react';
import { Banknote, Info, Smartphone } from 'lucide-react';
import { Field } from '@/components/field';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn, money } from '@/lib/utils';
import type { Account } from './types';

/**
 * Paying into the organizer's own account and sending the reference or a
 * screenshot, for the organizer to confirm. Offered only on events VENTIQ
 * sponsors.
 */
export function DirectPay({
    code,
    accounts,
    owed,
    installments,
}: {
    code: string;
    accounts: Account[];
    owed: number;
    installments: { percent: number; minDeposit: number; full: number } | null;
}) {
    const form = useForm<{ payment_type: 'full' | 'deposit'; deposit_amount: string; payment_method_id: number | null; payment_reference: string; proof: File | null }>({
        payment_type: 'full',
        deposit_amount: installments ? installments.minDeposit.toFixed(2) : '',
        payment_method_id: accounts[0]?.id ?? null,
        payment_reference: '',
        proof: null,
    });
    const chosen = accounts.find((a) => a.id === form.data.payment_method_id);
    const instructions = chosen?.instructions?.trim()
        || (chosen?.isCash ? 'Pay in person at the venue or designated location. Your ticket will be activated upon payment confirmation.' : null);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(route('ticket.pay.manual', code), { forceFormData: true, preserveScroll: true });
    }

    if (accounts.length === 0) {
        return (
            <div className="rounded-3xl border-2 border-amber-100 bg-amber-50 p-6 text-center">
                <h4 className="text-[10px] font-black uppercase leading-none tracking-widest text-amber-900">No Other Payment Methods Configured</h4>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-6">
            {installments && (
                <div className="space-y-4">
                    <Label>Payment Plan</Label>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <PlanOption checked={form.data.payment_type === 'full'} onChoose={() => form.setData('payment_type', 'full')} title="Full Amount" amount={money(owed)} tone="brand" />
                        <PlanOption
                            checked={form.data.payment_type === 'deposit'}
                            onChoose={() => form.setData('payment_type', 'deposit')}
                            title="Installments"
                            amount={`${money(installments.minDeposit)}+`}
                            note={`${installments.percent.toFixed(0)}% Min Deposit`}
                            tone="emerald"
                        />
                    </div>
                    {form.data.payment_type === 'deposit' && (
                        <div className="rounded-[2rem] border border-emerald-100 bg-emerald-50 p-8">
                            <Label htmlFor="deposit_amount" className="mb-4 text-center text-emerald-800">Initial Payment Amount</Label>
                            <div className="relative mx-auto max-w-xs">
                                <span className="absolute left-6 top-1/2 -translate-y-1/2 font-black text-emerald-400">M</span>
                                <input
                                    id="deposit_amount"
                                    type="number"
                                    step="0.01"
                                    required
                                    min={installments.minDeposit}
                                    max={installments.full}
                                    value={form.data.deposit_amount}
                                    onChange={(e) => form.setData('deposit_amount', e.target.value)}
                                    className="w-full rounded-2xl border-2 border-emerald-200 bg-white py-5 pl-12 pr-6 text-2xl font-black text-emerald-900 shadow-inner outline-none focus:border-emerald-500"
                                />
                            </div>
                            <div className="mt-6 flex flex-wrap justify-center gap-2">
                                {[['Min', installments.minDeposit], ['Half', installments.full / 2], ['Full', installments.full]].map(([label, value]) => (
                                    <button key={label} type="button" onClick={() => form.setData('deposit_amount', Number(value).toFixed(2))} className="rounded-full border border-emerald-200 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-emerald-700 transition-all hover:bg-emerald-600 hover:text-white">
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            <div className="space-y-4">
                <Label>Payment Method</Label>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {accounts.map((account) => (
                        <button
                            key={account.id}
                            type="button"
                            onClick={() => form.setData('payment_method_id', account.id)}
                            className={cn('flex h-full flex-col rounded-2xl border-2 p-4 text-left transition-all', form.data.payment_method_id === account.id ? 'border-action bg-white shadow-lg' : 'border-slate-50 bg-slate-50')}
                        >
                            <div className="mb-3 flex items-center">
                                <div className="mr-3 flex size-10 items-center justify-center rounded-xl bg-white shadow-sm">
                                    {account.isCash ? <Banknote className="size-5 text-green-600" /> : <Smartphone className="size-5 text-blue-600" />}
                                </div>
                                <span className="truncate text-xs font-black uppercase tracking-tight text-gray-900">{account.label}</span>
                            </div>
                            {account.number ? (
                                <div className="mt-auto rounded-lg border border-gray-100 bg-gray-50 p-2">
                                    <p className="mb-1 text-[9px] font-black uppercase tracking-tighter text-gray-400">{account.accountLabel}</p>
                                    <p className="break-all font-mono text-[11px] font-bold leading-none text-gray-900">{account.number}</p>
                                </div>
                            ) : (
                                <p className="mt-auto py-2 text-center text-[10px] font-bold uppercase italic tracking-wider text-gray-400">Pay in person</p>
                            )}
                        </button>
                    ))}
                </div>
                {form.errors.payment_method_id && <p className="ml-1 text-[11px] font-bold text-rose-500">{form.errors.payment_method_id}</p>}
            </div>

            {instructions && (
                <div className="flex items-start rounded-2xl border border-brand bg-brand p-5">
                    <Info className="mr-4 mt-0.5 size-5 shrink-0 text-white" />
                    <div className="flex-1">
                        <p className="mb-1 text-[10px] font-black uppercase tracking-[0.2em] text-blue-200">Payment Instructions</p>
                        <p className="text-sm font-bold leading-relaxed text-white">{instructions}</p>
                    </div>
                </div>
            )}

            <Field label={<>Reference <span className="lowercase text-gray-300">(not needed for cash)</span></>} htmlFor="payment_reference" error={form.errors.payment_reference}>
                <Input id="payment_reference" placeholder="Enter transaction reference" value={form.data.payment_reference} onChange={(e) => form.setData('payment_reference', e.target.value)} />
            </Field>
            <Field label={<>Screenshot of the payment <span className="lowercase text-gray-300">(optional, or instead of the reference)</span></>} htmlFor="manual_proof" error={form.errors.proof}>
                <input id="manual_proof" type="file" accept="image/*,application/pdf" onChange={(e) => form.setData('proof', e.target.files?.[0] ?? null)} className="w-full text-[12px] text-gray-500 file:mr-3 file:rounded-full file:border-0 file:bg-action file:px-4 file:py-2 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:text-white" />
            </Field>

            <Button type="submit" variant="dark" className="w-full text-[11px] tracking-[0.3em]" disabled={form.processing}>Confirm Payment Details</Button>
        </form>
    );
}

function PlanOption({ checked, onChoose, title, amount, note, tone }: { checked: boolean; onChoose: () => void; title: string; amount: string; note?: string; tone: 'brand' | 'emerald' }) {
    return (
        <button
            type="button"
            onClick={onChoose}
            className={cn(
                'h-full rounded-[2rem] border-2 p-6 text-left transition-all',
                checked ? cn('bg-white shadow-xl', tone === 'brand' ? 'border-brand' : 'border-emerald-600') : 'border-slate-50 bg-slate-50',
            )}
        >
            <h4 className="text-lg font-black uppercase tracking-tight text-gray-900">{title}</h4>
            <p className={cn('mt-1 text-2xl font-black', tone === 'brand' ? 'text-action' : 'text-emerald-600')}>{amount}</p>
            {note && <p className="mt-2 text-[10px] font-bold uppercase text-gray-500">{note}</p>}
        </button>
    );
}

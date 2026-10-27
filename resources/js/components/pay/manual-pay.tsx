import { useForm } from '@inertiajs/react';
import { Field } from '@/components/field';
import { PhoneInput } from '@/components/phone-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { money } from '@/lib/utils';
import type { ManualOption } from './types';

/**
 * Paying VENTIQ by hand where there's no PIN prompt: M-Pesa to VENTIQ's
 * number, or a bank transfer. They send it with the reference, then tell
 * us the transaction ID (or a screenshot); VENTIQ confirms it and the
 * ticket goes out.
 */
export function ManualPay({ code, option, paidFrom, owed }: { code: string; option: ManualOption; paidFrom: string | null; owed: number }) {
    const bank = option.method === 'bank';
    const form = useForm<{ method: string; merchant_phone: string; merchant_reference: string; proof: File | null }>({
        method: option.method,
        merchant_phone: bank ? '' : paidFrom?.startsWith('5') ? paidFrom : '',
        merchant_reference: '',
        proof: null,
    });
    const id = `manual-${option.method}`;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(route('ticket.pay.merchant', code), { forceFormData: true, preserveScroll: true });
    }

    return (
        <div className="space-y-5">
            <div className="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-5 text-[13px] font-bold text-gray-700">
                <p>{bank ? 'Transfer from your bank to VENTIQ:' : 'From your M-Pesa menu, send money to VENTIQ:'}</p>
                <dl className="space-y-1.5">
                    <div className="flex justify-between gap-4"><dt className="text-gray-500">Amount</dt><dd className="font-mono text-brand">{money(owed)}</dd></div>
                    {option.lines.map((l) => (
                        <div key={l.label} className="flex justify-between gap-4">
                            <dt className="text-gray-500">{l.label}</dt>
                            <dd className={l.label === 'Reference' ? 'font-mono text-[16px] tracking-widest text-action' : 'text-right font-mono text-brand'}>{l.value}</dd>
                        </div>
                    ))}
                </dl>
                <p className="font-medium text-gray-500">
                    {bank
                        ? 'Use the reference so we can match it. We confirm transfers within 24 hours of the money reaching us, and your place is held meanwhile.'
                        : 'Put the reference in the message. We confirm it as soon as we see it, and your place is held meanwhile.'}
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <p className="text-[12px] font-bold text-gray-500">Once you've sent it, tell us here.</p>
                {!bank && (
                    <Field label="Number you paid from" htmlFor={`${id}_phone`} error={form.errors.merchant_phone}>
                        <PhoneInput id={`${id}_phone`} required value={form.data.merchant_phone} onChange={(v) => form.setData('merchant_phone', v)} invalid={!!form.errors.merchant_phone} />
                    </Field>
                )}
                <Field label={bank ? 'Reference from your bank' : 'Transaction ID from the M-Pesa message'} htmlFor={`${id}_reference`} error={form.errors.merchant_reference}>
                    <Input id={`${id}_reference`} placeholder={bank ? 'e.g. FNB 99812' : 'e.g. QK71H2XY9'} value={form.data.merchant_reference} onChange={(e) => form.setData('merchant_reference', e.target.value)} />
                </Field>
                <Field label="Or a screenshot of it" htmlFor={`${id}_proof`} error={form.errors.proof}>
                    <input id={`${id}_proof`} type="file" accept="image/*,application/pdf" onChange={(e) => form.setData('proof', e.target.files?.[0] ?? null)} className="w-full text-[12px] text-gray-500 file:mr-3 file:rounded-full file:border-0 file:bg-action file:px-4 file:py-2 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:text-white" />
                </Field>
                <Button type="submit" className="w-full" disabled={form.processing}>I've paid</Button>
            </form>
        </div>
    );
}

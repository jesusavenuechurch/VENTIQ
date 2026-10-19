import { useForm } from '@inertiajs/react';
import { Field } from '@/components/field';
import { PhoneInput } from '@/components/phone-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { money } from '@/lib/utils';
import type { Merchant } from './types';

/**
 * Paying VENTIQ's EcoCash merchant by hand. The payment's SMS reaches
 * VENTIQ and is matched on the reference (or the paying number), so the
 * ticket usually arrives on its own; the form is for when it doesn't.
 */
export function MerchantPay({ code, merchant, reference, paidFrom, owed, idPrefix }: { code: string; merchant: Merchant; reference: string; paidFrom: string | null; owed: number; idPrefix: string }) {
    const form = useForm<{ merchant_phone: string; merchant_reference: string; proof: File | null }>({
        merchant_phone: paidFrom ?? '',
        merchant_reference: '',
        proof: null,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(route('ticket.pay.merchant', code), { forceFormData: true, preserveScroll: true });
    }

    return (
        <>
            <div className="mb-5 space-y-2 rounded-2xl border border-slate-100 bg-slate-50 p-5 text-[13px] font-bold text-gray-700">
                <p>On your phone, pay with EcoCash:</p>
                <ol className="list-inside list-decimal space-y-1 text-gray-600">
                    <li>Open the EcoCash menu and choose <strong>Pay merchant</strong></li>
                    <li>Merchant code <span className="font-mono text-[15px] text-brand">{merchant.code}</span> ({merchant.name})</li>
                    <li>Amount <span className="font-mono text-brand">{money(owed)}</span></li>
                    {reference && <li>For the reference, type <span className="font-mono text-[17px] tracking-widest text-action">{reference}</span></li>}
                </ol>
                <p className="font-medium text-gray-500">Your ticket is sent automatically once your payment reaches us, usually within minutes.</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <p className="text-[12px] font-bold text-gray-500">Paid from another phone, or no ticket after 15 minutes? Tell us and we'll find it.</p>
                <Field label="Number you paid from" htmlFor={`${idPrefix}_phone`} error={form.errors.merchant_phone}>
                    <PhoneInput id={`${idPrefix}_phone`} required value={form.data.merchant_phone} onChange={(v) => form.setData('merchant_phone', v)} invalid={!!form.errors.merchant_phone} />
                </Field>
                <Field label="Approval code from the EcoCash message (if you have it)" htmlFor={`${idPrefix}_reference`} error={form.errors.merchant_reference}>
                    <Input id={`${idPrefix}_reference`} placeholder="e.g. MP261008.1608.A38818" value={form.data.merchant_reference} onChange={(e) => form.setData('merchant_reference', e.target.value)} />
                </Field>
                <Field label="Or a screenshot of it" htmlFor={`${idPrefix}_proof`} error={form.errors.proof}>
                    <input id={`${idPrefix}_proof`} type="file" accept="image/*,application/pdf" onChange={(e) => form.setData('proof', e.target.files?.[0] ?? null)} className="w-full text-[12px] text-gray-500 file:mr-3 file:rounded-full file:border-0 file:bg-action file:px-4 file:py-2 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:text-white" />
                </Field>
                <Button type="submit" className="w-full" disabled={form.processing}>I've paid</Button>
            </form>
        </>
    );
}

import { useForm } from '@inertiajs/react';
import { Check, ImageIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { avatarUrl } from '@/lib/avatar';
import { money } from '@/lib/utils';

export interface PaymentCardData {
    id: number;
    event: string;
    holderName: string | null;
    avatarSeed: string | null;
    tier: string | null;
    admissions: number;
    number: string;
    amount: number;
    owed: number;
    partial: boolean;
    paidTo: string;
    paidToNumber: string | null;
    accountLabel: string;
    reference: string | null;
    proofUrl: string | null;
    submitted: string | null;
    action: string;
}

/** One submitted payment and the organizer's three answers. */
export function PaymentCard({ payment, canDecide }: { payment: PaymentCardData; canDecide: boolean }) {
    const [rejecting, setRejecting] = useState(false);
    const form = useForm({ decision: '', reason: '' });

    function decide(decision: 'activate' | 'deposit' | 'reject') {
        form.transform((data) => ({ ...data, decision }));
        form.post(payment.action, { preserveScroll: true });
    }

    const term = 'text-[9px] font-black uppercase tracking-widest text-gray-400';

    return (
        <div className="rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <img src={avatarUrl(payment.avatarSeed)} alt="" className="size-12 shrink-0 rounded-full bg-slate-100" />
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">{payment.event}</p>
                        <p className="mt-1 text-lg font-black leading-tight text-brand">{payment.holderName}</p>
                        <p className="mt-1 text-[12px] font-medium text-gray-500">
                            {payment.tier}
                            {payment.admissions > 1 && ` · Group of ${payment.admissions}`} · {payment.number}
                        </p>
                    </div>
                </div>
                <div className="text-right">
                    <p className="text-2xl font-black text-brand">{money(payment.amount)}</p>
                    {payment.partial && <p className="text-[11px] font-bold text-action-ink">Deposit · {money(payment.owed)} owed in total</p>}
                </div>
            </div>

            <dl className="mt-5 grid grid-cols-1 gap-4 text-[12px] sm:grid-cols-3">
                <div>
                    <dt className={term}>Paid to</dt>
                    <dd className="mt-1 font-bold text-gray-800">
                        {payment.paidTo}
                        {payment.paidToNumber && <span className="block font-mono text-gray-500">{payment.paidToNumber}</span>}
                    </dd>
                </div>
                <div>
                    <dt className={term}>Reference</dt>
                    <dd className="mt-1 break-all font-mono font-bold text-gray-800">{payment.reference || '—'}</dd>
                    {payment.proofUrl && (
                        <dd className="mt-1">
                            <a href={payment.proofUrl} target="_blank" rel="noopener" className="text-[11px] font-black text-action-ink hover:underline"><ImageIcon className="mr-1 inline size-3.5" />View screenshot</a>
                        </dd>
                    )}
                </div>
                <div>
                    <dt className={term}>Submitted</dt>
                    <dd className="mt-1 font-bold text-gray-800">{payment.submitted}</dd>
                </div>
            </dl>

            {canDecide && (
                <>
                    <p className="mt-6 text-[12px] font-black text-brand">Has this payment been received?</p>
                    {!rejecting ? (
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Button type="button" size="sm" className="rounded-2xl bg-mint-ink px-5 py-3 hover:bg-brand" disabled={form.processing} onClick={() => decide('activate')}>
                                <Check className="size-3.5" />{payment.partial ? 'Yes — activate with balance due' : 'Yes — activate ticket'}
                            </Button>
                            {payment.partial && (
                                <Button type="button" size="sm" className="rounded-2xl border-2 border-mint-ink bg-mint px-5 py-3 text-mint-ink hover:bg-mint" disabled={form.processing} onClick={() => decide('deposit')}>
                                    Yes — record deposit, keep inactive
                                </Button>
                            )}
                            <Button type="button" size="sm" variant="destructive" className="rounded-2xl px-5 py-3" onClick={() => setRejecting(true)}>No — not received</Button>
                        </div>
                    ) : (
                        <div className="mt-3 space-y-3">
                            <textarea
                                rows={2}
                                maxLength={500}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                placeholder="Optional note to the attendee, e.g. 'No payment with this reference reached our account.'"
                                className="w-full rounded-2xl border-2 border-slate-50 bg-slate-50 px-4 py-3 text-[13px] font-medium outline-none focus:border-rose-300 focus:bg-white"
                            />
                            <div className="flex gap-2">
                                <Button type="button" size="sm" className="rounded-2xl bg-rose-600 px-5 py-3 hover:bg-rose-700" disabled={form.processing} onClick={() => decide('reject')}>Reject payment</Button>
                                <Button type="button" size="sm" variant="ghost" className="rounded-2xl px-5 py-3" onClick={() => setRejecting(false)}>Back</Button>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

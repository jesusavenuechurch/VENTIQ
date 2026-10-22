import { router, useForm } from '@inertiajs/react';
import { Building2, Check, CircleX, Clock, Copy, LoaderCircle, Mail, Smartphone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SessionsLayout, { primaryButton, quietButton } from '@/Layouts/SessionsLayout';
import { PhoneInput, formatLocal } from '@/components/phone-input';
import { json } from '@/lib/http';
import { cn, money } from '@/lib/utils';

interface Props {
    purchase: { type: 'plan' | 'payg'; tier: string | null; quantity: number | null; amount: number; label: string };
    methods: string[];
    /** VENTIQ's bank account, with this purchase's reference; claimed once they've said they paid. */
    bank: { lines: { label: string; value: string }[]; reference: string; claimed: boolean } | null;
    support: string;
}

const NAMES: Record<string, string> = { mpesa: 'M-Pesa', ecocash: 'EcoCash' };
const WAIT_MS = 3 * 60 * 1000;

/** Paying for a Sessions plan or more sessions: a prompt on the phone, then it's added. */
export default function PlanPayment({ purchase, methods, bank, support }: Props) {
    // Mobile money first while it's on; otherwise straight to the bank transfer.
    const [way, setWay] = useState<'mobile' | 'bank'>(methods.length ? 'mobile' : 'bank');
    const [method, setMethod] = useState(methods[0]);
    const [phone, setPhone] = useState('');
    const [panel, setPanel] = useState<'form' | 'waiting' | 'timeout' | 'failed'>('form');
    const [error, setError] = useState('');
    const [sending, setSending] = useState(false);
    const timer = useRef<number>(0);
    useEffect(() => () => window.clearInterval(timer.current), []);

    async function send() {
        if (phone.length !== 8) return setError('Enter an 8-digit mobile number.');
        setError('');
        setSending(true);
        const res = await json<{ session_id?: number; status?: string }>(route('paylesotho.session-package.initiate'), {
            method: 'POST',
            body: { type: purchase.type, tier: purchase.tier, quantity: purchase.quantity, method, mobile_number: `+266${phone}` },
        }).catch(() => null);
        setSending(false);
        if (!res?.ok || !res.data.session_id) return setError("The payment couldn't start. Try again.");
        if (res.data.status === 'failed') return setPanel('failed');
        setPanel('waiting');

        const started = Date.now();
        const id = res.data.session_id;
        timer.current = window.setInterval(async () => {
            const s = await json<{ status: string }>(route('paylesotho.status', id), { method: 'GET' }).catch(() => null);
            if (s?.data.status === 'completed') {
                window.clearInterval(timer.current);
                router.visit(route('sessions.plan'));
            } else if (s?.data.status === 'failed') {
                window.clearInterval(timer.current);
                setPanel('failed');
            } else if (Date.now() - started > WAIT_MS) {
                window.clearInterval(timer.current);
                setPanel('timeout');
            }
        }, 2500);
    }

    return (
        <SessionsLayout title="Payment" crumbs={[['Plan', route('sessions.plan')], ['Payment']]} width="max-w-lg">
            <div className="mb-6 rounded-[2rem] bg-brand p-8 text-center text-white">
                <p className="text-[10px] font-black uppercase tracking-[0.25em] text-white/50">To pay</p>
                <p className="mt-2 text-4xl font-black">{money(purchase.amount)}</p>
                <p className="mt-2 text-[13px] text-white/70">{purchase.label}</p>
            </div>

            {methods.length > 0 && bank && panel === 'form' && (
                <div role="tablist" aria-label="How to pay" className="mb-4 grid grid-cols-2 gap-2">
                    {([['mobile', 'Mobile money', Smartphone], ['bank', 'Bank transfer', Building2]] as const).map(([key, label, Icon]) => (
                        <button key={key} type="button" role="tab" aria-selected={way === key} onClick={() => setWay(key)} className={cn('flex items-center justify-center gap-2 rounded-2xl border-2 py-3 text-[11px] font-black uppercase tracking-widest', way === key ? 'border-brand bg-brand text-white' : 'border-gray-100 bg-white text-gray-400')}>
                            <Icon className="size-4" />{label}
                        </button>
                    ))}
                </div>
            )}

            {way === 'bank' && bank ? (
                <BankTransfer purchase={purchase} bank={bank} />
            ) : methods.length === 0 ? (
                <div className="rounded-2xl border border-gray-100 bg-white p-6 text-center">
                    <p className="text-sm font-black text-brand">Online payment isn't available right now</p>
                    <p className="mt-1 text-[12px] text-gray-500">Email us and we'll set it up for you.</p>
                    <a href={`mailto:${support}?subject=${encodeURIComponent(`Sessions: ${purchase.label}`)}`} className={cn(primaryButton, 'mt-5')}><Mail className="size-4" />Email {support}</a>
                </div>
            ) : panel === 'form' ? (
                <div className="space-y-5 rounded-2xl border border-gray-100 bg-white p-6">
                    {methods.length > 1 && (
                        <div className="grid grid-cols-2 gap-2">
                            {methods.map((m) => (
                                <button key={m} type="button" onClick={() => setMethod(m)} className={cn('rounded-2xl border-2 py-3 text-[11px] font-black uppercase tracking-widest', method === m ? 'border-action bg-action/5 text-brand' : 'border-gray-100 bg-gray-50 text-gray-400')}>{NAMES[m] ?? m}</button>
                            ))}
                        </div>
                    )}
                    <div>
                        <label htmlFor="phone" className="mb-1 ml-1 block text-[10px] font-black uppercase tracking-wider text-brand">{NAMES[method] ?? 'Mobile'} number</label>
                        <PhoneInput id="phone" value={phone} onChange={setPhone} />
                        {error && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-600">{error}</p>}
                    </div>
                    <button type="button" disabled={sending} onClick={send} className={cn(primaryButton, 'w-full py-4')}>{sending ? <LoaderCircle className="size-4 animate-spin" /> : <><Smartphone className="size-4" />Send the payment prompt</>}</button>
                </div>
            ) : panel === 'waiting' ? (
                <Panel icon={<LoaderCircle className="size-8 animate-spin text-action" />} title="Check your phone">
                    A prompt went to +266 {formatLocal(phone)}. Enter your PIN to pay; this page moves on by itself.
                </Panel>
            ) : panel === 'timeout' ? (
                <Panel icon={<Clock className="size-8 text-amber-500" />} title="Still waiting" action={<button type="button" onClick={() => router.visit(route('sessions.plan'))} className={quietButton}>Check my plan</button>}>
                    This is taking longer than usual. If you paid, it's added to your plan as soon as the payment comes through.
                </Panel>
            ) : (
                <Panel icon={<CircleX className="size-8 text-rose-500" />} title="The payment didn't go through" action={<button type="button" onClick={() => setPanel('form')} className={quietButton}>Try again</button>}>
                    Nothing was charged. You can send the prompt again.
                </Panel>
            )}
        </SessionsLayout>
    );
}

/** VENTIQ's account to pay into, then "we've paid": VENTIQ checks and confirms it. */
function BankTransfer({ purchase, bank }: { purchase: Props['purchase']; bank: NonNullable<Props['bank']> }) {
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({ type: purchase.type, tier: purchase.tier, quantity: purchase.quantity, paid_on: today, note: '' });
    const [copied, setCopied] = useState<string | null>(null);
    const copy = (value: string) => {
        void navigator.clipboard?.writeText(value);
        setCopied(value);
        window.setTimeout(() => setCopied(null), 1500);
    };

    return (
        <div className="space-y-5 rounded-2xl border border-gray-100 bg-white p-6">
            <div>
                <p className="text-sm font-black text-brand">Pay {money(purchase.amount)} into our account</p>
                <p className="mt-1 text-[12px] text-gray-500">Use the reference below so we know it's from you. EFT, a branch deposit or the FNB app all work.</p>
            </div>
            <dl className="divide-y divide-gray-50 rounded-2xl bg-gray-50">
                {bank.lines.map((l) => (
                    <div key={l.label} className={cn('flex items-center justify-between gap-3 px-4 py-2.5', l.label === 'Reference' && 'bg-action/10')}>
                        <dt className="text-[10px] font-black uppercase tracking-wider text-gray-400">{l.label}</dt>
                        <dd className="flex min-w-0 items-center gap-2 text-right text-[13px] font-bold text-brand">
                            <span className="break-all">{l.value}</span>
                            {['Account number', 'Reference', 'Branch', 'SWIFT code'].includes(l.label) && (
                                <button type="button" aria-label={`Copy the ${l.label.toLowerCase()}`} onClick={() => copy(l.label === 'Branch' ? l.value.replace(/^.*code /, '') : l.value)} className="shrink-0 text-gray-400 hover:text-action">
                                    {copied === (l.label === 'Branch' ? l.value.replace(/^.*code /, '') : l.value) ? <Check className="size-3.5 text-emerald-600" /> : <Copy className="size-3.5" />}
                                </button>
                            )}
                        </dd>
                    </div>
                ))}
            </dl>

            {bank.claimed ? (
                <p className="flex items-start gap-2 rounded-2xl bg-mint px-4 py-3 text-[12px] font-bold text-mint-ink">
                    <Clock className="mt-0.5 size-4 shrink-0" />You've told us you paid. We add it as soon as we see it in our account, usually within a working day; you'll get an email.
                </p>
            ) : (
                <form className="space-y-4 border-t border-dashed border-gray-100 pt-5" onSubmit={(e) => { e.preventDefault(); form.post(route('sessions.plan.bank-transfer')); }}>
                    <p className="text-[12px] font-bold text-gray-600">Once you've paid, tell us and we'll add it.</p>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label className="block">
                            <span className="mb-1 ml-1 block text-[10px] font-black uppercase tracking-wider text-brand">Paid on</span>
                            <input type="date" required max={today} value={form.data.paid_on} onChange={(e) => form.setData('paid_on', e.target.value)} className="w-full rounded-2xl border border-gray-200 px-4 py-3 text-[13px] font-semibold text-brand outline-none focus:border-action" />
                            {form.errors.paid_on && <span className="ml-1 mt-1 block text-[11px] font-bold text-rose-600">{form.errors.paid_on}</span>}
                        </label>
                        <label className="block">
                            <span className="mb-1 ml-1 block text-[10px] font-black uppercase tracking-wider text-brand">Note <span className="normal-case text-gray-300">(optional)</span></span>
                            <input value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="e.g. paid from our Standard Lesotho account" className="w-full rounded-2xl border border-gray-200 px-4 py-3 text-[13px] font-semibold text-brand outline-none focus:border-action" />
                        </label>
                    </div>
                    <button type="submit" disabled={form.processing} className={cn(primaryButton, 'w-full py-4')}>{form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <><Check className="size-4" />I've made the transfer</>}</button>
                </form>
            )}
        </div>
    );
}

function Panel({ icon, title, action, children }: { icon: React.ReactNode; title: string; action?: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="rounded-2xl border border-gray-100 bg-white p-8 text-center">
            <div className="mb-4 flex justify-center">{icon}</div>
            <p className="text-sm font-black text-brand">{title}</p>
            <p className="mx-auto mt-1 max-w-sm text-[12px] text-gray-500">{children}</p>
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

import { router } from '@inertiajs/react';
import { CircleX, Clock, LoaderCircle, Mail, Smartphone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SessionsLayout, { primaryButton, quietButton } from '@/Layouts/SessionsLayout';
import { PhoneInput, formatLocal } from '@/components/phone-input';
import { json } from '@/lib/http';
import { cn, money } from '@/lib/utils';

interface Props {
    purchase: { type: 'plan' | 'payg'; tier: string | null; quantity: number | null; amount: number; label: string };
    methods: string[];
    support: string;
}

const NAMES: Record<string, string> = { mpesa: 'M-Pesa', ecocash: 'EcoCash' };
const WAIT_MS = 3 * 60 * 1000;

/** Paying for a Sessions plan or more sessions: a prompt on the phone, then it's added. */
export default function PlanPayment({ purchase, methods, support }: Props) {
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

            {methods.length === 0 ? (
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

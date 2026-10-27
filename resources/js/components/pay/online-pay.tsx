import { CircleX, Clock, Smartphone, SmartphoneNfc, Zap } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { PhoneInput } from '@/components/phone-input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ManualPay } from './manual-pay';
import type { ManualOption, OnlineOptions, PushState } from './types';

export type Panel = 'form' | 'waiting' | 'pin' | 'timeout' | 'failed' | 'fallback' | 'byhand';

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/**
 * Pay online with a PIN prompt. Send → wait up to waitSeconds for EcoCash's
 * answer (PayLesotho holds the request until the PIN is entered) → if no
 * answer, ask "Did you enter your PIN?" → yes: keep checking; no: send
 * again. The server counts the tries; when they run out, `fallback` shows
 * (another way to pay, rendered by the page).
 */
export function OnlinePay({
    code,
    ticketUrl,
    options,
    manual = [],
    owed,
    top,
    startPanel,
    fallback,
}: {
    code: string;
    ticketUrl: string;
    options: OnlineOptions;
    /** Ways to pay VENTIQ by hand: chosen like a provider, shown as instructions. */
    manual?: ManualOption[];
    owed: number;
    /** Under the heading: the ticket link to keep. */
    top?: React.ReactNode;
    startPanel: Panel;
    fallback: (state: { attemptsLeft: number; retry: () => void }) => React.ReactNode;
}) {
    const [panel, setPanel] = useState<Panel>(startPanel);
    const providers = [...options.methods, ...manual.map((m) => m.method).filter((m) => !options.methods.includes(m))];
    const [method, setMethod] = useState(providers[0]);
    const chosenManual = options.methods.includes(method) ? null : manual.find((m) => m.method === method) ?? null;
    const label = (m: string) => (m === 'mpesa' ? 'M-Pesa' : m === 'bank' ? 'Bank transfer' : 'EcoCash');
    const [phone, setPhone] = useState(options.phone ?? '');
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [attemptsLeft, setAttemptsLeft] = useState(options.attemptsLeft);
    const [waitSeconds, setWaitSeconds] = useState(options.waitSeconds);
    const [secondsLeft, setSecondsLeft] = useState(0);
    const [masked, setMasked] = useState('your phone');
    const [failedText, setFailedText] = useState("The payment wasn't completed. You can try again.");

    const session = useRef<number | null>(null);
    const timers = useRef<number[]>([]);
    const attempts = useRef(attemptsLeft);

    const stopAll = useCallback(() => {
        timers.current.forEach((t) => window.clearInterval(t));
        timers.current = [];
    }, []);
    useEffect(() => stopAll, [stopAll]);

    const updateAttempts = useCallback((n?: number) => {
        if (typeof n !== 'number') return;
        attempts.current = n;
        setAttemptsLeft(n);
    }, []);

    // Out of tries → another way; else back to the form to send again.
    const retryOrFallback = useCallback(() => {
        stopAll();
        setPanel(attempts.current > 0 ? 'form' : 'fallback');
    }, [stopAll]);

    const check = useCallback(
        async (onPending?: () => void) => {
            if (!session.current) return;
            try {
                const res = await fetch(route('ticket.pay.status', { code, session: session.current }), { headers: { Accept: 'application/json' } });
                const data: PushState = await res.json();
                updateAttempts(data.attempts_left);
                if (data.status === 'completed') {
                    stopAll();
                    window.location.href = ticketUrl;
                } else if (data.status === 'failed') {
                    stopAll();
                    const left = attempts.current;
                    setFailedText((data.message || "The payment wasn't completed.") + (left > 0 ? ` You have ${left} ${left === 1 ? 'try' : 'tries'} left.` : ''));
                    setPanel('failed');
                } else {
                    onPending?.();
                }
            } catch {
                onPending?.();
            }
        },
        [code, ticketUrl, stopAll, updateAttempts],
    );

    const wait = useCallback(
        (id: number, seconds: number, total: number) => {
            session.current = id;
            stopAll();
            const deadline = Date.now() + seconds * 1000;
            setPanel('waiting');
            const tick = () => {
                const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
                setSecondsLeft(left);
                if (left === 0) {
                    stopAll();
                    // One last look before asking.
                    void check(() => setPanel('pin'));
                }
            };
            setWaitSeconds(total);
            tick();
            timers.current.push(window.setInterval(tick, 1000), window.setInterval(() => void check(), 3000));
        },
        [check, stopAll],
    );

    // A push still waiting after a reload: follow it.
    useEffect(() => {
        if (startPanel === 'waiting' && options.inFlight) {
            wait(options.inFlight.id, Math.max(5, options.waitSeconds - options.inFlight.elapsed), options.waitSeconds);
        }
        // Only on first load.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    /** EcoCash numbers start with 6, M-Pesa with 5: the provider follows the number when it can. */
    function typePhone(v: string) {
        setPhone(v);
        setError(null);
        const fits = v.startsWith('6') ? 'ecocash' : v.startsWith('5') ? 'mpesa' : null;
        if (fits && fits !== method && options.methods.includes(fits)) setMethod(fits);
    }
    const mismatch = phone.length > 0 && ((method === 'ecocash' && !phone.startsWith('6')) || (method === 'mpesa' && !phone.startsWith('5')))
        ? method === 'ecocash'
            ? 'EcoCash numbers start with 6.' + (providers.includes('mpesa') ? ' For a number starting with 5, choose M-Pesa.' : '')
            : 'M-Pesa numbers start with 5.' + (providers.includes('ecocash') ? ' For a number starting with 6, choose EcoCash.' : '')
        : null;

    async function send() {
        if (phone.length !== 8) {
            setError('Enter a valid 8-digit mobile number.');
            return;
        }
        if (mismatch) {
            setError(mismatch);
            return;
        }
        setError(null);
        setSending(true);
        try {
            const res = await fetch(route('ticket.pay.online', code), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ method, mobile_number: '+266' + phone }),
            });
            const data: PushState = await res.json();
            updateAttempts(data.attempts_left);
            if (!res.ok || !data.session_id) {
                if (data.attempts_left === 0) {
                    setPanel('fallback');
                    return;
                }
                setError(data.message || 'Could not start payment. Please try again.');
                return;
            }
            const total = data.wait_seconds || waitSeconds;
            setMasked('+266 •••• ' + phone.slice(-4));
            if (data.status === 'failed') {
                session.current = data.session_id;
                void check();
                return;
            }
            wait(data.session_id, total, total);
        } catch {
            setError('Network error. Please try again.');
        } finally {
            setSending(false);
        }
    }

    function approved() {
        // "Yes, I approved it": keep checking quietly; the answer can still come.
        setPanel('timeout');
        stopAll();
        let checks = 0;
        timers.current.push(window.setInterval(() => {
            if (++checks > 40) stopAll();
            void check();
        }, 5000));
    }

    const clock = `${Math.floor(secondsLeft / 60)}:${String(secondsLeft % 60).padStart(2, '0')}`;
    const panelTitle = 'mb-2 text-2xl font-black uppercase tracking-tight text-gray-900';

    return (
        <div className="mb-6 overflow-hidden rounded-[2.5rem] border border-gray-100 bg-white shadow-2xl shadow-gray-200/50">
            <div className="border-b border-gray-50 p-8 sm:p-10">
                <div className="mb-2 flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-action/10 text-action"><Zap className="size-5" /></div>
                    <h2 className="text-2xl font-black uppercase italic leading-none tracking-tighter text-gray-900">{options.methods.length ? 'Pay Online' : 'Pay'}</h2>
                </div>
                <p className="text-sm font-medium text-gray-500">Payment processed securely through VENTIQ. Your ticket activates as soon as the payment goes through.</p>
                {top && <div className="mt-5">{top}</div>}
            </div>

            <div className="p-8 sm:p-10">
                {panel === 'form' && (
                    <div className="space-y-6">
                        <div>
                            <Label className="mb-3">Choose Provider</Label>
                            <div className={cn('grid gap-3', providers.length >= 3 ? 'grid-cols-3' : 'grid-cols-2')}>
                                {providers.map((m) => (
                                    <button
                                        key={m}
                                        type="button"
                                        onClick={() => {
                                            setMethod(m);
                                            setError(null);
                                        }}
                                        className={cn('flex flex-col items-center gap-2 rounded-2xl border-2 p-4 text-center transition-all', method === m ? 'border-action bg-action/5' : 'border-slate-100 bg-slate-50')}
                                    >
                                        <Smartphone className={cn('size-6', m === 'mpesa' ? 'text-red-600' : 'text-blue-600')} />
                                        <span className="text-xs font-black uppercase tracking-tight text-gray-900">{label(m)}</span>
                                        {!options.methods.includes(m) && <span className="text-[9px] font-bold uppercase tracking-widest text-gray-400">Pay by hand</span>}
                                    </button>
                                ))}
                            </div>
                        </div>
                        {chosenManual ? (
                            <ManualPay key={chosenManual.method} code={code} option={chosenManual} paidFrom={options.phone} owed={owed} />
                        ) : (
                            <>
                                <div>
                                    <Label htmlFor="online_phone">{label(method)} Number <span className="text-rose-500">*</span></Label>
                                    <PhoneInput id="online_phone" value={phone} onChange={typePhone} invalid={!!(error || mismatch)} />
                                    {(error || mismatch) && <p className="ml-1 mt-2 text-[10px] font-bold uppercase text-rose-500">{error || mismatch}</p>}
                                </div>
                                <Button type="button" size="lg" className="w-full tracking-[0.4em]" onClick={send} disabled={sending}>
                                    {sending ? 'Sending…' : 'Send Payment Request'}
                                </Button>
                                {attemptsLeft < options.maxAttempts && (
                                    <p className="text-center text-[11px] font-bold text-gray-400">{attemptsLeft} of {options.maxAttempts} tries left</p>
                                )}
                            </>
                        )}
                    </div>
                )}

                {panel === 'waiting' && (
                    <div className="py-6 text-center">
                        <div className="relative mx-auto mb-6 flex size-20 items-center justify-center">
                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-action opacity-20" />
                            <div className="relative flex size-16 items-center justify-center rounded-2xl bg-action shadow-xl"><SmartphoneNfc className="size-7 text-white" /></div>
                        </div>
                        <h3 className={panelTitle}>Check Your Phone</h3>
                        <p className="mb-1 text-sm font-bold text-gray-500">A payment prompt was sent to</p>
                        <p className="mb-6 text-lg font-black text-brand">{masked}</p>
                        <p className="mb-4 text-[13px] font-bold text-gray-600">Enter your {label(method)} PIN on your phone to approve the payment.</p>
                        <div className="mx-auto max-w-xs">
                            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div className="h-full bg-action transition-all duration-1000" style={{ width: `${(100 * secondsLeft) / waitSeconds}%` }} />
                            </div>
                            <p className="mt-2 text-[11px] font-black tabular-nums text-gray-400">{clock}</p>
                        </div>
                        <p className="mt-2 text-[10px] font-bold uppercase tracking-widest text-gray-400">Please keep this page open</p>
                    </div>
                )}

                {panel === 'pin' && (
                    <div className="py-6 text-center">
                        <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-amber-100"><Smartphone className="size-7 text-amber-500" /></div>
                        <h3 className={panelTitle}>Did you enter your PIN?</h3>
                        <p className="mb-6 text-sm font-bold text-gray-500">We haven't heard back from {label(method)} yet.</p>
                        <div className="space-y-3">
                            <Button type="button" variant="dark" className="w-full" onClick={approved}>Yes, I approved it</Button>
                            <Button type="button" className="w-full" onClick={retryOrFallback}>No, send it again</Button>
                        </div>
                    </div>
                )}

                {panel === 'timeout' && (
                    <div className="py-6 text-center">
                        <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-amber-100"><Clock className="size-7 text-amber-500" /></div>
                        <h3 className={panelTitle}>We're confirming it</h3>
                        <p className="mb-6 text-sm font-bold text-gray-500">
                            If you entered your PIN and approved the payment, you're done. Please don't pay again: we'll confirm it and send your ticket. Your place is held meanwhile.
                        </p>
                        <Button asChild variant="dark" className="w-full tracking-[0.3em]"><a href={ticketUrl}>View my ticket</a></Button>
                        <button type="button" onClick={retryOrFallback} className="mt-4 text-[11px] font-bold text-gray-400 hover:text-brand">I didn't pay, try again</button>
                    </div>
                )}

                {panel === 'failed' && (
                    <div className="py-6 text-center">
                        <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-rose-100"><CircleX className="size-7 text-rose-500" /></div>
                        <h3 className={panelTitle}>Payment Failed</h3>
                        <p className="mb-6 text-sm font-bold text-gray-500">{failedText}</p>
                        <Button type="button" className="w-full tracking-[0.3em]" onClick={retryOrFallback}>{attemptsLeft > 0 ? 'Try again' : 'Pay another way'}</Button>
                    </div>
                )}

                {panel === 'fallback' && fallback({ attemptsLeft, retry: () => setPanel('form') })}

                {panel === 'byhand' && (
                    <div className="py-6 text-center">
                        <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-mint"><Clock className="size-7 text-mint-ink" /></div>
                        <h3 className={panelTitle}>We're checking your payment</h3>
                        <p className="text-sm font-bold text-gray-500">Your ticket is sent as soon as your payment reaches us, usually within minutes. No need to pay again.</p>
                    </div>
                )}
            </div>
        </div>
    );
}

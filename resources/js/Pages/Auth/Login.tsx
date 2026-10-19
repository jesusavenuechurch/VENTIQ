import { Head } from '@inertiajs/react';
import { ArrowLeft, CircleAlert, LoaderCircle, MailOpen } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AuthLayout, { authButton, authInput, authLink } from '@/Layouts/AuthLayout';
import { json } from '@/lib/http';

type Step = 'email' | 'password' | 'code';

/**
 * Email first. People who sign in with a password are asked for it;
 * Google sign-ups and new emails get a 6-digit code by email. A code can
 * always be asked for instead of a password.
 */
export default function Login({ intent, status, email: initialEmail }: { intent: 'session' | 'host' | null; status: string | null; email: string }) {
    const [step, setStep] = useState<Step>('email');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(status ?? '');
    const [notice, setNotice] = useState('');
    const [email, setEmail] = useState(initialEmail);
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const refs = { email: useRef<HTMLInputElement>(null), password: useRef<HTMLInputElement>(null), code: useRef<HTMLInputElement>(null) };

    useEffect(() => refs[step].current?.focus(), [step]); // eslint-disable-line react-hooks/exhaustive-deps

    async function post<T>(name: string, body: Record<string, string>): Promise<T> {
        const { ok, data } = await json<T & { message?: string; errors?: Record<string, string[]> }>(route(name), { method: 'POST', body: { ...body, intent } });
        if (!ok) throw new Error((data.errors && Object.values(data.errors)[0]?.[0]) || data.message || 'Something went wrong. Please try again.');
        return data;
    }

    async function run(fn: () => Promise<void>) {
        setLoading(true);
        setError('');
        try {
            await fn();
        } catch (e) {
            setError((e as Error).message);
        }
        setLoading(false);
    }

    const go = (next: Step) => {
        setStep(next);
        setNotice('');
    };
    const start = () => run(async () => go((await post<{ next: Step }>('login.start', { email })).next));
    const sendCode = () => run(async () => {
        go((await post<{ next: Step }>('login.code.send', { email })).next);
        setNotice('New code sent.');
    });
    // Signing in changes who's asking: load the next page in full.
    const withPassword = () => run(async () => window.location.replace((await post<{ redirect?: string }>('login.submit', { email, password })).redirect || '/'));
    const withCode = (value = code) => run(async () => window.location.replace((await post<{ redirect?: string }>('login.code.verify', { email, code: value })).redirect || '/'));
    const back = () => {
        setStep('email');
        setPassword('');
        setCode('');
        setError('');
    };

    const backChip = (
        <button type="button" onClick={back} className="mb-5 inline-flex max-w-full items-center gap-2 rounded-full bg-slate-50 px-3 py-1.5 text-[12px] font-bold text-brand">
            <ArrowLeft className="size-3 text-gray-400" /><span className="truncate">{email}</span>
        </button>
    );
    const spinner = <LoaderCircle className="size-5 animate-spin" />;

    return (
        <AuthLayout
            label={intent === 'session' ? 'Continue to Sessions' : 'Continue to your dashboard'}
            footer={
                <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">
                    New here? <a href={route('org.register.direct', intent ? { intent } : {})} className="text-action hover:underline">Create an account</a>
                </p>
            }
        >
            <Head title="Sign in" />
            {error && (
                <div role="alert" className="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700">
                    <CircleAlert className="mr-1 inline size-4" />{error}
                </div>
            )}

            {step === 'email' && (
                <div>
                    <a href={`${route('auth.google.redirect')}?intent=${intent ?? ''}`} className="flex w-full items-center justify-center gap-3 rounded-2xl border border-gray-200 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-brand transition-all hover:border-brand">
                        <svg viewBox="0 0 24 24" className="size-4" aria-hidden><path fill="#F07F22" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.8h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.3Z" /><path fill="#1D4069" d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.8-1.8-5.6-4.1H3.1v2.6A10 10 0 0 0 12 22Z" /><path fill="#F07F22" d="M6.4 14a6 6 0 0 1 0-3.9V7.5H3.1a10 10 0 0 0 0 9l3.3-2.5Z" /><path fill="#1D4069" d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.9-2.9A10 10 0 0 0 3.1 7.5L6.4 10C7.2 7.7 9.4 6 12 6Z" /></svg>
                        Continue with Google
                    </a>
                    <div className="my-6 flex items-center gap-3">
                        <div className="h-px flex-1 bg-gray-100" />
                        <span className="text-[9px] font-bold uppercase tracking-widest text-gray-300">or use your email</span>
                        <div className="h-px flex-1 bg-gray-100" />
                    </div>
                    <form onSubmit={(e) => { e.preventDefault(); void start(); }} className="space-y-4">
                        <label htmlFor="email" className="sr-only">Email address</label>
                        <input ref={refs.email} id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" placeholder="Email address" className={authInput} />
                        <button disabled={loading || !email} className={authButton}>{loading ? spinner : 'Continue'}</button>
                    </form>
                </div>
            )}

            {step === 'password' && (
                <div>
                    {backChip}
                    <form onSubmit={(e) => { e.preventDefault(); void withPassword(); }} className="space-y-4">
                        <label htmlFor="password" className="sr-only">Password</label>
                        <input ref={refs.password} id="password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoComplete="current-password" placeholder="Password" className={authInput} />
                        <button disabled={loading || !password} className={authButton}>{loading ? spinner : 'Sign in'}</button>
                    </form>
                    <div className="mt-5 text-center">
                        <button type="button" onClick={() => void sendCode()} disabled={loading} className={authLink}>Forgot it? Email me a code instead</button>
                    </div>
                </div>
            )}

            {step === 'code' && (
                <div>
                    {backChip}
                    <div className="mb-5 rounded-2xl bg-mint p-4 text-[12px] font-bold text-mint-ink">
                        <MailOpen className="mr-1 inline size-4" />
                        If there's an account for this email, we've sent it a 6-digit code. It works for 10 minutes.{notice && ` ${notice}`}
                    </div>
                    <form onSubmit={(e) => { e.preventDefault(); void withCode(); }} className="space-y-4">
                        <label htmlFor="code" className="sr-only">6-digit code</label>
                        <input
                            ref={refs.code}
                            id="code"
                            value={code}
                            onChange={(e) => {
                                const digits = e.target.value.replace(/\D/g, '').slice(0, 6);
                                setCode(digits);
                                if (digits.length === 6 && !loading) void withCode(digits);
                            }}
                            required
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={7}
                            placeholder="000000"
                            className={`${authInput} text-center font-mono text-2xl tracking-[0.5em]`}
                        />
                        <button disabled={loading || code.length !== 6} className={authButton}>{loading ? spinner : 'Sign in'}</button>
                    </form>
                    <div className="mt-5 flex flex-wrap justify-between gap-3">
                        <button type="button" onClick={() => void sendCode()} disabled={loading} className={authLink}>Send a new code</button>
                        <button type="button" onClick={() => go('password')} className={authLink}>Use my password instead</button>
                    </div>
                    <p className="mt-4 text-center text-[11px] text-gray-400">Can't find it? Check spam, or tap the button in the email to sign in on that device.</p>
                </div>
            )}
        </AuthLayout>
    );
}

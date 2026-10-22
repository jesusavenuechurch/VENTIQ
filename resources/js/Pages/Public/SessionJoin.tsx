import { router } from '@inertiajs/react';
import { useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { authButton } from '@/Layouts/AuthLayout';

/** For people who can't scan the QR code: the code on the check-in pass. */
export default function SessionJoin({ code, error }: { code: string; error: string | null }) {
    const [value, setValue] = useState(code);

    return (
        <GuestLayout label="Check in">
            <div className="mx-auto max-w-sm py-10">
                <div className="rounded-[2rem] bg-white p-8 shadow-xl">
                    <h1 className="text-lg font-black uppercase tracking-tight">Enter the session code</h1>
                    <p className="mb-6 mt-1 text-[12px] font-medium text-gray-500">It's printed on the check-in pass, under the QR code.</p>
                    {error && <div role="alert" className="mb-4 rounded-xl bg-rose-50 p-3 text-[12px] font-bold text-rose-600">{error}</div>}
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(route('public.session-join'), { code: value });
                        }}
                    >
                        <input
                            aria-label="Session code"
                            required
                            autoFocus
                            autoCapitalize="characters"
                            autoComplete="off"
                            value={value}
                            onChange={(e) => setValue(e.target.value.toUpperCase())}
                            placeholder="ABC123"
                            className="w-full rounded-2xl border-none bg-gray-50 px-5 py-4 text-center font-mono text-lg font-black uppercase tracking-[0.3em] outline-none focus:ring-2 focus:ring-action/20"
                        />
                        <button className={authButton}>Continue</button>
                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}

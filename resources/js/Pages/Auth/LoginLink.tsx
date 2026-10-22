import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthLayout, { authButton } from '@/Layouts/AuthLayout';

/** The emailed sign-in link: a tap, not an automatic sign-in, since mail scanners open links before people do. */
export default function LoginLink({ email, id, token }: { email: string | null; id: number; token: string }) {
    const [busy, setBusy] = useState(false);
    return (
        <AuthLayout>
            <Head title="Sign in" />
            <div className="text-center">
                {email ? (
                    <>
                        <p className="text-[15px] font-black text-brand">Sign in as {email}?</p>
                        <p className="mt-2 text-[12px] text-gray-500">You'll stay signed in on this device.</p>
                        {/* A full page load: signing in changes who's asking. */}
                        <form method="POST" action={route('login.link.use', [id, token])} className="mt-6" onSubmit={() => setBusy(true)}>
                            <input type="hidden" name="_token" value={document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? ''} />
                            <button disabled={busy} className={authButton}>Sign in</button>
                        </form>
                    </>
                ) : (
                    <>
                        <p className="text-[15px] font-black text-brand">This sign-in link no longer works</p>
                        <p className="mt-2 text-[12px] text-gray-500">Links work once, for 10 minutes. Ask for a new one from the sign-in page.</p>
                        <button type="button" onClick={() => router.visit(route('login'))} className={`${authButton} mt-6`}>Go to sign in</button>
                    </>
                )}
            </div>
        </AuthLayout>
    );
}

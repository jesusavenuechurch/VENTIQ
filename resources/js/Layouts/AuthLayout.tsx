import { Link } from '@inertiajs/react';

/** Sign-in, sign-up and their follow-ups: the wordmark, a short label, one card. */
export default function AuthLayout({ label, wide = false, children, footer }: { label?: React.ReactNode; wide?: boolean; children: React.ReactNode; footer?: React.ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 p-4 text-gray-900 selection:bg-action/30">
            <div className="mb-8 text-center">
                {label && <span className="mb-4 inline-block rounded-full bg-action-soft px-3 py-1 text-[10px] font-black uppercase tracking-widest text-action-ink">{label}</span>}
                <Link href="/" className="block text-4xl font-black uppercase tracking-tighter text-brand">VENTI<span className="text-action">Q.</span></Link>
            </div>
            <div className={`w-full rounded-[2.5rem] border border-white bg-white p-8 shadow-2xl md:p-12 ${wide ? 'max-w-2xl' : 'max-w-md'}`}>{children}</div>
            {footer && <div className="mt-6 text-center">{footer}</div>}
        </div>
    );
}

/** The inputs and buttons on the auth pages. */
export const authInput = 'w-full rounded-2xl border-2 border-gray-50 bg-gray-50 px-5 py-4 font-bold text-brand outline-none transition-all focus:border-action focus:bg-white';
export const authButton = 'flex w-full items-center justify-center rounded-2xl bg-brand py-5 text-[10px] font-black uppercase tracking-[0.3em] text-white shadow-lg transition-colors hover:bg-action disabled:opacity-60 disabled:hover:bg-brand';
export const authLink = 'text-[11px] font-bold text-gray-400 hover:text-action';

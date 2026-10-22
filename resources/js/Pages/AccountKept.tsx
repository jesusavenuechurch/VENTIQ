import { Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';

/** From the unused-account warning's "Keep my account" link. */
export default function AccountKept({ firstName }: { firstName: string }) {
    return (
        <SiteLayout>
            <Head title="Account kept" />
            <div className="mx-auto max-w-md px-4 py-16 text-center text-brand">
                <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-mint"><Check className="size-7 text-mint-ink" /></div>
                <h1 className="text-2xl font-black tracking-tight">Your account stays</h1>
                <p className="mt-2 text-[14px] text-gray-500">Thanks, {firstName}. We won't remove it. When you're ready, create your first event and start selling tickets.</p>
                <Link href={route('login')} className="mt-8 inline-block rounded-2xl bg-brand px-6 py-4 text-[11px] font-black uppercase tracking-[0.2em] text-white hover:bg-action">Sign in</Link>
            </div>
        </SiteLayout>
    );
}

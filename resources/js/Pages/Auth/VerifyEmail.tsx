import { Head, router, usePage } from '@inertiajs/react';
import { MailOpen } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Button } from '@/components/ui/button';

/** Until the email is verified, after signing up or when a page needs it. */
export default function VerifyEmail({ email }: { email: string }) {
    const { flash } = usePage().props;
    return (
        <SiteLayout>
            <Head title="Verify your email" />
            <div className="mx-auto max-w-md px-4 py-16 text-center">
                <div className="mx-auto mb-6 flex size-14 items-center justify-center rounded-2xl bg-action/10 text-action"><MailOpen className="size-6" /></div>
                <h1 className="text-2xl font-black tracking-tight text-brand">Check your email</h1>
                <p className="mt-3 text-[13px] font-medium leading-relaxed text-gray-500">
                    We sent a verification link to <strong className="text-gray-700">{email}</strong>. Open it to finish setting up your account and start creating events.
                </p>
                {flash.status === 'verification-link-sent' && <div className="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-[12px] font-bold text-emerald-700">A new verification link is on its way.</div>}
                <Button type="button" variant="brand" className="mt-8 px-6 py-3 text-[10px]" onClick={() => router.post(route('verification.send'), {}, { preserveScroll: true })}>Send the link again</Button>
                <form method="POST" action={route('logout')} className="mt-4">
                    <input type="hidden" name="_token" value={document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? ''} />
                    <button className="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">Log out</button>
                </form>
            </div>
        </SiteLayout>
    );
}

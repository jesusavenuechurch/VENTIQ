import { Head, usePage } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { AppLink } from '@/components/app-link';

/** After answering from the review link. */
export default function Done({ message, event }: { message: string; event: string }) {
    const { auth } = usePage().props;

    return (
        <SiteLayout>
            <Head title="Payment reviewed" />
            <div className="mx-auto max-w-xl px-4 py-16 text-center">
                <CircleCheck className="mx-auto mb-4 size-10 text-mint-ink" />
                <p className="text-lg font-black text-brand">{message}</p>
                <p className="mt-2 text-[12px] font-medium text-gray-500">{event}</p>
                {auth.user && (
                    <AppLink to="organizer.payments.index" className="mt-8 inline-block rounded-2xl bg-brand px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white">See other payments</AppLink>
                )}
            </div>
        </SiteLayout>
    );
}

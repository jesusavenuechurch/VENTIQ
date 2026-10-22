import { Head } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';
import { PaymentCard, type PaymentCardData } from '@/components/organizer/payment-card';

/**
 * The page behind the organizer's "Review payment" link: one payment, one
 * question. The signed, expiring link is the credential, so it works from
 * a phone without signing in.
 */
export default function Show({ organization, decided, status, payment }: { organization: string; decided: boolean; status: string; payment: PaymentCardData }) {
    return (
        <SiteLayout>
            <Head title="Review payment" />
            <div className="mx-auto max-w-xl px-4 py-10">
                <p className="mb-1 text-[10px] font-black uppercase tracking-[0.3em] text-gray-300">Ventiq · {organization}</p>
                <h1 className="mb-6 text-2xl font-black tracking-tight text-brand">Has this payment been received?</h1>
                {decided ? (
                    <div className="rounded-[1.5rem] border border-slate-100 bg-slate-50 p-6 text-[13px] font-bold text-gray-600">
                        This payment has already been {status === 'approved' ? 'confirmed' : 'rejected'}. Nothing more to do.
                    </div>
                ) : (
                    <>
                        <PaymentCard payment={payment} canDecide />
                        <p className="mt-4 text-[11px] font-medium text-gray-400">Check your {payment.accountLabel} statement for this reference before activating.</p>
                    </>
                )}
            </div>
        </SiteLayout>
    );
}

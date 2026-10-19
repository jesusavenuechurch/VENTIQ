import { Head } from '@inertiajs/react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { Crowd } from '@/components/organizer/crowd';
import { PaymentCard, type PaymentCardData } from '@/components/organizer/payment-card';

/** Payments attendees sent the organizer directly, oldest first, to confirm. */
export default function Payments({ payments, canDecide }: { payments: PaymentCardData[]; canDecide: boolean }) {
    return (
        <OrganizerLayout title="Payments to confirm" subtitle="These people paid you directly. Check the money arrived, then send them their ticket.">
            <Head title="Payments to confirm" />
            {/* Narrower than the page frame, left-aligned under the tabs. */}
            <div className="max-w-4xl">
                {!canDecide && (
                    <div className="mb-6 rounded-2xl border border-slate-100 bg-slate-50 p-4 text-[12px] font-medium text-gray-500">
                        You can see these payments, but only team members allowed to confirm payments can activate tickets.
                    </div>
                )}
                <div className="space-y-4">
                    {payments.length === 0 ? (
                        <div className="rounded-[1.5rem] border border-dashed border-gray-200 bg-white p-10 text-center">
                            <Crowd seeds={['happy-1', 'happy-2', 'happy-3']} />
                            <p className="text-[15px] font-black text-brand">All caught up!</p>
                            <p className="mt-1 text-[13px] font-medium text-gray-500">Nobody's waiting on you. When someone pays you directly, they'll show up here and we'll email you.</p>
                        </div>
                    ) : (
                        payments.map((p) => <PaymentCard key={p.id} payment={p} canDecide={canDecide} />)
                    )}
                </div>
            </div>
        </OrganizerLayout>
    );
}

import { Head, useForm, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** An old /payment/{id} style link: tickets now open only with their private link. */
export default function LinkMoved({ ticketId, maskedEmail, organizer }: { ticketId: number | null; maskedEmail: string | null; organizer: string | null }) {
    const { flash } = usePage().props;
    const form = useForm({});

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 p-6 text-brand">
            <Head title="Your ticket link has changed" />

            <div className="w-full max-w-md rounded-[2rem] border border-gray-100 bg-white p-8 text-center shadow-xl">
                <div className="mx-auto mb-5 flex size-14 items-center justify-center rounded-2xl bg-action-soft text-action-ink">
                    <ShieldCheck className="size-6" />
                </div>
                <h1 className="text-2xl font-black tracking-tight">Your ticket has a new private link</h1>
                <p className="mt-3 text-[13px] font-medium text-gray-500">To keep tickets safe, each one now has its own private link. This old link no longer opens it.</p>

                {flash.status ? (
                    <div className="mt-6 rounded-2xl bg-mint p-4 text-[13px] font-bold text-mint-ink">{flash.status}</div>
                ) : ticketId && maskedEmail ? (
                    <form
                        className="mt-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('ticket.link.send', ticketId), { preserveScroll: true });
                        }}
                    >
                        <Button type="submit" variant="brand" className="w-full py-4 text-[11px]" disabled={form.processing}>
                            Email me my ticket link
                        </Button>
                        <p className="mt-3 text-[11px] text-gray-400">It goes to {maskedEmail}, the address the ticket was registered with.</p>
                    </form>
                ) : ticketId ? (
                    <p className="mt-6 text-[13px] font-bold text-gray-600">Open the ticket link from your WhatsApp message, or ask {organizer} to send your ticket again.</p>
                ) : (
                    <p className="mt-6 text-[13px] font-bold text-gray-600">Open the ticket link from your email or WhatsApp message, or ask the organizer to send your ticket again.</p>
                )}
            </div>
        </div>
    );
}

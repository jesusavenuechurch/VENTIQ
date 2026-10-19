import { Dialog, DialogClose, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';

const SECTIONS = [
    ['1. Scope of Service', 'VENTIQ provides infrastructure for event registration, ticketing, and analytics. We act as a technology facilitator, not an event organizer.'],
    ['2. Organizer Obligations', 'Organizers are solely responsible for the accuracy of event details, pricing, and the fulfillment of services promised to ticket holders.'],
    ['3. Financial Protocols', 'All subscription fees are final. VENTIQ is not responsible for refunding ticket purchases; these must be handled between the attendee and organizer.'],
    ['4. Data Integrity', 'User data is handled according to our privacy standards. Misuse of the platform for fraudulent activities will result in immediate termination.'],
];

/** The platform terms, from the footer. */
export function TermsDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[85vh] max-w-2xl flex-col">
                <div className="p-8 pb-4">
                    <DialogTitle>Terms<span className="text-action">.</span></DialogTitle>
                    <DialogDescription>Version 2.0 | Feb 2026</DialogDescription>
                </div>
                <div className="space-y-4 overflow-y-auto p-8 pt-2 text-sm leading-relaxed text-gray-600">
                    {SECTIONS.map(([title, body]) => (
                        <section key={title}>
                            <h4 className="mb-1 text-[10px] font-black uppercase tracking-[0.1em] text-brand">{title}</h4>
                            <p>{body}</p>
                        </section>
                    ))}
                    <section className="rounded-[2rem] border border-gray-100 bg-gray-50 p-6 font-medium italic">
                        "Simply Connected" isn't just a tagline; it's our technical standard. By using VENTIQ, you agree to maintain the integrity of the network.
                    </section>
                </div>
                <div className="border-t border-gray-100 bg-gray-50/50 p-6">
                    <DialogClose className="w-full rounded-2xl bg-brand py-4 text-[10px] font-black uppercase tracking-[0.3em] text-white shadow-lg transition-all hover:bg-action">Acknowledge & Close</DialogClose>
                </div>
            </DialogContent>
        </Dialog>
    );
}

import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Info, MailCheck, MapPin, SearchCheck, Ticket } from 'lucide-react';
import { useEffect, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { TierCard } from '@/components/tier-card';
import type { EventData, TierData } from '@/types/event';
import { cn } from '@/lib/utils';

/** An event's public page: what it is, and its ticket types to choose from. */
export default function Show({
    event,
    tiers,
    canRegister,
    closedReason,
    byInvitation,
    registerUrl,
    moreEventsUrl,
}: {
    event: EventData;
    tiers: TierData[];
    canRegister: boolean;
    closedReason: string | null;
    byInvitation: boolean;
    registerUrl: string;
    moreEventsUrl: string;
}) {
    const [drawer, setDrawer] = useState(false);

    // The page behind the open drawer doesn't scroll.
    useEffect(() => {
        document.body.style.overflow = drawer ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    }, [drawer]);

    function choose(tier: TierData) {
        router.visit(`${registerUrl}?tier=${tier.id}`);
    }

    const banner = (height: string, label: string) =>
        event.banner && (
            <div className="flex flex-col items-center gap-3">
                <div className="rounded-2xl border border-gray-100 bg-white p-1.5 shadow-sm transition-transform duration-300 hover:scale-105">
                    <img src={event.banner} alt={`${event.name} graphic`} className={cn(height, 'w-auto rounded-xl object-contain')} />
                </div>
                <span className="text-center text-[8px] font-black uppercase italic tracking-[0.4em] text-gray-300">{label}</span>
            </div>
        );

    return (
        <GuestLayout
            wide
            context={event.organization.name}
            right={<a href={moreEventsUrl} className="text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-brand">More events</a>}
        >
            <Head title={`${event.name} - ${event.organization.name}`} />

            <div className="grid grid-cols-1 gap-10 pb-24 lg:grid-cols-12 lg:pb-0">
                <div className="space-y-8 lg:col-span-7">
                    <button type="button" onClick={() => window.history.back()} className="group flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 transition-colors hover:text-brand">
                        <ArrowLeft className="size-3.5 transition-transform group-hover:-translate-x-1" />
                        <span>Back</span>
                    </button>

                    <div className="space-y-6">
                        <h1 className="text-5xl font-black uppercase italic leading-[0.85] tracking-tighter">{event.name}</h1>
                        {event.status === 'draft' ? (
                            <p className="inline-flex items-center gap-2 rounded-full bg-lilac px-4 py-2 text-[12px] font-bold text-lilac-ink">
                                Preview: only your team can see this draft. Publish it to open registration.
                            </p>
                        ) : closedReason && !byInvitation ? (
                            <p className="inline-flex items-center gap-2 rounded-full bg-action-soft px-4 py-2 text-[12px] font-bold text-action-ink">
                                <Info className="size-4" />{closedReason}
                            </p>
                        ) : null}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                                <div className="mb-2 flex items-center gap-3">
                                    <CalendarDays className="size-4 text-action" />
                                    <span className="text-[9px] font-black uppercase tracking-widest text-gray-400">Schedule</span>
                                </div>
                                <p className="text-sm font-bold text-brand">{event.date}</p>
                                <p className="mt-1 text-[10px] font-medium italic text-gray-400">Starting @ {event.time}</p>
                            </div>
                            <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                                <div className="mb-2 flex items-center gap-3">
                                    <MapPin className="size-4 text-brand" />
                                    <span className="text-[9px] font-black uppercase tracking-widest text-gray-400">Venue</span>
                                </div>
                                <p className="text-sm font-bold leading-tight text-brand">{event.venue}</p>
                            </div>
                        </div>

                        <div className="border-t border-gray-100 pt-6">
                            <h3 className="mb-4 text-[9px] font-black uppercase tracking-[0.3em] text-gray-300">Briefing</h3>
                            <div className="whitespace-pre-line text-sm leading-relaxed text-gray-500">{event.description}</div>
                        </div>

                        <div className="pt-10 lg:hidden">{banner('h-40', 'Official Event Graphic')}</div>
                    </div>
                </div>

                <div className="hidden lg:col-span-5 lg:block">
                    <div className="sticky top-24 space-y-6">
                        <div className="rounded-[2rem] border border-gray-200 bg-white p-8 shadow-sm">
                            <h3 className="mb-8 text-center text-[9px] font-black uppercase tracking-[0.3em] text-gray-300">Access Selection</h3>
                            {byInvitation ? (
                                <ByInvitation organization={event.organization.name} />
                            ) : (
                                <div className="space-y-3">
                                    {tiers.map((tier) => <TierCard key={tier.id} tier={tier} open={canRegister} onChoose={choose} />)}
                                </div>
                            )}
                            <FindTicket />
                        </div>
                        <div className="px-8">{banner('h-48', 'Official Event Identity')}</div>
                    </div>
                </div>
            </div>

            {/* Phones: a button opens the ticket types from the bottom. */}
            <div className="fixed bottom-8 left-1/2 z-50 -translate-x-1/2 lg:hidden">
                <button type="button" onClick={() => setDrawer(true)} className="flex items-center gap-3 whitespace-nowrap rounded-full bg-brand px-10 py-5 text-[10px] font-black uppercase tracking-[0.3em] text-white shadow-2xl transition-all active:scale-95">
                    <Ticket className="size-4 text-action" />
                    {byInvitation ? 'Your Ticket' : 'Get Access'}
                </button>
            </div>

            <div
                className={cn('fixed inset-0 z-[60] bg-brand/40 backdrop-blur-sm transition-opacity duration-300 lg:hidden', drawer ? 'opacity-100' : 'pointer-events-none opacity-0')}
                onClick={() => setDrawer(false)}
                aria-hidden={!drawer}
            >
                <div
                    role="dialog"
                    aria-label="Choose a ticket"
                    className={cn('absolute inset-x-0 bottom-0 rounded-t-[2.5rem] bg-white p-8 transition-transform duration-300', drawer ? 'translate-y-0' : 'translate-y-full')}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="mx-auto mb-8 h-1 w-10 rounded-full bg-gray-100" />
                    <div className="max-h-[60vh] space-y-3 overflow-y-auto pb-10">
                        {byInvitation ? (
                            <ByInvitation organization={event.organization.name} />
                        ) : (
                            tiers.map((tier) => <TierCard key={tier.id} tier={tier} open={canRegister} onChoose={choose} className="border-gray-100" />)
                        )}
                        <FindTicket />
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}

/** In place of the ticket types: nobody registers here, the organizer sends the tickets. */
function ByInvitation({ organization }: { organization: string }) {
    return (
        <div className="rounded-xl bg-slate-50 p-5 text-center">
            <MailCheck className="mx-auto size-6 text-action" />
            <p className="mt-3 text-[10px] font-black uppercase tracking-[0.2em] text-brand">By invitation</p>
            <p className="mt-2 text-[13px] leading-relaxed text-gray-500">
                {organization} issues the tickets for this event. If you're on the guest list, your ticket comes to you on WhatsApp.
            </p>
        </div>
    );
}

function FindTicket() {
    return (
        <div className="mt-8 border-t border-gray-50 pt-6">
            <Link href={route('ticket.find')} className="block rounded-xl border border-amber-100 bg-amber-50 px-5 py-3 text-center transition-all hover:bg-amber-100">
                <div className="flex items-center justify-center gap-2">
                    <SearchCheck className="size-4 text-action" />
                    <span className="text-[10px] font-black uppercase tracking-widest text-gray-700">Find My Ticket</span>
                </div>
                <p className="mt-1 text-[9px] font-medium text-amber-600">Already registered? Pay or open your ticket</p>
            </Link>
        </div>
    );
}

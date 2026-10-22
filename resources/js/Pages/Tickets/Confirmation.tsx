import { Head } from '@inertiajs/react';
import { ArrowRight, Check, Clock, Download, Info, Lock, Mail, MessageCircle, SearchCheck, Ticket } from 'lucide-react';
import { SaveLink } from '@/components/save-link';
import type { TicketData } from '@/types/ticket';
import { cn, money } from '@/lib/utils';

interface Row {
    number: string;
    holderName: string | null;
    admissions: number;
    tier: string | null;
    link: string;
}

/**
 * After registering or sending a payment: where the ticket stands. Shown
 * while it's pending (what was paid, what's left, who's checking); a single
 * paid ticket goes straight to its pass instead.
 */
export default function Confirmation({
    ticket,
    completed,
    tickets,
    toPay,
    partlyPaid,
    byHand,
    paidTo,
    sentTo,
    eventUrl,
}: {
    ticket: TicketData;
    completed: boolean;
    tickets: Row[];
    toPay: number;
    partlyPaid: boolean;
    byHand: boolean;
    paidTo: { label: string; number: string | null; instructions: string | null } | null;
    sentTo: { email: string | null; whatsapp: string | null } | null;
    eventUrl: string;
}) {
    const small = 'text-[8px] font-black uppercase tracking-widest text-slate-300';

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 p-4 text-slate-900 sm:p-6">
            <Head title={`Protocol Status | ${ticket.event.name}`} />

            <main className="w-full max-w-2xl">
                <div className="overflow-hidden rounded-[2.5rem] border border-slate-100 bg-white shadow-2xl shadow-slate-200/60">
                    <div className="flex items-center justify-between border-b border-slate-50 bg-slate-50/30 px-8 py-6">
                        <div className="flex items-center gap-3">
                            <span className="text-2xl font-black italic tracking-tighter">V.</span>
                            <span className="text-[9px] font-black uppercase tracking-[0.4em] text-slate-300">{ticket.event.organization}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="relative flex size-2">
                                <span className={cn('absolute inline-flex size-full animate-ping rounded-full opacity-75', completed ? 'bg-emerald-400' : 'bg-action')} />
                                <span className={cn('relative inline-flex size-2 rounded-full', completed ? 'bg-emerald-500' : 'bg-action')} />
                            </span>
                            <span className="text-[9px] font-black uppercase tracking-[0.2em]">{ticket.paymentStatus}</span>
                        </div>
                    </div>

                    <div className="space-y-10 p-8 sm:p-12">
                        <div className="space-y-4 text-center">
                            <div className={cn('mx-auto flex size-20 items-center justify-center rounded-3xl shadow-xl', completed ? 'bg-emerald-500 shadow-emerald-200' : 'bg-action shadow-orange-200')}>
                                {completed ? <Check className="size-9 text-white" /> : <Clock className="size-9 text-white" />}
                            </div>
                            <h1 className="text-5xl font-black uppercase italic leading-none tracking-tighter sm:text-7xl">{completed ? 'Verified' : 'Pending'}</h1>
                            <div className="inline-flex items-center gap-3 rounded-2xl bg-gradient-to-r from-brand to-action px-6 py-3 shadow-lg">
                                <Ticket className="size-5 text-white" />
                                <div className="text-left">
                                    <p className="mb-1 text-[8px] font-black uppercase leading-none tracking-widest text-white/60">Reference ID</p>
                                    <p className="font-mono text-xl font-black leading-none tracking-tight text-white">#{ticket.number}</p>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col items-center justify-between gap-4 rounded-3xl border border-slate-100 bg-slate-50 p-6 text-center sm:flex-row sm:text-left">
                            <div>
                                <p className={cn(small, 'mb-1')}>Event</p>
                                <h2 className="text-sm font-black uppercase tracking-tight">{ticket.event.name}</h2>
                            </div>
                            <div className="flex gap-6">
                                <div>
                                    <p className={cn(small, 'mb-1')}>Date</p>
                                    <p className="text-[10px] font-black uppercase">{ticket.event.date}</p>
                                </div>
                                <div>
                                    <p className={cn(small, 'mb-1')}>Venue</p>
                                    <p className="max-w-[120px] truncate text-[10px] font-black uppercase">{ticket.event.venue}</p>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <p className="mb-6 text-center text-[9px] font-black uppercase tracking-[0.4em] text-slate-300">Authorized Identity</p>
                            {tickets.map((row, index) => (
                                <div key={row.number} className="rounded-[2rem] border-2 border-slate-100 bg-white p-6 transition-all hover:border-action hover:shadow-lg sm:p-8">
                                    <div className="mb-6 flex items-center justify-between">
                                        <div>
                                            <p className="mb-1 text-[8px] font-black uppercase text-action">
                                                {row.admissions > 1 ? `Group ticket · ${row.admissions} people` : `Attendee 0${index + 1}`}
                                            </p>
                                            <h4 className="text-2xl font-black uppercase italic leading-none tracking-tighter text-slate-900">{row.holderName}</h4>
                                        </div>
                                        {index === 0 && <span className="rounded-full border border-brand/20 bg-brand/10 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-brand">Primary</span>}
                                    </div>
                                    <div className="mb-6 grid grid-cols-2 gap-4 border-t border-dashed border-slate-100 pt-6">
                                        <div>
                                            <p className="text-[9px] font-black uppercase tracking-widest text-slate-300">Ticket ID</p>
                                            <p className="mt-1 font-mono text-[10px] font-black text-slate-900">{row.number}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-[9px] font-black uppercase tracking-widest text-slate-300">Tier</p>
                                            <p className="mt-1 text-[10px] font-black uppercase tracking-tight text-slate-900">{row.tier}</p>
                                        </div>
                                    </div>
                                    {completed ? (
                                        <a href={row.link} target="_blank" rel="noopener" className="group flex w-full items-center justify-center rounded-2xl bg-action py-5 text-white shadow-lg transition-all hover:bg-brand">
                                            <Download className="mr-3 size-4 transition-transform group-hover:translate-y-0.5" />
                                            <span className="text-[11px] font-black uppercase tracking-[0.3em]">Download Ticket</span>
                                        </a>
                                    ) : (
                                        <div className="flex w-full items-center justify-center rounded-2xl bg-slate-100 py-5 text-slate-400">
                                            <Lock className="mr-3 size-4" />
                                            <span className="text-[11px] font-black uppercase tracking-[0.3em]">Locked Until Payment is Confirmed</span>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>

                        {!completed && (
                            <div className="space-y-6 border-t border-dashed border-slate-200 pt-10">
                                <div className="flex items-end justify-between">
                                    <p className="text-[9px] font-black uppercase leading-none tracking-widest text-slate-400">{partlyPaid ? 'Balance left' : 'To pay'}</p>
                                    <span className="text-4xl font-black italic leading-none tracking-tighter text-slate-900">{money(toPay)}</span>
                                </div>

                                {byHand && (
                                    <div className="rounded-3xl bg-mint p-6 text-mint-ink">
                                        <p className="text-[13px] font-black"><SearchCheck className="mr-1 inline size-4" />We're checking your EcoCash payment</p>
                                        <p className="mt-1 text-[12px] font-medium">Your ticket is sent as soon as your payment reaches us, usually within minutes. Your place is held meanwhile. No need to pay again.</p>
                                    </div>
                                )}

                                {paidTo && (
                                    <div className="rounded-3xl bg-slate-900 p-6 text-white">
                                        <div className="mb-4 flex items-center gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-xl bg-action"><Info className="size-4 text-white" /></div>
                                            <div>
                                                <p className="text-[9px] font-black uppercase tracking-[0.3em] text-action">Paid to</p>
                                                <p className="text-[12px] font-bold text-white">{paidTo.label}</p>
                                                {paidTo.number && <p className="font-mono text-[12px] text-slate-300">{paidTo.number}</p>}
                                            </div>
                                        </div>
                                        {paidTo.instructions && <p className="text-xs font-bold italic leading-relaxed text-slate-300">"{paidTo.instructions}"</p>}
                                    </div>
                                )}
                            </div>
                        )}

                        {sentTo && (sentTo.email || sentTo.whatsapp) && (
                            <div className="grid grid-cols-1 gap-4 border-t border-slate-100 pt-6 md:grid-cols-2">
                                {sentTo.email && (
                                    <div className="rounded-2xl border-2 border-blue-100 bg-blue-50/50 p-6 text-center">
                                        <Mail className="mx-auto mb-3 size-6 text-blue-500" />
                                        <h4 className="mb-1 text-[10px] font-black uppercase tracking-widest text-gray-900">Sent to Email</h4>
                                        <p className="truncate text-[9px] font-bold text-blue-600">{sentTo.email}</p>
                                    </div>
                                )}
                                {sentTo.whatsapp && (
                                    <div className="rounded-2xl border-2 border-emerald-100 bg-emerald-50/50 p-6 text-center">
                                        <MessageCircle className="mx-auto mb-3 size-7 text-emerald-500" />
                                        <h4 className="mb-1 text-[10px] font-black uppercase tracking-widest text-gray-900">Sent to WhatsApp</h4>
                                        <p className="text-[9px] font-bold text-emerald-600">{sentTo.whatsapp}</p>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="pt-4">
                            <a href={eventUrl} className="flex w-full items-center justify-center gap-3 rounded-2xl border-2 border-slate-900 py-5 text-[10px] font-black uppercase tracking-[0.4em] text-slate-900 transition-all hover:bg-slate-900 hover:text-white">
                                <span>Event Home</span>
                                <ArrowRight className="size-3" />
                            </a>
                        </div>
                    </div>
                </div>

                <SaveLink ticket={ticket} className="mt-6" />

                <p className="ml-[1em] mt-8 text-center text-[9px] font-black uppercase tracking-[1em] text-slate-300 opacity-50">Ventiq Protocol</p>
            </main>
        </div>
    );
}

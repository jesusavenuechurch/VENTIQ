import { Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { TicketData } from '@/types/ticket';
import { cn, money } from '@/lib/utils';

type Tone = 'waiting' | 'confirming' | 'closed';

/**
 * The ticket exists but won't scan: shown instead of the pass until it's
 * activated, or once it has expired or been cancelled. No QR here, so a
 * screenshot can't be mistaken for a valid ticket.
 */
export default function Inactive({
    ticket,
    state,
    submitted,
    paymentUrl,
    registerUrl,
}: {
    ticket: TicketData;
    state: { headline: string; body: string; tone: Tone };
    submitted: boolean;
    paymentUrl: string;
    registerUrl: string | null;
}) {
    const tones: Record<Tone, string> = {
        waiting: 'bg-amber-50 border-amber-200 text-amber-800',
        confirming: 'bg-sky-50 border-sky-200 text-sky-800',
        closed: 'bg-rose-50 border-rose-200 text-rose-800',
    };
    const label = 'mb-1 text-[10px] font-bold uppercase tracking-widest text-slate-400';
    const pending = ticket.status === 'pending';

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-900 p-6">
            <Head title={`Ventiq Pass - ${ticket.event.name}`} />

            <div className="w-full max-w-lg">
                <div className="mb-6 flex items-center justify-between px-2">
                    <span className="text-sm font-black uppercase tracking-tighter text-white">Ventiq</span>
                    <span className="text-[9px] font-bold uppercase tracking-widest text-white/60">{ticket.number}</span>
                </div>

                <div className="overflow-hidden rounded-[2rem] bg-white">
                    <div className="p-8">
                        <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">{ticket.event.organization}</p>
                        <h1 className="mt-1 text-2xl font-black uppercase leading-tight tracking-tight text-slate-900">{ticket.event.name}</h1>

                        <div className="mt-6 grid grid-cols-2 gap-6 text-sm">
                            <div>
                                <p className={label}>Guest</p>
                                <p className="font-extrabold text-slate-900">{ticket.holderName}</p>
                                {ticket.admissions > 1 && <p className="text-[11px] font-bold text-slate-500">Group of {ticket.admissions}</p>}
                            </div>
                            <div>
                                <p className={label}>Date</p>
                                <p className="font-extrabold text-slate-900">{ticket.event.date}</p>
                            </div>
                            <div>
                                <p className={label}>Ticket</p>
                                <p className="font-extrabold text-slate-900">
                                    {ticket.tier.name}
                                    {ticket.admissions > 1 && ` · admits ${ticket.admissions}`}
                                </p>
                            </div>
                            <div>
                                <p className={label}>Amount</p>
                                <p className="font-extrabold text-slate-900">{money(ticket.amount)}</p>
                            </div>
                        </div>
                    </div>

                    <div className={cn('mx-8 mb-8 rounded-2xl border p-5', tones[state.tone])}>
                        <p className="text-[12px] font-black uppercase tracking-widest"><Lock className="mr-1 inline size-3.5" />{state.headline}</p>
                        <p className="mt-2 text-[13px] font-medium leading-relaxed">{state.body}</p>
                        {pending && ticket.paymentDueAt && !submitted && <p className="mt-2 text-[12px] font-bold">Pay by {ticket.paymentDueAt}.</p>}
                    </div>

                    {pending && !submitted ? (
                        <div className="px-8 pb-8">
                            <a href={paymentUrl} className="block w-full rounded-2xl bg-slate-900 py-4 text-center text-[11px] font-black uppercase tracking-[0.2em] text-white hover:bg-brand">
                                Pay or submit your payment
                            </a>
                        </div>
                    ) : registerUrl ? (
                        <div className="px-8 pb-8">
                            <a href={registerUrl} className="block w-full rounded-2xl bg-action py-4 text-center text-[11px] font-black uppercase tracking-[0.2em] text-white hover:bg-brand">
                                Register again
                            </a>
                        </div>
                    ) : pending ? (
                        <div className="px-8 pb-8 text-center">
                            <a href={paymentUrl} className="text-[11px] font-bold text-gray-400 hover:text-brand">Something wrong? Send proof or pay another way</a>
                        </div>
                    ) : null}
                </div>

                <p className="mt-6 text-center text-[10px] font-bold uppercase tracking-widest text-white/40">This ticket can't be scanned until it's active</p>
            </div>
        </div>
    );
}

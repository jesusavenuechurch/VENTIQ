import { Head } from '@inertiajs/react';
import { Camera, FileText } from 'lucide-react';
import { useRef, useState } from 'react';
import type { TicketData } from '@/types/ticket';

/** The active ticket: the pass shown at the door, saved as an image or a PDF. */
/** Tickets bought on one purchase for several people: each its own ticket and QR. */
interface Together {
    count: number;
    buyer: string;
    /** On the buyer's ticket: the others, each with its link. Null on theirs. */
    others: { name: string; number: string; status: string; link: string }[] | null;
}

export default function Pass({ ticket, qrUrl, pdfUrl, together }: { ticket: TicketData; qrUrl: string; pdfUrl: string; together?: Together | null }) {
    const pass = useRef<HTMLDivElement>(null);
    const [saving, setSaving] = useState(false);
    const tierColour = ticket.tier.isVip ? '#D4AF37' : '#10b981';

    // Draws the pass into a PNG in the browser. Loaded only when asked for.
    async function saveImage() {
        if (!pass.current) return;
        setSaving(true);
        try {
            const { default: html2canvas } = await import('html2canvas');
            const canvas = await html2canvas(pass.current, {
                scale: 3, useCORS: true, backgroundColor: null, logging: false,
                // Keep the pass's rounded corners in the picture.
                onclone: (doc) => {
                    const el = doc.getElementById('ticket-capture');
                    if (el) el.style.borderRadius = '2.5rem';
                },
            });
            const link = document.createElement('a');
            link.download = `Ventiq-${ticket.number}.png`;
            link.href = canvas.toDataURL('image/png', 1.0);
            link.click();
        } finally {
            setSaving(false);
        }
    }

    const label = 'mb-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-400';
    const value = 'text-base font-extrabold uppercase leading-tight text-slate-900';

    return (
        <div className="flex min-h-screen items-center justify-center bg-black bg-radial-[at_top] from-slate-900 via-black to-black p-6">
            <Head title={`Ventiq Pass - ${ticket.event.name}`} />

            <div className="w-full max-w-2xl animate-in fade-in duration-700">
                <div className="mb-6 flex items-center justify-between px-4">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-black uppercase tracking-tighter text-white">Ventiq</span>
                        <span className="hidden size-1 rounded-full bg-white/20 sm:block" />
                        <span className="hidden text-[9px] font-bold uppercase tracking-[0.3em] text-white/40 sm:inline">Digital Fulfillment</span>
                    </div>
                    <div className="rounded-full border border-white/10 px-3 py-1">
                        <span className="whitespace-nowrap text-[9px] font-bold uppercase tracking-widest text-white/60">{ticket.number}</span>
                    </div>
                </div>

                <div id="ticket-capture" ref={pass} className="shadow-[0_40px_80px_-20px_rgba(0,0,0,0.6)]">
                    <div className="relative flex flex-col overflow-hidden rounded-[2.5rem] bg-white md:flex-row">
                        <div className="relative grow bg-white p-10 md:p-12">
                            <div className="pointer-events-none absolute -bottom-6 -left-8 -rotate-12 select-none opacity-[0.03]">
                                <h1 className="text-[120px] font-black italic">VENTIQ</h1>
                            </div>

                            <div className="relative z-10">
                                <div className="mb-12">
                                    <div className="mb-4 inline-block rounded-lg px-4 py-1.5 text-[11px] font-black uppercase tracking-widest text-white shadow-sm" style={{ backgroundColor: tierColour }}>
                                        {ticket.tier.name}
                                    </div>
                                    <h2 className="text-3xl font-black uppercase leading-none tracking-tighter text-slate-900">{ticket.event.name}</h2>
                                </div>

                                <div className="grid grid-cols-2 gap-x-8 gap-y-10">
                                    <div>
                                        <p className={label}>Guest Name</p>
                                        <p className={value}>{ticket.holderName}</p>
                                        {ticket.admissions > 1 && <p className="mt-1 text-[11px] font-black uppercase text-slate-500">Admits {ticket.admissions}</p>}
                                    </div>
                                    <div>
                                        <p className={label}>Date & Time</p>
                                        <p className={value}>{ticket.event.date}</p>
                                    </div>
                                    <div className="col-span-2 pb-2">
                                        <p className={label}>Venue Location</p>
                                        <p className={`${value} max-w-sm leading-relaxed`}>{ticket.event.venue}</p>
                                        {ticket.event.location && <p className="max-w-sm text-[11px] font-semibold leading-relaxed text-slate-500">{ticket.event.location}</p>}
                                    </div>
                                </div>

                                <div className="mt-12 opacity-40">
                                    <span className="text-[9px] font-black uppercase tracking-widest text-slate-900">{ticket.event.organization}</span>
                                </div>
                            </div>
                        </div>

                        <div className="relative flex min-h-[320px] w-full flex-col items-center justify-center bg-slate-50/80 p-10 md:w-72">
                            <div className="absolute bottom-10 left-0 top-10 hidden md:block">
                                <div className="h-full w-0.5 bg-[radial-gradient(#cbd5e1_30%,transparent_30%)] bg-[length:1px_18px] bg-center" />
                            </div>

                            <div className="flex size-44 items-center justify-center rounded-3xl border border-slate-200 bg-white p-4 shadow-md">
                                <img src={qrUrl} crossOrigin="anonymous" alt="QR Code" className="aspect-square size-full object-contain" />
                            </div>

                            <div className="mt-8 text-center">
                                <p className="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400">Entry Pass</p>
                                <p className="mt-1 text-[9px] font-bold uppercase text-slate-400">Scan at Entrance</p>
                            </div>

                            {ticket.voucherCode && (
                                <div className="mt-6 w-full rounded-2xl border-2 border-dashed border-slate-200 bg-white px-4 py-3 text-center">
                                    <p className="mb-1 text-[8px] font-black uppercase tracking-[0.25em] text-slate-400">Entry Code</p>
                                    <p className="text-2xl font-black tracking-[0.2em] text-slate-900">{ticket.voucherCode}</p>
                                    <p className="mt-1 text-[8px] text-slate-400">Show this if you cannot scan</p>
                                </div>
                            )}

                            <div className="absolute bottom-6 flex items-center gap-1.5 opacity-20">
                                <span className="text-[8px] font-bold uppercase">Powered by</span>
                                <span className="text-[10px] font-black uppercase tracking-tighter">Ventiq</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-10 grid grid-cols-2 gap-4">
                    <button type="button" onClick={saveImage} disabled={saving} className="flex items-center justify-center gap-3 rounded-2xl bg-white py-5 text-[10px] font-black uppercase tracking-[0.2em] text-black transition-all hover:bg-slate-100 disabled:opacity-60">
                        <Camera className="size-4" />
                        <span>{saving ? 'Saving…' : 'Save Image'}</span>
                    </button>
                    <a href={pdfUrl} className="flex items-center justify-center gap-3 rounded-2xl border border-white/10 bg-white/5 py-5 text-[10px] font-black uppercase tracking-[0.2em] text-white transition-all hover:bg-white/10">
                        <FileText className="size-4 text-red-500" />
                        <span>PDF Ticket</span>
                    </a>
                </div>

                {together && (
                    <div className="mt-6 rounded-2xl border border-white/10 bg-white/5 p-5 text-white">
                        <p className="text-[10px] font-black uppercase tracking-[0.2em] text-white/50">Bought together · {together.count} tickets</p>
                        {together.others ? (
                            <ul className="mt-3 divide-y divide-white/10">
                                {together.others.map((o) => (
                                    <li key={o.number} className="flex items-center justify-between gap-3 py-2.5">
                                        <span className="min-w-0">
                                            <span className="block truncate text-[13px] font-bold">{o.name}</span>
                                            <span className="block font-mono text-[10px] text-white/40">{o.number}{o.status !== 'active' && o.status !== 'checked_in' ? ` · ${o.status}` : ''}</span>
                                        </span>
                                        <a href={o.link} className="shrink-0 text-[10px] font-black uppercase tracking-widest text-action hover:underline">Open</a>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-2 text-[13px] text-white/70">Bought by {together.buyer}, with {together.count - 1 === 1 ? 'one other ticket' : `${together.count - 1} other tickets`}. Each person has their own ticket and QR.</p>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

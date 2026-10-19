import { Link } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import { useState } from 'react';
import type { TicketData } from '@/types/ticket';
import { cn } from '@/lib/utils';

/**
 * The ticket's private link, to keep: the way back to pay, send proof or
 * show the ticket, for people who have no email or WhatsApp copy.
 */
export function SaveLink({ ticket, className }: { ticket: TicketData; className?: string }) {
    const [label, setLabel] = useState('Save');

    async function save() {
        try {
            if (navigator.share) {
                await navigator.share({ title: ticket.event.name, url: ticket.link });
                setLabel('Shared');
            } else {
                await navigator.clipboard.writeText(ticket.link);
                setLabel('Copied');
            }
        } catch {
            // Cancelled: nothing to do.
        }
    }

    return (
        <div className={cn('rounded-3xl border border-slate-100 bg-white p-5', className)}>
            <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">
                <Link2 className="mr-1 inline size-3.5 text-action" />Your ticket link: save it
            </p>
            <p className="mt-1 text-[12px] font-medium text-gray-500">Open it any time to pay, send proof of payment or show your ticket. Keep it to yourself.</p>
            <div className="mt-3 flex gap-2">
                <input
                    readOnly
                    value={ticket.link}
                    aria-label="Your ticket link"
                    onClick={(e) => e.currentTarget.select()}
                    className="min-w-0 flex-1 rounded-xl bg-slate-50 px-3 py-2 font-mono text-[11px] text-gray-700 outline-none"
                />
                <button type="button" onClick={save} className="shrink-0 rounded-xl bg-brand px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action">
                    {label}
                </button>
            </div>
            {ticket.voucherCode && (
                <p className="mt-3 text-[11px] font-bold text-gray-500">
                    Lost it? Use <Link href={route('ticket.find')} className="underline">Find my ticket</Link> with your phone and entry code{' '}
                    <span className="font-mono text-brand">{ticket.voucherCode}</span>.
                </p>
            )}
        </div>
    );
}

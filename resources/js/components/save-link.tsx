import { Link } from '@inertiajs/react';
import { Copy, Link2 } from 'lucide-react';
import { toast } from 'sonner';
import type { TicketData } from '@/types/ticket';
import { cn } from '@/lib/utils';

/**
 * The ticket's private link, to keep: one line, copied with a tap (shared
 * on phones that can). The way back to pay, send proof or show the ticket.
 */
export function SaveLink({ ticket, className }: { ticket: TicketData; className?: string }) {
    async function copy() {
        try {
            await navigator.clipboard.writeText(ticket.link);
            toast.success('Ticket link copied. Keep it to yourself.');
        } catch {
            // No clipboard (an old browser): the phone's share sheet instead.
            try {
                await navigator.share?.({ title: ticket.event.name, url: ticket.link });
            } catch {
                // Cancelled.
            }
        }
    }

    return (
        <div className={cn('space-y-1.5', className)}>
            <button
                type="button"
                onClick={copy}
                title="Copy your ticket link"
                className="group flex w-full items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-left transition-all hover:border-action hover:bg-white"
            >
                <Link2 className="size-4 shrink-0 text-action" />
                <span className="min-w-0 flex-1">
                    <span className="block text-[10px] font-black uppercase tracking-widest text-gray-400">Your ticket link</span>
                    <span className="block truncate font-mono text-[11px] text-gray-700">{ticket.link.replace(/^https?:\/\//, '')}</span>
                </span>
                <span className="flex shrink-0 items-center gap-1 text-[10px] font-black uppercase tracking-widest text-gray-400 group-hover:text-action">
                    <Copy className="size-3.5" />Copy
                </span>
            </button>
            {ticket.voucherCode && (
                <p className="px-1 text-[11px] font-medium text-gray-400">
                    Lost it? <Link href={route('ticket.find')} className="font-bold underline">Find my ticket</Link> with your phone and entry code{' '}
                    <span className="font-mono font-bold text-brand">{ticket.voucherCode}</span>.
                </p>
            )}
        </div>
    );
}

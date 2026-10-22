import { Users } from 'lucide-react';
import type { TierData } from '@/types/event';
import { cn } from '@/lib/utils';

/** A ticket type to choose on the event page. */
export function TierCard({ tier, open, onChoose, className }: { tier: TierData; open: boolean; onChoose: (tier: TierData) => void; className?: string }) {
    const unavailable = tier.soldOut;

    return (
        <button
            type="button"
            disabled={unavailable}
            onClick={() => onChoose(tier)}
            className={cn(
                'group block w-full rounded-xl border bg-slate-50 p-5 text-left transition-all',
                unavailable ? 'cursor-not-allowed border-gray-100 opacity-60' : 'border-transparent hover:border-brand hover:bg-white',
                className,
            )}
        >
            <div className="mb-3 flex items-start justify-between">
                <div className="flex-1 pr-4">
                    <span className="block text-[9px] font-black uppercase tracking-[0.15em] text-action">{tier.name}</span>
                    {tier.admits > 1 && (
                        <span className="mr-1 mt-1 inline-flex items-center gap-1 rounded-full bg-brand/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-brand">
                            <Users className="size-3" />Admits {tier.admits}
                        </span>
                    )}
                    {tier.fewLeft !== null && (
                        <span className="mt-1 inline-block rounded-full bg-action/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-action">Only {tier.fewLeft} left</span>
                    )}
                    <span className="mt-0.5 block line-clamp-1 text-[10px] italic text-gray-400">{tier.description || 'Secure Entry'}</span>
                </div>
                <span className="whitespace-nowrap text-lg font-black tracking-tighter">{tier.priceLabel}</span>
            </div>

            {tier.depositFrom && (
                <div className="mt-3 flex items-center justify-between rounded-lg border border-emerald-100 bg-emerald-50 p-2.5">
                    <span className="text-[9px] font-black uppercase tracking-tight text-emerald-700">Deposit Option</span>
                    <span className="rounded bg-white px-2 py-0.5 text-[9px] font-bold text-emerald-600 shadow-sm">From {tier.depositFrom}</span>
                </div>
            )}

            {unavailable && (
                <div className="mt-3 w-full rounded-lg bg-gray-100 py-2 text-center text-[10px] font-black uppercase italic tracking-widest text-gray-400">
                    {open ? 'Sold Out' : 'Closed'}
                </div>
            )}
        </button>
    );
}

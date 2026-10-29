import { Check, LoaderCircle, Rocket } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

const STARS = Array.from({ length: 14 }, (_, i) => ({ left: (i * 37) % 100, delay: (i * 0.7) % 4, duration: 2.5 + ((i * 13) % 20) / 10, size: i % 3 === 0 ? 3 : 2 }));

/**
 * The whole screen while VENTIQ works after a meeting: a rocket on its
 * way, and the step it's on. Steps move on with time (the last one stays
 * until the work is done). Optionally sent to the background, where a
 * small pill keeps showing it's still running.
 */
export function WorkingScreen({
    title,
    steps,
    note,
    done,
    doneLabel = 'Ready',
    onBackground,
}: {
    title: string;
    steps: string[];
    note?: string;
    /** The work has finished: shows it, briefly, before the screen goes. */
    done?: boolean;
    doneLabel?: string;
    onBackground?: () => void;
}) {
    const [step, setStep] = useState(0);
    useEffect(() => {
        if (done) return;
        const t = window.setInterval(() => setStep((s) => Math.min(s + 1, steps.length - 1)), 3500);
        return () => window.clearInterval(t);
    }, [done, steps.length]);

    return (
        <div role="status" aria-live="polite" className="fixed inset-0 z-[70] flex items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_50%_30%,#24386A_0%,#1D2F55_55%,#14213D_100%)] px-6 text-white animate-in fade-in duration-300">
            {STARS.map((s, i) => (
                <span key={i} className="ventiq-drift absolute top-0 rounded-full bg-white/70" style={{ left: `${s.left}%`, width: s.size, height: s.size, animationDelay: `${s.delay}s`, animationDuration: `${s.duration}s` }} />
            ))}

            <div className="relative flex w-full max-w-md flex-col items-center text-center">
                <div className="relative mb-8 flex size-32 items-center justify-center rounded-full bg-white/5 ring-1 ring-white/10">
                    {done ? (
                        <span className="flex size-16 items-center justify-center rounded-full bg-emerald-400 text-[#14213D] animate-in zoom-in duration-300"><Check className="size-8" strokeWidth={3} /></span>
                    ) : (
                        <span className="ventiq-ride flex flex-col items-center">
                            <Rocket className="size-14 text-white" strokeWidth={1.6} style={{ transform: 'rotate(-45deg)' }} />
                            <span className="ventiq-flame -mt-1 h-8 w-3 rounded-full bg-gradient-to-b from-[#F7A35C] via-[#F07F22] to-transparent blur-[1px]" />
                        </span>
                    )}
                </div>

                <h2 className="text-2xl font-black tracking-tight">{done ? doneLabel : title}</h2>
                {!done && (
                    <ol className="mt-6 w-full space-y-2 text-left">
                        {steps.map((s, i) => (
                            <li key={s} className={cn('flex items-center gap-3 rounded-2xl px-4 py-2.5 text-[13px] font-bold transition-all duration-500', i === step ? 'bg-white/10 text-white' : i < step ? 'text-white/50' : 'text-white/25')}>
                                <span className={cn('flex size-5 shrink-0 items-center justify-center rounded-full', i < step ? 'bg-emerald-400/90 text-[#14213D]' : i === step ? 'text-[#F7A35C]' : 'border border-white/20')}>
                                    {i < step ? <Check className="size-3" strokeWidth={3} /> : i === step ? <LoaderCircle className="size-4 animate-spin" /> : null}
                                </span>
                                {s}
                            </li>
                        ))}
                    </ol>
                )}
                {note && !done && <p className="mt-5 text-[12px] font-medium leading-relaxed text-white/60">{note}</p>}
                {onBackground && !done && (
                    <button type="button" onClick={onBackground} className="mt-7 rounded-full border border-white/25 px-5 py-2.5 text-[11px] font-black uppercase tracking-widest text-white transition hover:bg-white hover:text-[#1D2F55]">
                        Keep working in the background
                    </button>
                )}
            </div>
        </div>
    );
}

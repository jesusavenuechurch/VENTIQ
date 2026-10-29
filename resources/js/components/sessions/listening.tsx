import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

const BARS = 24;

/**
 * While a session records: bars that move with what the microphone
 * actually hears, so the room can see VENTIQ is listening (and a dead
 * microphone shows as flat). No typing on the phone that records.
 */
export function Listening({ levels, paused, item }: { levels: () => Uint8Array | null; paused: boolean; item: string | null }) {
    const bars = useRef<(HTMLSpanElement | null)[]>([]);
    const heard = useRef<HTMLSpanElement | null>(null);

    useEffect(() => {
        let frame = 0;
        let quietFor = 0;
        const draw = () => {
            const data = paused ? null : levels();
            let loudest = 0;
            for (let i = 0; i < BARS; i++) {
                // Mirror the bands from the middle out: speech sits in the low ones.
                const band = data ? data[Math.min(data.length - 1, Math.abs(i - BARS / 2) + 1)] : 0;
                loudest = Math.max(loudest, band);
                const h = Math.max(0.06, band / 255);
                const el = bars.current[i];
                if (el) el.style.transform = `scaleY(${h})`;
            }
            quietFor = loudest > 60 ? 0 : quietFor + 1;
            if (heard.current) heard.current.textContent = paused ? 'Recording paused' : quietFor > 180 ? 'Listening… the room is quiet' : 'Listening: VENTIQ can hear the room';
            frame = requestAnimationFrame(draw);
        };
        frame = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(frame);
    }, [levels, paused]);

    return (
        <div className="py-8">
            <div className="flex h-28 items-center justify-center gap-[5px]" aria-hidden="true">
                {Array.from({ length: BARS }).map((_, i) => (
                    <span
                        key={i}
                        ref={(el) => void (bars.current[i] = el)}
                        className={cn('h-full w-[6px] rounded-full transition-transform duration-75', paused ? 'bg-slate-200' : 'bg-gradient-to-t from-[#F07F22] to-[#D4AF37]')}
                        style={{ transform: 'scaleY(0.06)' }}
                    />
                ))}
            </div>
            <p className="mt-5 flex items-center justify-center gap-2 text-[11px] font-black uppercase tracking-[2px] text-slate-500">
                {!paused && <span className="size-1.5 animate-pulse rounded-full bg-rose-500" />}
                <span ref={heard} role="status">Listening</span>
            </p>
            {item && !paused && <p className="mt-2 text-center text-[13px] font-semibold text-slate-400">What's said goes under <span className="text-slate-700">{item}</span>. Tap Next item as the meeting moves on.</p>}
        </div>
    );
}

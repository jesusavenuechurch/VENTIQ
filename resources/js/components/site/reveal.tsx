import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/** Fades a section in as it scrolls into view. */
export function Reveal({ className, children }: { className?: string; children: React.ReactNode }) {
    const ref = useRef<HTMLElement>(null);
    const [shown, setShown] = useState(false);
    useEffect(() => {
        const el = ref.current;
        if (!el || !('IntersectionObserver' in window)) return setShown(true);
        const io = new IntersectionObserver(([e]) => e.isIntersecting && (setShown(true), io.disconnect()), { threshold: 0.12 });
        io.observe(el);
        return () => io.disconnect();
    }, []);
    return (
        <section ref={ref} className={cn('transition-all duration-700 ease-[cubic-bezier(.22,1,.36,1)]', shown ? 'translate-y-0 opacity-100' : 'translate-y-7 opacity-0', className)}>
            {children}
        </section>
    );
}

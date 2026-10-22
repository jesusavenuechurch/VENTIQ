import { Eraser } from 'lucide-react';
import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export interface SignaturePadHandle {
    /** The drawing as a PNG data URL, cropped to the ink, or null when nothing's drawn. */
    toDataUrl: () => string | null;
    clear: () => void;
}

/**
 * Draw with a finger, a pen or the mouse. Strokes are smoothed; the PNG
 * keeps a transparent background so it sits on any certificate.
 */
export const SignaturePad = forwardRef<SignaturePadHandle, { className?: string; onChange?: (drawn: boolean) => void }>(function SignaturePad({ className, onChange }, ref) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const points = useRef<{ x: number; y: number }[]>([]);
    const drawing = useRef(false);
    const [drawn, setDrawn] = useState(false);

    const context = () => canvas.current?.getContext('2d') ?? null;

    // Sharp on high-density screens: the canvas is sized in device pixels.
    const resize = useCallback(() => {
        const c = canvas.current;
        if (!c) return;
        const ratio = window.devicePixelRatio || 1;
        const { width, height } = c.getBoundingClientRect();
        c.width = Math.round(width * ratio);
        c.height = Math.round(height * ratio);
        const ctx = context();
        if (!ctx) return;
        ctx.scale(ratio, ratio);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0f2a4a';
        ctx.lineWidth = 2.6;
        setDrawn(false);
        onChange?.(false);
    }, [onChange]);

    useEffect(() => {
        resize();
        window.addEventListener('resize', resize);
        return () => window.removeEventListener('resize', resize);
    }, [resize]);

    const at = (e: React.PointerEvent) => {
        const r = canvas.current!.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    };

    const down = (e: React.PointerEvent) => {
        e.preventDefault();
        canvas.current?.setPointerCapture(e.pointerId);
        drawing.current = true;
        points.current = [at(e)];
        const ctx = context();
        const p = points.current[0];
        ctx?.beginPath();
        ctx?.arc(p.x, p.y, 1.2, 0, Math.PI * 2);
        ctx?.fill();
    };
    const move = (e: React.PointerEvent) => {
        if (!drawing.current) return;
        const ctx = context();
        const pts = points.current;
        pts.push(at(e));
        if (!ctx || pts.length < 3) return;
        // A curve through the midpoints: smooth, without corners.
        const [a, b, c] = pts.slice(-3);
        ctx.beginPath();
        ctx.moveTo((a.x + b.x) / 2, (a.y + b.y) / 2);
        ctx.quadraticCurveTo(b.x, b.y, (b.x + c.x) / 2, (b.y + c.y) / 2);
        ctx.stroke();
        if (!drawn) {
            setDrawn(true);
            onChange?.(true);
        }
    };
    const up = () => {
        drawing.current = false;
    };

    const clear = () => {
        const c = canvas.current;
        const ctx = context();
        if (!c || !ctx) return;
        ctx.save();
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, c.width, c.height);
        ctx.restore();
        setDrawn(false);
        onChange?.(false);
    };

    useImperativeHandle(ref, () => ({
        clear,
        toDataUrl: () => {
            const c = canvas.current;
            const ctx = context();
            if (!c || !ctx || !drawn) return null;
            // Crop to the ink, with a little room around it.
            const { data, width, height } = ctx.getImageData(0, 0, c.width, c.height);
            let [x0, y0, x1, y1] = [width, height, 0, 0];
            for (let y = 0; y < height; y++) {
                for (let x = 0; x < width; x++) {
                    if (data[(y * width + x) * 4 + 3] > 0) {
                        if (x < x0) x0 = x;
                        if (y < y0) y0 = y;
                        if (x > x1) x1 = x;
                        if (y > y1) y1 = y;
                    }
                }
            }
            if (x1 <= x0 || y1 <= y0) return null;
            const pad = 8;
            [x0, y0, x1, y1] = [Math.max(0, x0 - pad), Math.max(0, y0 - pad), Math.min(width, x1 + pad), Math.min(height, y1 + pad)];
            const out = document.createElement('canvas');
            out.width = x1 - x0;
            out.height = y1 - y0;
            out.getContext('2d')?.drawImage(c, x0, y0, out.width, out.height, 0, 0, out.width, out.height);
            return out.toDataURL('image/png');
        },
    }));

    return (
        <div className={cn('relative', className)}>
            <canvas
                ref={canvas}
                aria-label="Draw your signature"
                onPointerDown={down}
                onPointerMove={move}
                onPointerUp={up}
                onPointerCancel={up}
                className="h-48 w-full touch-none cursor-crosshair rounded-2xl border-2 border-dashed border-gray-200 bg-white"
            />
            <span className="pointer-events-none absolute inset-x-8 bottom-12 border-b border-gray-200" />
            {!drawn && <span className="pointer-events-none absolute inset-0 flex items-center justify-center text-[13px] font-medium text-gray-300">Sign here</span>}
            <button type="button" onClick={clear} className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-gray-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand"><Eraser className="size-3" />Clear</button>
        </div>
    );
});

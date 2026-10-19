import { CircleAlert, IdCard, MessageCircle, Printer, QrCode, Share2, X } from 'lucide-react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export interface ShareData {
    name: string;
    date: string | null;
    url: string;
    qr: string;
    slug: string;
    published: boolean;
}

/** Opens the share panel for an event. */
export function ShareButton({ share, onOpen, className }: { share: ShareData | null; onOpen: (s: ShareData) => void; className?: string }) {
    if (!share) return null;
    return (
        <button type="button" onClick={() => onOpen(share)} className={className ?? 'rounded-full border border-slate-100 bg-slate-50 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:bg-white'}>
            <QrCode className="mr-1 inline size-3.5" />Share &amp; QR
        </button>
    );
}

async function qrImage(src: string): Promise<HTMLImageElement> {
    const img = new Image();
    img.src = src;
    await img.decode();
    return img;
}

function save(canvas: HTMLCanvasElement, name: string) {
    canvas.toBlob((blob) => {
        if (!blob) return;
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }, 'image/png');
}

function wrap(g: CanvasRenderingContext2D, text: string, max: number): string[] {
    const lines: string[] = [];
    let line = '';
    for (const word of text.split(/\s+/)) {
        const test = line ? `${line} ${word}` : word;
        if (g.measureText(test).width > max && line) {
            lines.push(line);
            line = word;
        } else {
            line = test;
        }
    }
    if (line) lines.push(line);
    return lines;
}

/**
 * Share an event: its link, WhatsApp and Facebook, and the QR as downloads
 * for posters. The QR is the same-origin SVG drawn onto a canvas, so the
 * canvas stays exportable.
 */
export function ShareEventDialog({ share, onClose }: { share: ShareData | null; onClose: () => void }) {
    const [copied, setCopied] = useState(false);
    const [busy, setBusy] = useState(false);
    if (!share) return null;

    async function copy() {
        try {
            await navigator.clipboard.writeText(share!.url);
        } catch {
            // Older browsers: the field can still be copied by hand.
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    async function downloadPng() {
        setBusy(true);
        try {
            const size = 2000;
            const c = document.createElement('canvas');
            c.width = c.height = size;
            const g = c.getContext('2d')!;
            g.fillStyle = '#fff';
            g.fillRect(0, 0, size, size);
            g.imageSmoothingEnabled = false;
            g.drawImage(await qrImage(share!.qr), 0, 0, size, size);
            save(c, `${share!.slug}-qr.png`);
        } finally {
            setBusy(false);
        }
    }

    // 1200 px wide card: name, date, QR, call to action, link.
    async function downloadCard() {
        setBusy(true);
        try {
            await document.fonts.ready;
            const W = 1200;
            const q = 820;
            const c = document.createElement('canvas');
            let g = c.getContext('2d')!;
            g.font = '900 72px Inter, sans-serif';
            const title = wrap(g, share!.name, W - 160).slice(0, 3);
            const qy = 150 + title.length * 86 + (share!.date ? 70 : 0);
            const H = qy + q + 280;
            c.width = W;
            c.height = H;
            g = c.getContext('2d')!;
            g.textAlign = 'center';
            g.fillStyle = '#ffffff';
            g.fillRect(0, 0, W, H);
            g.fillStyle = '#1D4069';
            g.fillRect(0, 0, W, 16);
            g.fillStyle = '#F07F22';
            g.fillRect(0, 16, W, 6);

            let y = 150;
            g.fillStyle = '#1D4069';
            g.font = '900 72px Inter, sans-serif';
            for (const line of title) {
                g.fillText(line, W / 2, y);
                y += 86;
            }
            if (share!.date) {
                g.font = '700 38px Inter, sans-serif';
                g.fillStyle = '#64748b';
                g.fillText(share!.date, W / 2, y + 4);
            }
            g.imageSmoothingEnabled = false;
            g.drawImage(await qrImage(share!.qr), (W - q) / 2, qy, q, q);
            g.fillStyle = '#F07F22';
            g.font = '900 52px Inter, sans-serif';
            g.fillText('SCAN TO REGISTER', W / 2, qy + q + 80);
            g.fillStyle = '#1D4069';
            g.font = '700 32px Inter, sans-serif';
            g.fillText(share!.url.replace(/^https?:\/\//, ''), W / 2, qy + q + 140, W - 120);
            g.fillStyle = '#94a3b8';
            g.font = '700 24px Inter, sans-serif';
            g.fillText('TICKETS BY VENTIQ', W / 2, H - 44);
            save(c, `${share!.slug}-qr-card.png`);
        } finally {
            setBusy(false);
        }
    }

    const option = 'flex w-full items-center gap-3 rounded-2xl p-3 text-left disabled:opacity-60';
    const icon = 'flex size-10 shrink-0 items-center justify-center rounded-xl bg-white';

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent showClose={false} className="max-h-[92vh] overflow-y-auto rounded-[2rem]">
                <div className="p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">Share event</p>
                            <DialogTitle className="mt-1 text-lg normal-case not-italic leading-tight tracking-normal">{share.name}</DialogTitle>
                        </div>
                        <button type="button" onClick={onClose} className="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-50 text-gray-400 hover:text-brand" aria-label="Close"><X className="size-4" /></button>
                    </div>

                    {!share.published && (
                        <p className="mt-4 rounded-2xl bg-action-soft p-3 text-[11px] font-bold text-action-ink">
                            <CircleAlert className="mr-1 inline size-3.5" />This event is private, so the link won't open for attendees. Make it public in Edit event to share it.
                        </p>
                    )}

                    <div className="mt-5 rounded-[1.5rem] border border-gray-100 bg-white p-5 text-center">
                        <img src={share.qr} alt={`QR code for ${share.name}`} className="mx-auto size-48" width={192} height={192} />
                        <p className="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Scan to register</p>
                    </div>

                    <div className="mt-5 flex gap-2">
                        <input readOnly value={share.url} onFocus={(e) => e.currentTarget.select()} className="min-w-0 flex-1 rounded-full border border-slate-100 bg-slate-50 px-4 py-2.5 text-[12px] font-bold text-brand" />
                        <button type="button" onClick={copy} className={cn('rounded-full px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white transition-colors', copied ? 'bg-mint-ink' : 'bg-brand hover:bg-action')}>
                            {copied ? 'Copied' : 'Copy'}
                        </button>
                    </div>

                    <div className="mt-3 grid grid-cols-2 gap-2">
                        <a href={`https://wa.me/?text=${encodeURIComponent(`${share.name} ${share.url}`)}`} target="_blank" rel="noopener" className="rounded-full bg-[#25D366] px-4 py-2.5 text-center text-[10px] font-black uppercase tracking-widest text-white">
                            <MessageCircle className="mr-1 inline size-3.5" />WhatsApp
                        </a>
                        <a href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(share.url)}`} target="_blank" rel="noopener" className="rounded-full bg-[#1877F2] px-4 py-2.5 text-center text-[10px] font-black uppercase tracking-widest text-white">
                            <Share2 className="mr-1 inline size-3.5" />Facebook
                        </a>
                    </div>

                    <p className="mb-2 mt-6 text-[10px] font-black uppercase tracking-widest text-gray-400">Download for your poster</p>
                    <div className="space-y-2">
                        <button type="button" onClick={downloadCard} disabled={busy} className={cn(option, 'bg-lilac hover:brightness-95')}>
                            <span className={cn(icon, 'text-lilac-ink')}><IdCard className="size-4" /></span>
                            <span className="min-w-0">
                                <span className="block text-[12px] font-black text-lilac-ink">QR card (PNG)</span>
                                <span className="block text-[11px] font-medium text-gray-500">QR with the event name, date and link. Drop it onto any poster.</span>
                            </span>
                        </button>
                        <button type="button" onClick={downloadPng} disabled={busy} className={cn(option, 'bg-slate-50 hover:bg-slate-100')}>
                            <span className={cn(icon, 'text-brand')}><QrCode className="size-4" /></span>
                            <span className="min-w-0">
                                <span className="block text-[12px] font-black text-brand">QR code only (PNG)</span>
                                <span className="block text-[11px] font-medium text-gray-500">2000 × 2000 px, for Canva, Word or WhatsApp status.</span>
                            </span>
                        </button>
                        <a href={`${share.qr}?download=1`} className={cn(option, 'bg-slate-50 hover:bg-slate-100')}>
                            <span className={cn(icon, 'text-brand')}><Printer className="size-4" /></span>
                            <span className="min-w-0">
                                <span className="block text-[12px] font-black text-brand">QR code for print (SVG)</span>
                                <span className="block text-[11px] font-medium text-gray-500">Stays sharp at any size: banners, pull-ups, flyers. Give this one to your designer.</span>
                            </span>
                        </a>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

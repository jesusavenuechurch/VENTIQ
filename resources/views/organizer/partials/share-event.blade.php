{{-- Share an event: its link, WhatsApp/Facebook, and the QR code as
     downloads for posters. One panel per page; open it with
     @include('organizer.partials.share-button', ['event' => $event]). --}}
<div x-data="shareEvent()" x-on:share-event.window="open($event.detail)" x-cloak>
    <div x-show="shown" x-transition.opacity
         class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center sm:p-4 bg-[#1D4069]/40 backdrop-blur-sm"
         x-on:keydown.escape.window="shown = false">
        <div x-show="shown" x-transition x-on:click.outside="shown = false"
             role="dialog" aria-modal="true" aria-labelledby="share-title"
             class="w-full sm:max-w-md bg-white rounded-t-[2rem] sm:rounded-[2rem] shadow-2xl max-h-[92vh] overflow-y-auto no-scrollbar">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Share event</p>
                        <h2 id="share-title" class="text-lg font-black text-[#1D4069] leading-tight mt-1" x-text="ev.name"></h2>
                    </div>
                    <button type="button" x-on:click="shown = false" class="w-9 h-9 shrink-0 rounded-full bg-slate-50 text-gray-400 hover:text-[#1D4069]" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <p x-show="!ev.published" class="mt-4 p-3 rounded-2xl bg-action-soft text-[11px] font-bold text-action-ink">
                    <i class="fas fa-circle-exclamation mr-1"></i>This event is private, so the link won't open for attendees. Make it public in Edit event to share it.
                </p>

                {{-- The QR code, as it will print. --}}
                <div class="mt-5 rounded-[1.5rem] border border-gray-100 bg-white p-5 text-center">
                    <img :src="ev.qr" :alt="'QR code for ' + ev.name" class="mx-auto w-48 h-48" width="192" height="192">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mt-2">Scan to register</p>
                </div>

                {{-- Link --}}
                <div class="mt-5 flex gap-2">
                    <input type="text" readonly :value="ev.url" x-on:focus="$el.select()"
                           class="flex-1 min-w-0 px-4 py-2.5 rounded-full bg-slate-50 border border-slate-100 text-[12px] font-bold text-[#1D4069]">
                    <button type="button" x-on:click="copy()"
                            class="px-4 py-2.5 rounded-full text-[10px] font-black uppercase tracking-widest text-white transition-colors"
                            :class="copied ? 'bg-mint-ink' : 'bg-brand hover:bg-action'">
                        <span x-text="copied ? 'Copied' : 'Copy'"></span>
                    </button>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a :href="'https://wa.me/?text=' + encodeURIComponent(ev.name + ' ' + ev.url)" target="_blank" rel="noopener"
                       class="px-4 py-2.5 rounded-full bg-[#25D366] text-white text-[10px] font-black uppercase tracking-widest text-center">
                        <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                    </a>
                    <a :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(ev.url)" target="_blank" rel="noopener"
                       class="px-4 py-2.5 rounded-full bg-[#1877F2] text-white text-[10px] font-black uppercase tracking-widest text-center">
                        <i class="fab fa-facebook-f mr-1"></i>Facebook
                    </a>
                </div>

                {{-- Downloads --}}
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-6 mb-2">Download for your poster</p>
                <div class="space-y-2">
                    <button type="button" x-on:click="downloadCard()" :disabled="busy"
                            class="w-full flex items-center gap-3 p-3 rounded-2xl bg-lilac text-left hover:brightness-95 disabled:opacity-60">
                        <span class="w-10 h-10 shrink-0 rounded-xl bg-white flex items-center justify-center text-lilac-ink"><i class="fas fa-id-card"></i></span>
                        <span class="min-w-0">
                            <span class="block text-[12px] font-black text-lilac-ink">QR card (PNG)</span>
                            <span class="block text-[11px] font-medium text-gray-500">QR with the event name, date and link. Drop it onto any poster.</span>
                        </span>
                    </button>
                    <button type="button" x-on:click="downloadPng()" :disabled="busy"
                            class="w-full flex items-center gap-3 p-3 rounded-2xl bg-slate-50 text-left hover:bg-slate-100 disabled:opacity-60">
                        <span class="w-10 h-10 shrink-0 rounded-xl bg-white flex items-center justify-center text-[#1D4069]"><i class="fas fa-qrcode"></i></span>
                        <span class="min-w-0">
                            <span class="block text-[12px] font-black text-[#1D4069]">QR code only (PNG)</span>
                            <span class="block text-[11px] font-medium text-gray-500">2000 × 2000 px, for Canva, Word or WhatsApp status.</span>
                        </span>
                    </button>
                    <a :href="ev.qr + '?download=1'"
                       class="w-full flex items-center gap-3 p-3 rounded-2xl bg-slate-50 text-left hover:bg-slate-100">
                        <span class="w-10 h-10 shrink-0 rounded-xl bg-white flex items-center justify-center text-[#1D4069]"><i class="fas fa-print"></i></span>
                        <span class="min-w-0">
                            <span class="block text-[12px] font-black text-[#1D4069]">QR code for print (SVG)</span>
                            <span class="block text-[11px] font-medium text-gray-500">Stays sharp at any size: banners, pull-ups, flyers. Give this one to your designer.</span>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
    function shareEvent() {
        return {
            shown: false, copied: false, busy: false,
            ev: { name: '', date: '', url: '', qr: '', slug: '', published: true },

            open(detail) { this.ev = detail; this.copied = false; this.shown = true; },

            async copy() {
                try { await navigator.clipboard.writeText(this.ev.url); }
                catch (e) { document.querySelector('[x-on\\:focus]')?.select(); document.execCommand('copy'); }
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            },

            // The QR SVG drawn onto a canvas. Same origin, so the canvas
            // stays exportable.
            async qrImage() {
                const img = new Image();
                img.src = this.ev.qr;
                await img.decode();
                return img;
            },

            save(canvas, name) {
                canvas.toBlob(blob => {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = name;
                    a.click();
                    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
                }, 'image/png');
            },

            async downloadPng() {
                this.busy = true;
                try {
                    const size = 2000, c = document.createElement('canvas');
                    c.width = c.height = size;
                    const g = c.getContext('2d');
                    g.fillStyle = '#fff'; g.fillRect(0, 0, size, size);
                    g.imageSmoothingEnabled = false;
                    g.drawImage(await this.qrImage(), 0, 0, size, size);
                    this.save(c, this.ev.slug + '-qr.png');
                } finally { this.busy = false; }
            },

            // 1200 px wide card: name, date, QR, call to action, link.
            async downloadCard() {
                this.busy = true;
                try {
                    await document.fonts.ready;
                    // Measure the title first so the card is as tall as its content.
                    const W = 1200, q = 820, c = document.createElement('canvas');
                    let g = c.getContext('2d');
                    g.font = '900 72px Inter, sans-serif';
                    const title = this.wrap(g, this.ev.name, W - 160).slice(0, 3);
                    const qy = 150 + title.length * 86 + (this.ev.date ? 70 : 0);
                    const H = qy + q + 280;
                    c.width = W; c.height = H;
                    g = c.getContext('2d');
                    g.textAlign = 'center';

                    g.fillStyle = '#ffffff'; g.fillRect(0, 0, W, H);
                    g.fillStyle = '#1D4069'; g.fillRect(0, 0, W, 16);
                    g.fillStyle = '#F07F22'; g.fillRect(0, 16, W, 6);

                    let y = 150;
                    g.fillStyle = '#1D4069';
                    g.font = '900 72px Inter, sans-serif';
                    for (const line of title) { g.fillText(line, W / 2, y); y += 86; }
                    if (this.ev.date) {
                        g.font = '700 38px Inter, sans-serif'; g.fillStyle = '#64748b';
                        g.fillText(this.ev.date, W / 2, y + 4);
                    }

                    g.imageSmoothingEnabled = false;
                    g.drawImage(await this.qrImage(), (W - q) / 2, qy, q, q);

                    g.fillStyle = '#F07F22';
                    g.font = '900 52px Inter, sans-serif';
                    g.fillText('SCAN TO REGISTER', W / 2, qy + q + 80);

                    g.fillStyle = '#1D4069';
                    g.font = '700 32px Inter, sans-serif';
                    g.fillText(this.ev.url.replace(/^https?:\/\//, ''), W / 2, qy + q + 140, W - 120);

                    g.fillStyle = '#94a3b8';
                    g.font = '700 24px Inter, sans-serif';
                    g.fillText('TICKETS BY VENTIQ', W / 2, H - 44);

                    this.save(c, this.ev.slug + '-qr-card.png');
                } finally { this.busy = false; }
            },

            wrap(g, text, max) {
                const lines = [];
                let line = '';
                for (const word of text.split(/\s+/)) {
                    const test = line ? line + ' ' + word : word;
                    if (g.measureText(test).width > max && line) { lines.push(line); line = word; }
                    else line = test;
                }
                if (line) lines.push(line);
                return lines;
            },
        };
    }
</script>
@endonce

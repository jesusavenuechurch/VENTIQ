<div class="p-4" x-data="{ 
    link: '{{ $link }}',
    copyToClipboard() {
        if (!navigator.clipboard) {
            // Fallback for non-secure contexts
            const textArea = document.createElement('textarea');
            textArea.value = this.link;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                $tooltip('Copied to clipboard', { timeout: 2000 });
            } catch (err) {
                console.error('Fallback copy failed', err);
            }
            document.body.removeChild(textArea);
            return;
        }

        navigator.clipboard.writeText(this.link).then(() => {
            $tooltip('Copied to clipboard', { timeout: 2000 });
        });
    }
}">
    <div class="space-y-4">
        <div class="bg-slate-50 dark:bg-white/5 rounded-2xl p-4 border border-slate-100 dark:border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-[#1D4069]/10 flex items-center justify-center">
                    <x-heroicon-s-user class="w-6 h-6 text-[#1D4069]" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                        {{ $ticket->holder_name }}
                    </h3>
                    <p class="text-xs font-medium text-[#F07F22] uppercase tracking-wider">
                        {{ $ticket->event->name }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="bg-slate-50/50 dark:bg-white/5 p-3 rounded-xl border border-slate-100 dark:border-white/10">
                <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold block mb-1">Ticket ID</span>
                <p class="font-mono font-bold text-gray-900 dark:text-white">{{ $ticket->ticket_number }}</p>
            </div>
            <div class="bg-slate-50/50 dark:bg-white/5 p-3 rounded-xl border border-slate-100 dark:border-white/10">
                <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold block mb-1">Tier</span>
                <p class="font-bold text-gray-900 dark:text-white">{{ $ticket->tier->tier_name }}</p>
            </div>
        </div>

        <div class="space-y-2">
            <label class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500 ml-1">
                Download URL
            </label>
            <div class="flex gap-2 p-1 bg-slate-100 dark:bg-gray-950 rounded-2xl border border-slate-200 dark:border-white/10">
                <input 
                    type="text" 
                    x-model="link"
                    readonly
                    class="flex-1 px-3 py-2 bg-transparent border-none focus:ring-0 text-sm font-mono text-gray-600 dark:text-gray-400"
                />
                
                <x-filament::button 
                    color="primary"
                    size="sm"
                    icon="heroicon-m-clipboard-document"
                    x-on:click="copyToClipboard()"
                    class="rounded-xl shadow-sm"
                >
                    Copy
                </x-filament::button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-2">
            <x-filament::button
                href="{{ $link }}"
                tag="a"
                target="_blank"
                color="gray"
                icon="heroicon-m-arrow-top-right-on-square"
                class="rounded-xl shadow-sm border-slate-200"
            >
                Preview
            </x-filament::button>

            @if($ticket->client->phone)
            @php
                $waMessage = urlencode("Hi {$ticket->holder_name}, here's your ticket for {$ticket->event->name}: {$link}");
                $waPhone = preg_replace('/\D/', '', $ticket->client->phone);
            @endphp
            <x-filament::button
                href="https://wa.me/{{ $waPhone }}?text={{ $waMessage }}"
                tag="a"
                target="_blank"
                color="success"
                icon="heroicon-m-chat-bubble-left-right"
                class="rounded-xl shadow-sm"
            >
                WhatsApp
            </x-filament::button>
            @endif
        </div>
    </div>
</div>
{{-- The ticket's private link, to keep: the way back to pay, send proof
     or show the ticket, for people who have no email or WhatsApp copy. --}}
@php $link = route('ticket.download', $ticket->qr_code); @endphp
<div class="rounded-3xl border border-slate-100 bg-white p-5 {{ $class ?? '' }}">
    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400"><i class="fas fa-link mr-1 text-[#F07F22]"></i>Your ticket link: save it</p>
    <p class="mt-1 text-[12px] font-medium text-gray-500">Open it any time to pay, send proof of payment or show your ticket. Keep it to yourself.</p>
    <div class="mt-3 flex gap-2">
        <input readonly value="{{ $link }}" aria-label="Your ticket link" onclick="this.select()"
               class="min-w-0 flex-1 rounded-xl bg-slate-50 px-3 py-2 font-mono text-[11px] text-gray-700 outline-none">
        <button type="button" class="shrink-0 rounded-xl bg-[#1D4069] px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-[#F07F22]"
                onclick="(navigator.share ? navigator.share({ title: @js($ticket->event->name), url: @js($link) }) : navigator.clipboard.writeText(@js($link))).then(() => { this.textContent = navigator.share ? 'Shared' : 'Copied'; }).catch(() => {})">
            Save
        </button>
    </div>
    @if($ticket->voucher_code)
        <p class="mt-3 text-[11px] font-bold text-gray-500">Lost it? Use <a href="{{ route('ticket.find') }}" class="underline">Find my ticket</a> with your phone and entry code <span class="font-mono text-[#1D4069]">{{ $ticket->voucher_code }}</span>.</p>
    @endif
</div>

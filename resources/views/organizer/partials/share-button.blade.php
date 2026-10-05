{{-- Opens the share panel (organizer.partials.share-event) for $event. --}}
@if($event->share_url)
    <button type="button"
            x-on:click="$dispatch('share-event', @js([
                'name'      => $event->name,
                'date'      => $event->event_date?->format('l, j F Y'),
                'url'       => $event->share_url,
                'qr'        => route('organizer.events.qr', $event),
                'slug'      => $event->slug,
                'published' => (bool) $event->is_public,
            ]))"
            class="{{ $class ?? 'px-4 py-2 rounded-full bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:bg-white' }}">
        <i class="fas fa-qrcode mr-1"></i>Share &amp; QR
    </button>
@endif

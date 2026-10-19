{{-- Puts a draft $event live. --}}
<form method="POST" action="{{ route('organizer.events.publish', $event) }}" class="{{ $formClass ?? '' }}">
    @csrf
    <button class="px-4 py-2 rounded-full bg-mint-ink text-white text-[10px] font-black uppercase tracking-widest hover:opacity-90"><i class="fas fa-rocket mr-1"></i>Publish</button>
</form>

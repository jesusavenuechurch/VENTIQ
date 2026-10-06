{{-- The three parts of Settings. --}}
@php
    $sections = [
        ['organizer.organization.edit', 'Organization', 'fa-building',     'organizer.organization.*'],
        ['organizer.payout.edit',       'Payouts',      'fa-money-bill-transfer', 'organizer.payout.*'],
        ['organizer.team.index',        'Team',         'fa-user-group',   'organizer.team.*'],
    ];
@endphp
<nav class="mb-6 flex gap-1 p-1 rounded-2xl bg-slate-100 w-full sm:w-fit overflow-x-auto no-scrollbar" aria-label="Settings">
    @foreach($sections as [$route, $label, $icon, $active])
        @php $on = request()->routeIs($active); @endphp
        <a href="{{ route($route) }}" @if($on) aria-current="page" @endif
           class="flex-1 sm:flex-none whitespace-nowrap px-4 py-2.5 rounded-xl text-[11px] font-black uppercase tracking-widest text-center transition-colors {{ $on ? 'bg-white text-[#1D4069] shadow-sm' : 'text-gray-500 hover:text-[#1D4069]' }}">
            <i class="fas {{ $icon }} mr-1"></i>{{ $label }}
        </a>
    @endforeach
</nav>

{{-- Shared top of every organizer page: who we're looking at, the area's
     tabs, and the super admin "acting as" banner. --}}
@if($actingAsOrganization ?? false)
    <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-between gap-4">
        <p class="text-[11px] font-bold text-amber-800">
            <i class="fas fa-user-shield mr-1"></i>
            Super admin view of <strong>{{ $currentOrganization->name }}</strong>. Changes you make here are made on their behalf.
        </p>
        <form method="POST" action="{{ route('organizer.act-as.stop') }}">
            @csrf
            <button class="text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-full bg-amber-600 text-white hover:bg-amber-700">Back to admin</button>
        </form>
    </div>
@endif

<div class="mb-8">
    <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em] mb-1">Ventiq · {{ $currentOrganization->name }}</p>
    <h1 class="text-2xl font-black text-[#1D4069] tracking-tight">{{ $title }}</h1>
    @isset($subtitle)
        <p class="text-[13px] font-medium text-gray-500 mt-1">{{ $subtitle }}</p>
    @endisset

    <nav class="mt-5 flex flex-wrap items-center gap-2">
        {{-- Sessions and the rest of Ventiq are reached from the home page. --}}
        <a href="{{ route('home') }}" class="px-3 py-2 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069]">
            <i class="fas fa-arrow-left mr-1"></i>Home
        </a>
        @foreach([
            'organizer.home'           => ['Events', 'fa-calendar', 'organizer.home|organizer.events.*'],
            'organizer.payments.index' => ['Payments to confirm', 'fa-receipt', 'organizer.payments.*'],
            'organizer.accounts.index' => ['Payment accounts', 'fa-wallet', 'organizer.accounts.*'],
        ] as $route => [$label, $icon, $active])
            <a href="{{ route($route) }}"
               class="px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all
                      {{ request()->routeIs(...explode('|', $active)) ? 'bg-[#1D4069] text-white' : 'bg-white border border-gray-100 text-gray-500 hover:text-[#1D4069]' }}">
                <i class="fas {{ $icon }} mr-1"></i>{{ $label }}
            </a>
        @endforeach
    </nav>

    @if(session('status'))
        <div class="mt-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-[12px] font-bold text-emerald-700">
            {{ session('status') }}
        </div>
    @endif
</div>

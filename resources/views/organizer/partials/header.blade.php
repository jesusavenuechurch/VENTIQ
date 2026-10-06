{{-- Shared top of every organizer page: who we're looking at, the area's
     tabs, and the super admin "acting as" banner. The tabs use Livewire's
     wire:navigate: the next page is fetched in the background and only the
     page is swapped, so switching tabs doesn't reload everything. --}}
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

{{-- Same frame on every page: the organization and the tabs sit at the
     top in the same place, and only what's below them changes. --}}
<div class="mb-8">
    @php
        // The public site is the logo in the top bar; Sessions is in the account menu.
        $toConfirm = $paymentsToConfirm();
        $tabs = [
            ['organizer.home',           'Events',              'Events',     'fa-calendar-days', 'organizer.home|organizer.events.*', 0],
            ['organizer.payments.index', 'Payments to confirm', 'To confirm', 'fa-receipt',       'organizer.payments.*',              $toConfirm],
            ['organizer.accounts.index', 'Payment accounts',    'Accounts',   'fa-wallet',        'organizer.accounts.*',              0],
            ['organizer.organization.edit', 'Settings',         'Settings',   'fa-gear',          'organizer.organization.*|organizer.payout.*|organizer.team.*', 0],
        ];
        // Only tabs this person can open.
        if (!auth()->user()?->can('view_payment_method')) {
            $tabs = array_values(array_filter($tabs, fn ($t) => $t[0] !== 'organizer.accounts.index'));
        }
    @endphp

    <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.3em]">Ventiq · {{ $currentOrganization->name }}</p>

    {{-- Wider screens: the row of tabs, always first. --}}
    <nav class="mt-3 pb-5 mb-6 border-b border-gray-100 hidden sm:flex flex-wrap items-center gap-2">
        @foreach($tabs as [$route, $label, $short, $icon, $active, $badge])
            <a href="{{ route($route) }}" wire:navigate.hover
               class="px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all
                      {{ request()->routeIs(...explode('|', $active)) ? 'bg-brand border border-brand text-white' : 'bg-white border border-gray-100 text-gray-500 hover:text-[#1D4069]' }}">
                <i class="fas {{ $icon }} mr-1"></i>{{ $label }}
                @if($badge)
                    <span class="ml-1 inline-flex min-w-5 h-5 px-1.5 items-center justify-center rounded-full bg-action text-white text-[10px] tracking-normal">{{ $badge }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    {{-- Where this page sits, on pages below a tab: Events › Summit › Edit.
         $crumbs is [[label, url], ..., [label]]; the last is this page. --}}
    @isset($crumbs)
        <nav aria-label="Breadcrumb" class="mt-4 sm:mt-0 mb-2 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] font-bold text-gray-400">
            @foreach($crumbs as $i => $crumb)
                @if($i > 0)<i class="fas fa-chevron-right text-[8px] text-gray-300" aria-hidden="true"></i>@endif
                @if(isset($crumb[1]))
                    <a href="{{ $crumb[1] }}" wire:navigate class="hover:text-[#1D4069] max-w-[14rem] truncate">
                        @if($i === 0)<i class="fas fa-arrow-left mr-1"></i>@endif{{ $crumb[0] }}
                    </a>
                @else
                    <span class="text-gray-500 max-w-[14rem] truncate" aria-current="page">{{ $crumb[0] }}</span>
                @endif
            @endforeach
        </nav>
    @endisset

    <h1 class="{{ isset($crumbs) ? '' : 'mt-4 sm:mt-0' }} text-2xl font-black text-[#1D4069] tracking-tight">{{ $title }}</h1>

    {{-- Sections of this area (Settings), right under its unchanging title. --}}
    @isset($subnav)
        <div class="mt-4">@include($subnav)</div>
    @endisset

    @isset($subtitle)
        <p class="text-[13px] font-medium text-gray-500 {{ isset($subnav) ? '' : 'mt-1' }}">{{ $subtitle }}</p>
    @endisset

    @if(session('status'))
        <div class="mt-6 p-4 rounded-2xl bg-mint border border-mint text-[12px] font-bold text-mint-ink">
            {{ session('status') }}
        </div>
    @endif
</div>

{{-- Phones: an icon tab bar at the bottom, in thumb reach. The layout
     places it below the scrolling content, so nothing hides behind it. --}}
@section('bottom_bar')
    <nav class="sm:hidden flex-none bg-white border-t border-gray-100 pb-[env(safe-area-inset-bottom)]">
        <div class="grid {{ count($tabs) === 4 ? 'grid-cols-4' : 'grid-cols-3' }}">
            @foreach($tabs as [$route, $label, $short, $icon, $active, $badge])
                @php $on = request()->routeIs(...explode('|', $active)); @endphp
                <a href="{{ route($route) }}" wire:navigate @if($on) aria-current="page" @endif
                   class="relative flex flex-col items-center gap-1 py-2.5 {{ $on ? 'text-brand' : 'text-gray-400' }}">
                    @if($on)<span class="absolute top-0 inset-x-6 h-0.5 rounded-full bg-brand"></span>@endif
                    <span class="relative">
                        <i class="fas {{ $icon }} text-base"></i>
                        @if($badge)
                            <span class="absolute -top-2 -right-3 min-w-4 h-4 px-1 rounded-full bg-action text-white text-[9px] font-black leading-4 text-center">{{ $badge > 99 ? '99+' : $badge }}</span>
                        @endif
                    </span>
                    <span class="text-[9px] font-black uppercase tracking-wider">{{ $short }}</span>
                </a>
            @endforeach
        </div>
    </nav>
@endsection

@extends('layouts.app')

@section('title', 'Browse Events')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12">
    
    <div class="mb-8">
        <a href="{{ url('/') }}" class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069] mb-4 inline-block transition-colors">
            ← Back to home
        </a>
        <h1 class="text-4xl font-black text-[#1D4069] tracking-tight uppercase italic mb-2">Upcoming <span class="text-[#F07F22]">Events.</span></h1>
        <p class="text-sm text-gray-500 font-medium">Find events near you - professional infrastructure for Lesotho.</p>
    </div>

    <div class="mb-10 flex flex-wrap gap-2">
        <button class="px-5 py-2.5 bg-[#1D4069] text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-blue-900/20">
            All Events
        </button>
        <button class="px-5 py-2.5 bg-white border border-gray-200 text-gray-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:border-[#F07F22] hover:text-[#F07F22] transition-all">
            Free Access
        </button>
    </div>

    @if($events->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($events as $event)
                {{-- Entire Card is now a Link --}}
                <a href="{{ route('public.event', ['orgSlug' => $event->organization->slug, 'eventSlug' => $event->slug]) }}" 
                   class="group flex flex-col bg-white rounded-[2rem] shadow-sm overflow-hidden hover:shadow-2xl hover:shadow-blue-900/10 transition-all duration-500 border border-gray-100 outline-none">
                    
                    <div class="aspect-[16/9] w-full overflow-hidden bg-gray-100 relative">
                        @if($event->banner_image)
                            <img src="{{ \App\Support\Thumb::url($event->banner_image, 480) }}" 
                                 alt="{{ $event->name }}" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-[#1D4069] to-[#1D4069]/80 flex items-center justify-center">
                                <span class="text-white/20 font-black italic text-4xl uppercase tracking-tighter">Ventiq.</span>
                            </div>
                        @endif
                        
                        <div class="absolute top-4 right-4">
                            @php $minPrice = $event->tiers->min('price'); @endphp
                            <span class="px-3 py-1.5 rounded-lg bg-white/90 backdrop-blur text-[10px] font-black text-[#1D4069] shadow-sm uppercase tracking-widest">
                                {{ $minPrice == 0 ? 'Free' : 'M' . number_format($minPrice) . '+' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-8 flex flex-col flex-grow">
                        <div class="text-[9px] text-[#F07F22] font-black uppercase tracking-[0.2em] mb-3">
                            {{ $event->organization->name }}
                        </div>

                        <div class="h-[56px] mb-2">
                            <h3 class="text-xl font-black text-[#1D4069] leading-tight line-clamp-2 uppercase tracking-tight group-hover:text-[#F07F22] transition-colors">
                                {{ $event->name }}
                            </h3>
                        </div>
                        
                        <div class="h-[40px] mb-6">
                            @if($event->tagline)
                                <p class="text-xs text-gray-500 font-medium line-clamp-2 italic">
                                    {{ $event->tagline }}
                                </p>
                            @endif
                        </div>

                        <div class="space-y-3 mb-8 flex-grow">
                            @if($event->event_date)
                                <div class="flex items-center text-[11px] font-bold text-gray-600 uppercase tracking-tight">
                                    <i class="far fa-calendar-alt text-[#F07F22] w-5 text-sm"></i>
                                    <span>{{ $event->event_date->format('M j, Y') }} @ {{ $event->event_date->format('g:i A') }}</span>
                                </div>
                            @endif

                            @if($event->venue)
                                <div class="flex items-center text-[11px] font-bold text-gray-600 uppercase tracking-tight">
                                    <i class="fas fa-map-marker-alt text-[#1D4069] w-5 text-sm"></i>
                                    <span class="line-clamp-1">{{ $event->venue }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="mt-auto w-full text-center bg-[#1D4069] group-hover:bg-[#F07F22] text-white font-black py-4 px-4 rounded-2xl transition-all uppercase text-[10px] tracking-[0.3em] shadow-lg shadow-blue-900/10">
                            Secure Access
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-16 custom-pagination">
            {{ $events->links() }}
        </div>
    @else
        <div class="bg-white rounded-[2.5rem] shadow-sm p-16 text-center border border-dashed border-gray-200">
            <h3 class="text-xl font-black text-[#1D4069] uppercase italic mb-2">Protocol Empty</h3>
            <p class="text-sm text-gray-400 font-medium mb-8">No events currently scheduled in the Ventiq network.</p>
            <a href="{{ url('/') }}" class="text-[10px] font-black uppercase tracking-widest text-[#F07F22] hover:underline">
                ← Return to Base
            </a>
        </div>
    @endif
</div>
@endsection
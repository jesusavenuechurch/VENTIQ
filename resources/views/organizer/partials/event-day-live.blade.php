{{-- The parts of the event-day page that refresh on their own. --}}
@php
    $card = 'bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6';
    $maxHour = max(1, (int) $arrivals->max('people'));
@endphp

<div class="grid lg:grid-cols-3 gap-4 mb-4">
    {{-- Headline: people in against people expected. --}}
    <div class="{{ $card }} lg:col-span-2">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">People in</p>
        <p class="mt-2 flex items-baseline gap-2">
            <span class="text-5xl font-black text-[#1D4069] tracking-tight">{{ number_format($summary['admitted']) }}</span>
            <span class="text-[15px] font-bold text-gray-400">of {{ number_format($summary['expected']) }} expected</span>
        </p>
        <div class="mt-4 h-3 rounded-full bg-slate-100 overflow-hidden" role="meter" aria-valuemin="0" aria-valuemax="{{ $summary['expected'] }}" aria-valuenow="{{ $summary['admitted'] }}" aria-label="People checked in">
            <div class="h-full rounded-full bg-brand" style="width: {{ $summary['percent'] }}%"></div>
        </div>
        <p class="mt-2 text-[12px] font-semibold text-gray-500">
            {{ $summary['percent'] }}% arrived · {{ number_format($summary['still_to_come']) }} still to come
        </p>
        @php
            $cheer = match (true) {
                $summary['expected'] === 0                      => null,
                $summary['admitted'] === 0                      => 'Doors open soon. Arrivals show up here as tickets are scanned.',
                $summary['admitted'] >= $summary['expected']    => 'Everyone\'s here! Enjoy your event.',
                $summary['percent'] >= 75                       => 'Nearly everyone\'s in.',
                $summary['percent'] >= 50                       => 'More than half are in!',
                default                                         => 'People are arriving. Things are warming up.',
            };
        @endphp
        @if($cheer)
            <p class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full {{ $summary['admitted'] >= $summary['expected'] ? 'bg-mint text-mint-ink' : 'bg-slate-50 text-[#1D4069]' }} text-[12px] font-bold">{{ $cheer }}</p>
        @endif
    </div>

    <div class="{{ $card }} grid grid-cols-2 lg:grid-cols-1 gap-4">
        <div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Tickets scanned</p>
            <p class="mt-1 text-2xl font-black text-[#1D4069]">{{ number_format($summary['tickets_arrived']) }} <span class="text-[13px] font-bold text-gray-400">of {{ number_format($summary['tickets']) }}</span></p>
        </div>
        <div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Not paid yet</p>
            <p class="mt-1 text-2xl font-black text-[#1D4069]">{{ number_format($summary['unpaid_people']) }} <span class="text-[13px] font-bold text-gray-400">people</span></p>
            @if($summary['unpaid_people'])
                <p class="text-[11px] font-medium text-gray-400">Their tickets won't scan until payment is confirmed.</p>
            @endif
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-4 mb-4">
    <div class="{{ $card }}">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">By ticket type</p>
        <div class="space-y-4">
            @forelse($tiers as $tier)
                @php($pct = $tier['expected'] ? min(100, (int) floor($tier['admitted'] / $tier['expected'] * 100)) : 0)
                <div>
                    <div class="flex justify-between text-[12px] font-bold">
                        <span class="text-[#1D4069]">{{ $tier['name'] }}</span>
                        <span class="text-gray-500">{{ $tier['admitted'] }} / {{ $tier['expected'] }}</span>
                    </div>
                    <div class="mt-1.5 h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-[12px] text-gray-400">No valid tickets yet.</p>
            @endforelse
        </div>
    </div>

    <div class="{{ $card }}">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Arrivals by hour</p>
        @if($arrivals->isEmpty())
            <p class="text-[12px] text-gray-400">Nobody has been scanned in yet.</p>
        @else
            <div class="flex items-end gap-0.5 h-36" role="img" aria-label="People arriving per hour">
                @foreach($arrivals as $slot)
                    <div class="group relative flex-1 h-full flex flex-col justify-end items-center" tabindex="0">
                        <div class="w-full max-w-8 rounded-t-[4px] bg-brand group-hover:bg-action group-focus:bg-action transition-colors"
                             style="height: {{ $slot['people'] ? max(4, round($slot['people'] / $maxHour * 100)) : 0 }}%"></div>
                        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block group-focus:block whitespace-nowrap px-2 py-1 rounded-lg bg-[#1D4069] text-white text-[10px] font-bold z-10">
                            {{ $slot['hour']->format('H:i') }} · {{ $slot['people'] }} {{ \Illuminate\Support\Str::plural('person', $slot['people']) }}
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-0.5 mt-1 pt-1 border-t border-slate-100 text-[10px] font-bold text-gray-400">
                @foreach($arrivals as $i => $slot)
                    <span class="flex-1 text-center">
                        @if($arrivals->count() <= 12 || $i % (int) ceil($arrivals->count() / 6) === 0){{ $slot['hour']->format('H:i') }}@endif
                    </span>
                @endforeach
            </div>
            <table class="sr-only">
                <caption>People arriving per hour</caption>
                @foreach($arrivals as $slot)<tr><th>{{ $slot['hour']->format('H:i') }}</th><td>{{ $slot['people'] }}</td></tr>@endforeach
            </table>
        @endif
    </div>
</div>

<div class="{{ $card }} mb-4">
    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Latest arrivals</p>
    <div class="divide-y divide-gray-50">
        @forelse($recent as $ticket)
            <div class="py-2.5 flex items-center justify-between gap-3">
                <div class="min-w-0 flex items-center gap-3">
                    <x-avatar :seed="$ticket->client->phone" size="w-9 h-9" />
                    <div class="min-w-0">
                    <p class="text-[13px] font-black text-[#1D4069] truncate">{{ $ticket->holder_name }}</p>
                    <p class="text-[11px] font-medium text-gray-500">
                        {{ $ticket->tier->tier_name }}
                        @if(($ticket->admissions ?? 1) > 1) · {{ $ticket->admitted_count }} of {{ $ticket->admissions }} in @endif
                    </p>
                    </div>
                </div>
                <span class="text-[11px] font-bold text-gray-400 shrink-0">{{ $ticket->checked_in_at->format('H:i') }}</span>
            </div>
        @empty
            <p class="text-[12px] text-gray-400">Arrivals appear here as tickets are scanned.</p>
        @endforelse
    </div>
</div>
